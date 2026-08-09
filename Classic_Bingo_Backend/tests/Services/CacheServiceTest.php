<?php

namespace App\Tests\Services;

use App\Config\CacheConfig;
use App\Services\CacheService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client as PredisClient;
use ReflectionClass;

/**
 * Unit test suite for the CacheService.
 *
 * @covers \App\Services\CacheService
 */
final class CacheServiceTest extends TestCase {
    private CacheConfig $config;
    private MockObject|PredisClient $predisClientMock;

    /**
     * Sets up a standard config and a mock for the Predis client before each test.
     */
    protected function setUp(): void {
        $this->config = new CacheConfig([
            'host' => 'localhost',
            'port' => 6379
        ]);

        $this->predisClientMock = $this->createMock(PredisClient::class);
    }

    /**
     * Helper method to inject the mock Predis client into the CacheService's private property.
     */
    private function injectMockClient(CacheService $service, MockObject $clientMock): void {
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $clientMock);
    }

    // ===================================================================
    // Test for get()
    // ===================================================================

    #[Test]
    public function it_successfully_retrieves_a_value(): void {
        // Arrange
        $key = 'test-key';
        $expectedValue = 'test-value';
        $cacheService = new CacheService($this->config);

        // Configure the mock Predis client
        $this->predisClientMock
            ->expects($this->once())
            //Mock the __call method instead of get(), because Predis uses PHP magic method __call to handle all the Redis commands like get(), set() and del().
            ->method('__call')
            // Assert __call is invoked with 'get' and its arguments
            ->with('get', [$key])
            ->willReturn($expectedValue);

        // Inject the mock into the service
        $this->injectMockClient($cacheService, $this->predisClientMock);

        // Act
        $actualValue = $cacheService->get($key);

        // Assert
        $this->assertEquals($expectedValue, $actualValue);
    }

    // ===================================================================
    // Tests for set()
    // ===================================================================

   #[Test]
    public function it_sets_a_value_with_a_ttl(): void {
        // Arrange
        $key = 'test-key';
        $value = 'test-value';
        $ttl = 3600; // 1 hour
        $cacheService = new CacheService($this->config);
        
        // This array will hold the arguments of each call to `__call`.
        $callArguments = [];

        // We expect __call to be invoked twice. The callback will capture the
        // arguments of each call so we can assert them later.
        $this->predisClientMock->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function(string $method, array $args) use (&$callArguments) {
                $callArguments[] = [$method, $args];
                // Return null as the return value of set/expire is not used.
                return null;
            });

        $this->injectMockClient($cacheService, $this->predisClientMock);

        // Act
        $cacheService->set($key, $value, $ttl);

        // Assert
        // Now, check that the captured arguments match what we expected.
        $expectedCalls = [
            ['set', [$key, $value]],
            ['expire', [$key, $ttl]],
        ];

        $this->assertEquals($expectedCalls, $callArguments);
    }

    #[Test]
    public function it_sets_a_value_without_a_ttl(): void {
        // Arrange
        $key = 'test-key';
        $value = 'test-value';
        $cacheService = new CacheService($this->config);

        //Expect __call once for the 'set' command
        $this->predisClientMock->expects($this->once())
            ->method('__call')
            ->with('set', [$key, $value]);

        $this->injectMockClient($cacheService, $this->predisClientMock);

        // Act
        $cacheService->set($key, $value, null);
    }

    // ===================================================================
    // Test for delete()
    // ===================================================================

    #[Test]
    public function it_deletes_a_key_and_returns_the_count(): void {
        // Arrange
        $key = 'key-to-delete';
        $expectedCount = 1;
        $cacheService = new CacheService($this->config);

        $this->predisClientMock
            ->expects($this->once())
            ->method('__call')
            ->with('del', [[$key]]) // Note the double array: del([$key]) -> __call('del', [[$key]])
            ->willReturn($expectedCount);

        $this->injectMockClient($cacheService, $this->predisClientMock);

        // Act
        $actualCount = $cacheService->delete($key);

        // Assert
        $this->assertEquals($expectedCount, $actualCount);
    }
}