<?php

namespace Tests\Unit\Services;

use App\Services\PricingCalculator;
use App\Config\GameConfig;
use App\Resources\GameSessionData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PricingCalculator::class)]
class PricingCalculatorTest extends TestCase
{
    private GameConfig $config;
    private PricingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a real GameConfig instance with test data
        $testConfig = [
            'vs_ai' => [
                'baseCost' => 50,
                'extraCardCost' => 15,
                'callInterval' => 4
            ],
            'pvp' => [
                'baseCost' => 100,
                'extraCardCost' => 25,
                'callInterval' => 4
            ]
        ];
        
        $this->config = new GameConfig($testConfig);
        $this->calculator = new PricingCalculator($this->config);
    }

    #[Test]
    public function calculateEntryCostForSingleCardInVsAIMode(): void
    {
        // Act
        $cost = $this->calculator->calculateEntryCost('vs_ai', 1);

        // Assert
        $this->assertEquals(50, $cost, "Single card should cost only base cost");
    }

    #[Test]
    public function calculateEntryCostForMultipleCardsInVsAIMode(): void
    {
        // Act
        $cost = $this->calculator->calculateEntryCost('vs_ai', 4);

        // Assert
        // Formula: baseCost + ((numberOfCards - 1) * extraCardCost)
        // 50 + ((4 - 1) * 15) = 50 + 45 = 95
        $this->assertEquals(95, $cost);
    }

    #[Test]
    public function calculateEntryCostForSingleCardInPvPMode(): void
    {
        // Act
        $cost = $this->calculator->calculateEntryCost('pvp', 1);

        // Assert
        $this->assertEquals(100, $cost);
    }

    #[Test]
    public function calculateEntryCostForMultipleCardsInPvPMode(): void
    {
        // Act
        $cost = $this->calculator->calculateEntryCost('pvp', 5);

        // Assert
        // Formula: 100 + ((5 - 1) * 25) = 100 + 100 = 200
        $this->assertEquals(200, $cost);
    }

    #[Test]
    #[DataProvider('entryCostDataProvider')]
    public function calculateEntryCostWithVariousCardCounts(
        string $mode, 
        int $cards, 
        int $expected
    ): void
    {
        // Act
        $cost = $this->calculator->calculateEntryCost($mode, $cards);

        // Assert
        $this->assertEquals($expected, $cost);
    }

    public static function entryCostDataProvider(): array
    {
        return [
            'vs_ai with 1 card' => ['vs_ai', 1, 50],
            'vs_ai with 2 cards' => ['vs_ai', 2, 65],  // 50 + (1 * 15)
            'vs_ai with 3 cards' => ['vs_ai', 3, 80],  // 50 + (2 * 15)
            'vs_ai with 5 cards' => ['vs_ai', 5, 110], // 50 + (4 * 15)
            'pvp with 1 card' => ['pvp', 1, 100],
            'pvp with 2 cards' => ['pvp', 2, 125],     // 100 + (1 * 25)
            'pvp with 3 cards' => ['pvp', 3, 150],     // 100 + (2 * 25)
            'pvp with 6 cards' => ['pvp', 6, 225],     // 100 + (5 * 25)
        ];
    }

    #[Test]
    public function calculateEntryCostReturnsDefaultForUnsupportedMode(): void
    {
        // Act
        $cost = $this->calculator->calculateEntryCost('unsupported_mode', 3);

        // Assert
        $this->assertEquals(50, $cost, "Should return default fallback cost of 50");
    }

    #[Test]
    public function getCallIntervalForVsAIMode(): void
    {
        // Act
        $interval = $this->calculator->getCallInterval('vs_ai');

        // Assert
        $this->assertEquals(4, $interval);
    }

    #[Test]
    public function getCallIntervalForPvPMode(): void
    {
        // Act
        $interval = $this->calculator->getCallInterval('pvp');

        // Assert
        $this->assertEquals(4, $interval);
    }

    #[Test]
    public function getCallIntervalReturnsDefaultForUnknownMode(): void
    {
        // Act
        $interval = $this->calculator->getCallInterval('unknown_mode');

        // Assert
        $this->assertEquals(4, $interval, "Should return default interval of 4");
    }

    #[Test]
    public function calculatePrizePoolWithSingleParticipant(): void
    {
        // Arrange
        $sessionData = new GameSessionData('test-session');
        $sessionData->sessionType = 'vs_ai';
        $sessionData->participants = [
            'user-1' => [
                'type' => 'user',
                'numberOfCards' => 3,
                'joinedAt' => time()
            ]
        ];

        // Act
        $prizePool = $this->calculator->calculatePrizePool($sessionData);

        // Assert
        // 50 + (2 * 15) = 80
        $this->assertEquals(80, $prizePool);
    }

    #[Test]
    public function calculatePrizePoolWithMultipleParticipants(): void
    {
        // Arrange
        $sessionData = new GameSessionData('test-session');
        $sessionData->sessionType = 'vs_ai';
        $sessionData->participants = [
            'user-1' => [
                'type' => 'user',
                'numberOfCards' => 2,
                'joinedAt' => time()
            ],
            'AI_1' => [
                'type' => 'ai',
                'numberOfCards' => 3,
                'joinedAt' => time()
            ],
            'AI_2' => [
                'type' => 'ai',
                'numberOfCards' => 1,
                'joinedAt' => time()
            ]
        ];

        // Act
        $prizePool = $this->calculator->calculatePrizePool($sessionData);

        // Assert
        // user-1: 50 + (1 * 15) = 65
        // AI_1:   50 + (2 * 15) = 80
        // AI_2:   50 + (0 * 15) = 50
        // Total: 65 + 80 + 50 = 195
        $this->assertEquals(195, $prizePool);
    }

    #[Test]
    public function calculatePrizePoolForPvPMode(): void
    {
        // Arrange
        $sessionData = new GameSessionData('pvp-session');
        $sessionData->sessionType = 'pvp';
        $sessionData->participants = [
            'user-1' => [
                'type' => 'user',
                'numberOfCards' => 4,
                'joinedAt' => time()
            ],
            'user-2' => [
                'type' => 'user',
                'numberOfCards' => 2,
                'joinedAt' => time()
            ]
        ];

        // Act
        $prizePool = $this->calculator->calculatePrizePool($sessionData);

        // Assert
        // user-1: 100 + (3 * 25) = 175
        // user-2: 100 + (1 * 25) = 125
        // Total: 175 + 125 = 300
        $this->assertEquals(300, $prizePool);
    }

    #[Test]
    public function calculatePrizePoolWithEmptySession(): void
    {
        // Arrange
        $sessionData = new GameSessionData('empty-session');
        $sessionData->sessionType = 'vs_ai';
        $sessionData->participants = [];

        // Act
        $prizePool = $this->calculator->calculatePrizePool($sessionData);

        // Assert
        $this->assertEquals(0, $prizePool, "Empty session should have 0 prize pool");
    }

    #[Test]
    public function calculatePrizePoolWithMixedCardCounts(): void
    {
        // Arrange
        $sessionData = new GameSessionData('mixed-session');
        $sessionData->sessionType = 'vs_ai';
        $sessionData->participants = [
            'user-1' => ['type' => 'user', 'numberOfCards' => 1, 'joinedAt' => time()],
            'user-2' => ['type' => 'user', 'numberOfCards' => 5, 'joinedAt' => time()],
            'AI_1' => ['type' => 'ai', 'numberOfCards' => 2, 'joinedAt' => time()],
            'AI_2' => ['type' => 'ai', 'numberOfCards' => 4, 'joinedAt' => time()],
        ];

        // Act
        $prizePool = $this->calculator->calculatePrizePool($sessionData);

        // Assert
        // user-1: 50 + (0 * 15) = 50
        // user-2: 50 + (4 * 15) = 110
        // AI_1:   50 + (1 * 15) = 65
        // AI_2:   50 + (3 * 15) = 95
        // Total: 50 + 110 + 65 + 95 = 320
        $this->assertEquals(320, $prizePool);
    }

    #[Test]
    public function prizePoolCalculationUsesCorrectGameMode(): void
    {
        // Arrange - Create two sessions with different modes
        $vsAISession = new GameSessionData('vs-ai-session');
        $vsAISession->sessionType = 'vs_ai';
        $vsAISession->participants = [
            'user-1' => ['type' => 'user', 'numberOfCards' => 2, 'joinedAt' => time()]
        ];

        $pvpSession = new GameSessionData('pvp-session');
        $pvpSession->sessionType = 'pvp';
        $pvpSession->participants = [
            'user-1' => ['type' => 'user', 'numberOfCards' => 2, 'joinedAt' => time()]
        ];

        // Act
        $vsAIPool = $this->calculator->calculatePrizePool($vsAISession);
        $pvpPool = $this->calculator->calculatePrizePool($pvpSession);

        // Assert
        // vs_ai: 50 + (1 * 15) = 65
        // pvp:   100 + (1 * 25) = 125
        $this->assertEquals(65, $vsAIPool);
        $this->assertEquals(125, $pvpPool);
        $this->assertNotEquals($vsAIPool, $pvpPool, "Different game modes should have different costs");
    }

    #[Test]
    public function entryCostFormulaIsCorrect(): void
    {
        // Test the actual formula: baseCost + ((numberOfCards - 1) * extraCardCost)
        
        // Arrange & Act
        $cost1 = $this->calculator->calculateEntryCost('vs_ai', 1);
        $cost2 = $this->calculator->calculateEntryCost('vs_ai', 2);
        $cost3 = $this->calculator->calculateEntryCost('vs_ai', 3);

        // Assert - Each additional card should cost extraCardCost (15)
        $this->assertEquals(15, $cost2 - $cost1, "Second card should add extraCardCost");
        $this->assertEquals(15, $cost3 - $cost2, "Third card should add extraCardCost");
    }

    #[Test]
    public function prizePoolSumsAllParticipantCosts(): void
    {
        // Arrange
        $sessionData = new GameSessionData('test-session');
        $sessionData->sessionType = 'vs_ai';
        $sessionData->participants = [
            'user-1' => ['type' => 'user', 'numberOfCards' => 2, 'joinedAt' => time()],
            'user-2' => ['type' => 'user', 'numberOfCards' => 3, 'joinedAt' => time()],
        ];

        // Act
        $prizePool = $this->calculator->calculatePrizePool($sessionData);
        
        // Calculate expected manually
        $user1Cost = $this->calculator->calculateEntryCost('vs_ai', 2);
        $user2Cost = $this->calculator->calculateEntryCost('vs_ai', 3);
        $expectedTotal = $user1Cost + $user2Cost;

        // Assert
        $this->assertEquals($expectedTotal, $prizePool, "Prize pool should equal sum of all entry costs");
    }

    #[Test]
    public function zeroCardsReturnsNegativeCost(): void
    {
        // This tests edge case behavior - what happens with 0 cards?
        // Based on formula: baseCost + ((0 - 1) * extraCardCost)
        // = 50 + (-1 * 15) = 50 - 15 = 35
        
        // Act
        $cost = $this->calculator->calculateEntryCost('vs_ai', 0);

        // Assert
        $this->assertEquals(35, $cost, "Zero cards results in: baseCost - extraCardCost");
    }

    #[Test]
    public function largeNumberOfCardsCalculatesCorrectly(): void
    {
        // Test with a large number of cards to ensure no integer overflow or logic issues
        
        // Act
        $cost = $this->calculator->calculateEntryCost('vs_ai', 20);

        // Assert
        // 50 + ((20 - 1) * 15) = 50 + 285 = 335
        $this->assertEquals(335, $cost);
    }
}