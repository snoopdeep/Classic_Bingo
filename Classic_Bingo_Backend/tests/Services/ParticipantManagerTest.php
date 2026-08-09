<?php

namespace Tests\Unit\Services;

use App\Resources\GameSessionData;
use App\Services\ParticipantManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParticipantManager::class)]
class ParticipantManagerTest extends TestCase {
    private ParticipantManager $manager;
    private GameSessionData $sessionData;

    protected function setUp(): void   {
        parent::setUp();
        $this->manager = new ParticipantManager();
        // A fresh session is created for each test to ensure isolation
        $this->sessionData = new GameSessionData('test-session-123');
        $this->sessionData->sessionType = 'vs_ai'; // Set the session type after construction
    }

    #[Test]
    public function addAIParticipantsCorrectlyAddsMultipleAIPlayers(): void  {
        // Arrange
        $aiCount = 2;
        $cardsPerAI = [3, 2]; // First AI gets 3 cards, second gets 2

        // Act
        $this->manager->addAIParticipants($this->sessionData, $aiCount, $cardsPerAI);

        // Assert - Overall state
        $this->assertSame(2, $this->sessionData->maxPlayers);
        $this->assertCount(2, $this->sessionData->participants);
        $this->assertCount(2, $this->sessionData->playerCards);

        // Get participant keys (which are the generated AI IDs)
        $aiIds = array_keys($this->sessionData->participants);

        // Assert - First AI
        $ai1_id = $aiIds[0];
        $this->assertStringStartsWith('AI_', $ai1_id);
        $this->assertSame('ai', $this->sessionData->participants[$ai1_id]['type']);
        $this->assertSame(3, $this->sessionData->participants[$ai1_id]['numberOfCards']);
        $this->assertArrayHasKey('joinedAt', $this->sessionData->participants[$ai1_id]);
        $this->assertIsInt($this->sessionData->participants[$ai1_id]['joinedAt']);
        $this->assertEquals([0, 1, 2], $this->sessionData->playerCards[$ai1_id]);

        // Assert - Second AI
        $ai2_id = $aiIds[1];
        $this->assertStringStartsWith('AI_', $ai2_id);
        $this->assertSame('ai', $this->sessionData->participants[$ai2_id]['type']);
        $this->assertSame(2, $this->sessionData->participants[$ai2_id]['numberOfCards']);
        $this->assertArrayHasKey('joinedAt', $this->sessionData->participants[$ai2_id]);
        $this->assertEquals([3, 4], $this->sessionData->playerCards[$ai2_id]);
    }

    #[Test]
    public function addSingleAIParticipantCorrectly(): void {
        // Arrange
        $aiCount = 1;
        $cardsPerAI = [5];

        // Act
        $this->manager->addAIParticipants($this->sessionData, $aiCount, $cardsPerAI);

        // Assert
        $this->assertSame(1, $this->sessionData->maxPlayers);
        $this->assertCount(1, $this->sessionData->participants);
        
        $aiIds = array_keys($this->sessionData->participants);
        $aiId = $aiIds[0];
        
        $this->assertStringStartsWith('AI_', $aiId);
        $this->assertSame('ai', $this->sessionData->participants[$aiId]['type']);
        $this->assertSame(5, $this->sessionData->participants[$aiId]['numberOfCards']);
        $this->assertEquals([0, 1, 2, 3, 4], $this->sessionData->playerCards[$aiId]);
    }

    #[Test]
    public function addHumanParticipantCorrectlyAddsPlayerToEmptySession(): void {
        // Arrange
        $userId = 'human-player-1';
        $numberOfCards = 4;

        // Act
        $this->manager->addHumanParticipant($this->sessionData, $userId, $numberOfCards);

        // Assert - Overall state
        $this->assertSame(1, $this->sessionData->maxPlayers);
        $this->assertCount(1, $this->sessionData->participants);
        $this->assertArrayHasKey($userId, $this->sessionData->participants);

        // Assert - Human player details
        $participant = $this->sessionData->participants[$userId];
        $this->assertSame('user', $participant['type']);
        $this->assertSame($numberOfCards, $participant['numberOfCards']);
        $this->assertIsInt($participant['joinedAt']);
        $this->assertGreaterThan(0, $participant['joinedAt']);

        // Assert - Card assignment
        $this->assertArrayHasKey($userId, $this->sessionData->playerCards);
        $this->assertEquals([0, 1, 2, 3], $this->sessionData->playerCards[$userId]);
    }

    #[Test]
    public function addHumanParticipantCorrectlyAssignsCardsInExistingSession(): void {
        // Arrange - Pre-populate the session with an AI player holding 2 cards
        $this->manager->addAIParticipants($this->sessionData, 1, [2]);
        $this->assertSame(1, $this->sessionData->maxPlayers, "Pre-condition failed: AI not added.");

        // Act - Add the human player
        $humanId = 'human-player-2';
        $numberOfCards = 3;
        $this->manager->addHumanParticipant($this->sessionData, $humanId, $numberOfCards);

        // Assert - Final state
        $this->assertSame(2, $this->sessionData->maxPlayers);
        $this->assertCount(2, $this->sessionData->participants);

        // Assert - Human player details
        $this->assertArrayHasKey($humanId, $this->sessionData->participants);
        $this->assertSame(3, $this->sessionData->participants[$humanId]['numberOfCards']);
        $this->assertSame('user', $this->sessionData->participants[$humanId]['type']);

        // Assert - CRITICAL: Card indices start after the AI's cards
        $this->assertEquals([2, 3, 4], $this->sessionData->playerCards[$humanId]);
    }

