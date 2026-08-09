<?php

namespace App\Tests\Core;

use App\Core\SessionManager;
use App\Factories\SessionFactory;
use App\Resources\GameSessionData;
use App\Services\CacheService;
use Exception;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(SessionManager::class)]
final class SessionManagerTest extends TestCase
{
    private MockObject|PDO $pdoMock;
    private MockObject|CacheService $cacheMock;
    private MockObject|SessionFactory $sessionFactoryMock;
    private SessionManager $sessionManager;

    /**
     * Sets up mocks for all dependencies before each test.
     */
    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->cacheMock = $this->createMock(CacheService::class);
        $this->sessionFactoryMock = $this->createMock(SessionFactory::class);

        $this->sessionManager = new SessionManager(
            $this->pdoMock,
            $this->cacheMock,
            $this->sessionFactoryMock
        );
    }

    /**
     * Resets the static localCache property after each test to prevent test contamination.
     * This is CRITICAL for ensuring tests are isolated.
     */
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(SessionManager::class);
        $property = $reflection->getProperty('localCache');
        $property->setAccessible(true);
        $property->setValue(null, []); // Resetting the static property
    }

    #[Test]
    public function createSession_succeeds_and_saves_to_cache(): void
    {
        // Arrange
        $requestData = ['gameMode' => 'vs_ai'];
        $userId = 'user-123';
        $sessionId = 'new-session-id'; // We don't test UUIDGenerator, just the flow
        $expectedJson = '{"sessionId":"new-session-id"}';

        // Mock the GameSessionData object that the factory will create
        $sessionDataMock = $this->createMock(GameSessionData::class);
        $sessionDataMock->sessionId = $sessionId; // Assign the ID
        $sessionDataMock->method('toJson')->willReturn($expectedJson);

        // Configure the factory mock
        $this->sessionFactoryMock
            ->expects($this->once())
            ->method('createSession')
            ->willReturn($sessionDataMock);

        // Configure the cache mock to expect the save operation
        $this->cacheMock
            ->expects($this->once())
            ->method('set')
            ->with("session:{$sessionId}", $expectedJson, 900);

        // Act
        [$createdSessionId, $createdSessionData] = $this->sessionManager->createSession($requestData, $userId);

        // Assert
        // The session ID from createSession is a generated one, but the object's ID should match
        $this->assertNotEmpty($createdSessionId);
        $this->assertSame($sessionDataMock, $createdSessionData);
    }

    #[Test]
    public function createSession_throws_exception_when_cache_save_fails(): void
    {
        // Arrange
        $sessionDataMock = $this->createMock(GameSessionData::class);
        $sessionDataMock->sessionId = 'some-id';

        $this->sessionFactoryMock
            ->method('createSession')
            ->willReturn($sessionDataMock);

        // Configure the cache mock to throw an exception on 'set'
        $this->cacheMock
            ->method('set')
            ->willThrowException(new Exception('Cache unavailable'));

        // Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cache unavailable');

        // Act
        $this->sessionManager->createSession(['gameMode' => 'vs_ai'], 'user-123');
    }

    #[Test]
    public function getSession_retrieves_from_local_cache_first(): void
    {
        // Arrange
        $sessionId = 'session-in-local-cache';
        $sessionData = new GameSessionData($sessionId);

        // Manually populate the local cache using reflection
        $reflection = new ReflectionClass(SessionManager::class);
        $property = $reflection->getProperty('localCache');
        $property->setAccessible(true);
        $property->setValue(null, [$sessionId => $sessionData]);

        // The external cache service should NOT be called
        $this->cacheMock->expects($this->never())->method('get');

        // Act
        $result = $this->sessionManager->getSession($sessionId);

        // Assert
        $this->assertSame($sessionData, $result);
    }

    #[Test]
    public function getSession_retrieves_from_external_cache_and_populates_local_cache(): void
    {
        // Arrange
        $sessionId = 'session-in-redis';
        $jsonData = '{"sessionId":"session-in-redis", "sessionType":"vs_ai"}';

        // Configure cache mock to return data
        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with("session:{$sessionId}")
            ->willReturn($jsonData);

        // Act
        $result = $this->sessionManager->getSession($sessionId);

        // Assert
        $this->assertInstanceOf(GameSessionData::class, $result);
        $this->assertEquals($sessionId, $result->sessionId);

        // Now, get it again and assert the external cache is not hit a second time
        $this->cacheMock->expects($this->never())->method('get');
        $this->sessionManager->getSession($sessionId);
    }

    #[Test]
    public function getSession_returns_null_when_not_found_anywhere(): void
    {
        // Arrange
        $sessionId = 'non-existent-session';

        // Configure cache mock to find nothing
        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with("session:{$sessionId}")
            ->willReturn(null);

        // Act
        $result = $this->sessionManager->getSession($sessionId);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function saveSession_persists_to_cache_and_updates_local_cache(): void
    {
        // Arrange
        $sessionId = 'session-to-save';
        $jsonData = '{"sessionId":"session-to-save"}';
        
        $sessionDataMock = $this->createMock(GameSessionData::class);
        $sessionDataMock->sessionId = $sessionId;
        $sessionDataMock->method('toJson')->willReturn($jsonData);
        
        $this->cacheMock
            ->expects($this->once())
            ->method('set')
            ->with("session:{$sessionId}", $jsonData, 900);

        // Act
        $this->sessionManager->saveSession($sessionDataMock, $sessionId);

        // Assert that the local cache is now populated
        $locallyCached = $this->sessionManager->getSession($sessionId);
        $this->assertSame($sessionDataMock, $locallyCached);
    }
}
