<?php

namespace Tests\Unit\Factories;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Factories\SessionFactory;
use App\Utils\BingoGenerator;
use App\Services\ParticipantManager;
use App\Services\PricingCalculator;
use App\Resources\GameSessionData;
use App\Handlers\AppException;

class SessionFactoryTest extends TestCase {
    private MockObject|BingoGenerator $bingoGenerator;
    private MockObject|ParticipantManager $participantManager;
    private MockObject|PricingCalculator $pricingCalculator;
    private SessionFactory $sessionFactory;

    protected function setUp(): void {
        parent::setUp();
        
        // Create mock dependencies
        $this->bingoGenerator = $this->createMock(BingoGenerator::class);
        $this->participantManager = $this->createMock(ParticipantManager::class);
        $this->pricingCalculator = $this->createMock(PricingCalculator::class);
        
        // Instantiate SessionFactory with mocked dependencies
        $this->sessionFactory = new SessionFactory(
            $this->bingoGenerator,
            $this->participantManager,
            $this->pricingCalculator
        );
    }

    public function testCreateSessionWithVsAIMode(): void {
        // Arrange
        $sessionId = 'test-session-123';
        $userId = 'user-456';
        $requestData = [
            'gameMode' => 'vs_ai',
            'numberOfCards' => [2, 3, 1], // AI1: 2 cards, AI2: 3 cards, User: 1 card
            'numberOfAIOpponents' => 2
        ];

        $expectedNumberSequence = range(1, 75);
        $expectedBingoCards = [
            ['grid' => array_fill(0, 25, 1), 'daubed' => array_fill(0, 25, false), 'cardId' => 0],
            ['grid' => array_fill(0, 25, 2), 'daubed' => array_fill(0, 25, false), 'cardId' => 1],
            ['grid' => array_fill(0, 25, 3), 'daubed' => array_fill(0, 25, false), 'cardId' => 2],
            ['grid' => array_fill(0, 25, 4), 'daubed' => array_fill(0, 25, false), 'cardId' => 3],
            ['grid' => array_fill(0, 25, 5), 'daubed' => array_fill(0, 25, false), 'cardId' => 4],
            ['grid' => array_fill(0, 25, 6), 'daubed' => array_fill(0, 25, false), 'cardId' => 5],
        ];

        // Mock ParticipantManager to actually modify the GameSessionData
        $this->participantManager
            ->expects($this->once())
            ->method('addAIParticipants')
            ->with(
                $this->isInstanceOf(GameSessionData::class),
                2,
                [2, 3, 1]
            )
            ->willReturnCallback(function(GameSessionData $session, int $count, array $cards) {
                // Simulate adding AI participants
                $session->participants['AI_1'] = ['type' => 'ai', 'numberOfCards' => 2, 'joinedAt' => time()];
                $session->participants['AI_2'] = ['type' => 'ai', 'numberOfCards' => 3, 'joinedAt' => time()];
                $session->maxPlayers = 2;
            });

        $this->participantManager
            ->expects($this->once())
            ->method('addHumanParticipant')
            ->with(
                $this->isInstanceOf(GameSessionData::class),
                $userId,
                1
            )
            ->willReturnCallback(function(GameSessionData $session, string $userId, int $cards) {
                // Simulate adding human participant
                $session->participants[$userId] = ['type' => 'user', 'numberOfCards' => 1, 'joinedAt' => time()];
                $session->maxPlayers++;
            });

        // Mock PricingCalculator behavior
        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateEntryCost')
            ->with('vs_ai', 1)
            ->willReturn(50);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('getCallInterval')
            ->with('vs_ai')
            ->willReturn(4);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculatePrizePool')
            ->with($this->isInstanceOf(GameSessionData::class))
            ->willReturn(150);

        // Mock BingoGenerator behavior
        $this->bingoGenerator
            ->expects($this->once())
            ->method('generateBingoCards')
            ->with(6) // Total cards: 2 + 3 + 1 = 6
            ->willReturn($expectedBingoCards);

        $this->bingoGenerator
            ->expects($this->once())
            ->method('generateNumberSequence')
            ->willReturn($expectedNumberSequence);

        // Act
        $result = $this->sessionFactory->createSession($sessionId, $requestData, $userId);

        // Assert
        $this->assertInstanceOf(GameSessionData::class, $result);
        $this->assertEquals($sessionId, $result->sessionId);
        $this->assertEquals('vs_ai', $result->sessionType);
        $this->assertEquals(50, $result->entryCost);
        $this->assertEquals(4, $result->callInterval);
        $this->assertEquals(150, $result->pricePool);
        $this->assertEquals($expectedBingoCards, $result->bingoCards);
        $this->assertEquals($expectedNumberSequence, $result->numbersToCall);
        $this->assertEquals(-1, $result->currentNumberIndex);
        $this->assertCount(3, $result->participants);
    }

    public function testCreateSessionWithSingleAIOpponent(): void  {
        // Arrange
        $sessionId = 'test-session-single';
        $userId = 'user-789';
        $requestData = [
            'gameMode' => 'vs_ai',
            'numberOfCards' => [1, 2], // AI: 1 card, User: 2 cards
            'numberOfAIOpponents' => 1
        ];

        // Setup mocks with callbacks to modify session data
        $this->participantManager
            ->expects($this->once())
            ->method('addAIParticipants')
            ->willReturnCallback(function(GameSessionData $session) {
                $session->participants['AI_1'] = ['type' => 'ai', 'numberOfCards' => 1, 'joinedAt' => time()];
                $session->maxPlayers = 1;
            });

        $this->participantManager
            ->expects($this->once())
            ->method('addHumanParticipant')
            ->willReturnCallback(function(GameSessionData $session, string $userId) {
                $session->participants[$userId] = ['type' => 'user', 'numberOfCards' => 2, 'joinedAt' => time()];
                $session->maxPlayers++;
            });

        $this->pricingCalculator
            ->method('calculateEntryCost')
            ->willReturn(65);

        $this->pricingCalculator
            ->method('getCallInterval')
            ->willReturn(4);

        $this->pricingCalculator
            ->method('calculatePrizePool')
            ->willReturn(100);

        $this->bingoGenerator
            ->method('generateBingoCards')
            ->with(3)
            ->willReturn([]);

        $this->bingoGenerator
            ->method('generateNumberSequence')
            ->willReturn(range(1, 75));

        // Act
        $result = $this->sessionFactory->createSession($sessionId, $requestData, $userId);

        // Assert
        $this->assertInstanceOf(GameSessionData::class, $result);
        $this->assertEquals('vs_ai', $result->sessionType);
        $this->assertCount(2, $result->participants);
    }

    public function testCreateSessionThrowsExceptionForUnsupportedGameMode(): void  {
        // Arrange
        $sessionId = 'test-session-invalid';
        $userId = 'user-999';
        $requestData = [
            'gameMode' => 'unsupported_mode',
            'numberOfCards' => [1],
            'numberOfAIOpponents' => 1
        ];

        // Assert exception
        $this->expectException(AppException::class);

        // Act
        $this->sessionFactory->createSession($sessionId, $requestData, $userId);
    }
}