    #[Test]
    public function handlesMixedAdditionOfParticipantsCorrectly(): void  {
        // Arrange & Act - Step 1: Add a human player
        $human1_id = 'human-1';
        $this->manager->addHumanParticipant($this->sessionData, $human1_id, 2);

        // Arrange & Act - Step 2: Add AI players
        $this->manager->addAIParticipants($this->sessionData, 2, [1, 3]);

        // Arrange & Act - Step 3: Add another human player
        $human2_id = 'human-2';
        $this->manager->addHumanParticipant($this->sessionData, $human2_id, 4);

        // Assert - Final state
        $this->assertSame(4, $this->sessionData->maxPlayers);
        $this->assertCount(4, $this->sessionData->participants);

        // Assert - Verify card assignments for all participants
        $aiIds = array_keys(array_filter(
            $this->sessionData->participants, 
            fn($p) => $p['type'] === 'ai'
        ));
        $ai1_id = $aiIds[0];
        $ai2_id = $aiIds[1];

        $this->assertEquals([0, 1], $this->sessionData->playerCards[$human1_id], "Human 1 cards incorrect.");
        $this->assertEquals([2], $this->sessionData->playerCards[$ai1_id], "AI 1 cards incorrect.");
        $this->assertEquals([3, 4, 5], $this->sessionData->playerCards[$ai2_id], "AI 2 cards incorrect.");
        $this->assertEquals([6, 7, 8, 9], $this->sessionData->playerCards[$human2_id], "Human 2 cards incorrect.");
    }

    #[Test]
    public function addMultipleHumanParticipantsCorrectly(): void  {
        // Arrange & Act
        $user1 = 'user-1';
        $user2 = 'user-2';
        $user3 = 'user-3';
        
        $this->manager->addHumanParticipant($this->sessionData, $user1, 2);
        $this->manager->addHumanParticipant($this->sessionData, $user2, 3);
        $this->manager->addHumanParticipant($this->sessionData, $user3, 1);

        // Assert
        $this->assertSame(3, $this->sessionData->maxPlayers);
        $this->assertCount(3, $this->sessionData->participants);
        
        // Verify all are users
        foreach ($this->sessionData->participants as $participant) {
            $this->assertSame('user', $participant['type']);
        }
        
        // Verify card assignments
        $this->assertEquals([0, 1], $this->sessionData->playerCards[$user1]);
        $this->assertEquals([2, 3, 4], $this->sessionData->playerCards[$user2]);
        $this->assertEquals([5], $this->sessionData->playerCards[$user3]);
    }

    #[Test]
    public function participantsHaveUniqueIds(): void  {
        // Arrange & Act
        $this->manager->addAIParticipants($this->sessionData, 5, [1, 1, 1, 1, 1]);

        // Assert
        $aiIds = array_keys($this->sessionData->participants);
        $uniqueIds = array_unique($aiIds);
        
        $this->assertCount(5, $aiIds);
        $this->assertCount(5, $uniqueIds, "AI IDs must be unique");
        
        // Verify each ID starts with 'AI_' prefix
        foreach ($aiIds as $id) {
            $this->assertStringStartsWith('AI_', $id);
        }
    }

    #[Test]
    public function cardIndicesAreSequentialAndNonOverlapping(): void  {
        // Arrange & Act - Add participants with various card counts
        $this->manager->addAIParticipants($this->sessionData, 2, [3, 2]);
        $this->manager->addHumanParticipant($this->sessionData, 'user-1', 4);

        // Assert - Collect all assigned card indices
        $allCardIndices = [];
        foreach ($this->sessionData->playerCards as $cards) {
            $allCardIndices = array_merge($allCardIndices, $cards);
        }
        
        // Verify indices are sequential from 0 to 8 (3+2+4-1)
        sort($allCardIndices);
        $this->assertEquals(range(0, 8), $allCardIndices, "Card indices must be sequential and non-overlapping");
    }

    #[Test]
    public function joinedAtTimestampIsSetForAllParticipants(): void  {
        // Arrange
        $beforeTime = time();
        
        // Act
        $this->manager->addAIParticipants($this->sessionData, 1, [2]);
        $this->manager->addHumanParticipant($this->sessionData, 'user-1', 3);
        
        $afterTime = time();

        // Assert
        foreach ($this->sessionData->participants as $participant) {
            $this->assertArrayHasKey('joinedAt', $participant);
            $this->assertIsInt($participant['joinedAt']);
            $this->assertGreaterThanOrEqual($beforeTime, $participant['joinedAt']);
            $this->assertLessThanOrEqual($afterTime, $participant['joinedAt']);
        }
    }
}