<?php

namespace App\Tests\Services;

use App\Core\SessionManager;
use App\Enums\ErrorCode;
use App\Handlers\AppException;
use App\Resources\GameSessionData;
use App\Services\GameService;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test suite for the GameService.
 *
 * @covers \App\Services\GameService
 */
final class GameServiceTest extends TestCase {
    private MockObject|SessionManager $sessionManagerMock;
    private GameService $gameService;

    protected function setUp(): void
    {
        $this->sessionManagerMock = $this->createMock(SessionManager::class);
        $this->gameService = new GameService($this->sessionManagerMock);
    }

    // ===================================================================
    // Tests for startNewGame()
    // ===================================================================

    #[Test]
    public function it_successfully_starts_a_new_game(): void {
        // Arrange
        $userId = 'user-123';
        // Provide valid request data to pass validation 
        $requestData = [
            'gameMode' => 'vs_ai',
            'numberOfAIOpponents' => 1,
            'numberOfCards' => [1]
        ];
        $sessionId = 'session-abc';
        $sessionData = new GameSessionData($sessionId);

        $this->sessionManagerMock
            ->expects($this->once())
            ->method('createSession')
            ->with($requestData, $userId)
            ->willReturn([$sessionId, $sessionData]);

        // Act
        $result = $this->gameService->startNewGame($requestData, $userId);

        // Assert
        $this->assertEquals(201, $result['status']);
        $this->assertTrue($result['data']['success']);
        $this->assertEquals($sessionId, $result['data']['data']['sessionId']);
        $this->assertEquals($sessionData->toLobbyData(), $result['data']['data']['sessionData']);
    }

    #[Test]
    public function it_rethrows_app_exception_from_session_manager_during_game_start(): void {
        // Arrange
        $userId = 'user-123';
        $requestData = [
            'gameMode' => 'vs_ai',
            'numberOfAIOpponents' => 1,
            'numberOfCards' => [1]
        ];
        $expectedException = new AppException(ErrorCode::GAME_MODE_UNSUPPORTED);

        $this->sessionManagerMock
            ->method('createSession')
            ->willThrowException($expectedException);

        $this->expectExceptionObject($expectedException);

        // Act
        $this->gameService->startNewGame($requestData, $userId);
    }

    #[Test]
    public function it_wraps_unexpected_exceptions_during_game_start(): void {
        // Arrange
        $userId = 'user-123';
        $requestData = [
            'gameMode' => 'vs_ai',
            'numberOfAIOpponents' => 1,
            'numberOfCards' => [1]
        ];

        $this->sessionManagerMock
            ->method('createSession')
            ->willThrowException(new Exception("Something went wrong!"));

        $this->expectException(AppException::class);
        $this->expectExceptionCode(500);

        // Act
        $this->gameService->startNewGame($requestData, $userId);
    }

    // ===================================================================
    // Tests for processNextNumberForSession() - These were already passing
    // ===================================================================

    #[Test]
    public function it_throws_exception_if_session_is_not_found(): void {
        $sessionId = 'non-existent-session';
        $this->sessionManagerMock
            ->method('getSession')
            ->with($sessionId)
            ->willReturn(null);
        $this->expectException(AppException::class);
        $this->expectExceptionCode(404);
        $this->gameService->processNextNumberForSession($sessionId);
    }

    #[Test]
    public function it_calls_the_first_number_for_a_new_session(): void {
        $sessionId = 'session-new';
        $sessionData = new GameSessionData($sessionId);
        $sessionData->numbersToCall = [15, 25, 35];
        $sessionData->currentNumberIndex = -1;
        $sessionData->isActive = false;

        $this->sessionManagerMock->method('getSession')->willReturn($sessionData);
        $this->sessionManagerMock->expects($this->once())->method('saveSession');

        $result = $this->gameService->processNextNumberForSession($sessionId);

        $this->assertEquals(200, $result['status']);
        $this->assertEquals([15], $result['data']['data']['calledNumbers']);
        $this->assertFalse($result['data']['data']['isGameOver']);
        $this->assertTrue($sessionData->isActive);
        $this->assertEquals(0, $sessionData->currentNumberIndex);
        $this->assertNotNull($sessionData->startedAt);
    }

    #[Test]
    public function it_calls_a_subsequent_number_for_an_active_session(): void {
        $sessionId = 'session-active';
        $sessionData = new GameSessionData($sessionId);
        $sessionData->numbersToCall = [15, 25, 35];
        $sessionData->currentNumberIndex = 0;
        $sessionData->isActive = true;

        $this->sessionManagerMock->method('getSession')->willReturn($sessionData);
        $this->sessionManagerMock->expects($this->once())->method('saveSession');

        $result = $this->gameService->processNextNumberForSession($sessionId);

        $this->assertEquals(200, $result['status']);
        $this->assertEquals([25], $result['data']['data']['calledNumbers']);
        $this->assertFalse($result['data']['data']['isGameOver']);
        $this->assertEquals(1, $sessionData->currentNumberIndex);
    }

    #[Test]
    public function it_handles_the_end_of_the_game(): void {
        $sessionId = 'session-ending';
        $sessionData = new GameSessionData($sessionId);
        $sessionData->numbersToCall = [15, 25, 35];
        $sessionData->currentNumberIndex = 1;
        $sessionData->isActive = true;

        $this->sessionManagerMock->method('getSession')->willReturn($sessionData);
        $this->sessionManagerMock->expects($this->once())->method('saveSession');

        $result = $this->gameService->processNextNumberForSession($sessionId);

        $this->assertEquals(200, $result['status']);
        $this->assertEquals([35], $result['data']['data']['calledNumbers']);
        $this->assertTrue($result['data']['data']['isGameOver']);
        $this->assertEquals(2, $sessionData->currentNumberIndex);
    }

    #[Test]
    public function it_does_nothing_if_game_is_already_over(): void {
        $sessionId = 'session-finished';
        $sessionData = new GameSessionData($sessionId);
        $sessionData->numbersToCall = [15, 25, 35];
        $sessionData->currentNumberIndex = 2;
        $sessionData->isActive = true;

        $this->sessionManagerMock->method('getSession')->willReturn($sessionData);
        $this->sessionManagerMock->expects($this->never())->method('saveSession');

        $result = $this->gameService->processNextNumberForSession($sessionId);

        $this->assertEquals(200, $result['status']);
        $this->assertEmpty($result['data']['data']['calledNumbers']);
        $this->assertTrue($result['data']['data']['isGameOver']);
    }

    #[Test]
    public function it_throws_exception_if_saving_session_fails(): void {
        $sessionId = 'session-save-fail';
        $sessionData = new GameSessionData($sessionId);
        $sessionData->numbersToCall = [10, 20];
        $sessionData->currentNumberIndex = 0;
        $sessionData->isActive = true;

        $this->sessionManagerMock->method('getSession')->willReturn($sessionData);
        $this->sessionManagerMock
            ->method('saveSession')
            ->willThrowException(new Exception("Cache is down!"));

        $this->expectException(AppException::class);
        $this->expectExceptionCode(500);

        $this->gameService->processNextNumberForSession($sessionId);
    }
}