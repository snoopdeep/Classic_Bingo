<?php 
namespace App\Services;

use App\Constants\GameEntitiesConstants;
use App\Resources\GameSessionData;
use App\Utils\UUIDGenerator;

/**
 * ParticipantManager
 * * Handles the business logic for adding, configuring, and managing participants
 * (both human and AI) within a game session object.
 */
class ParticipantManager {

    // CONSTANTS 
    /**
     * @var string Prefix for generating unique AI user IDs.
     */
    private const AI_ = 'AI_';


    /**
     * General method to add all participants (AI and Human) to the session, 
     * generating unique IDs for AIs and assigning sequential card indices.
     *
     * @param GameSessionData $sessionData The current game session state (passed by reference).
     * @param string $userId The unique identifier of the human player.
     * @param int $playerCardCount The number of cards the human player receives.
     * @param int $numAI The number of AI opponents to create.
     * @param array<int, int> $aiCardCounts An indexed array where the value is the card count for each AI.
     * @return void
     */
    public function addParticipants(
        GameSessionData $sessionData, 
        string $userId, 
        int $playerCardCount, 
        int $numAI, 
        array $aiCardCounts
    ): void {
        
        // The next available index in the global bingoCards array.
        $startIndex = 0; // Card indices start at 0
        
        // 1. Add AI Participants
        for ($i = 0; $i < $numAI; $i++) {
            $aiId = self::AI_ . UUIDGenerator::generate();
            $aiCards = $aiCardCounts[$i];
            
            // Add AI to participants list and increment maxPlayers
            $this->addParticipant($sessionData, $aiId, GameEntitiesConstants::AI, $aiCards);

            // Assign a contiguous range of indices for the AI's cards
            $cardIndices = range($startIndex, $startIndex + $aiCards - 1);
            $this->assignCards($sessionData, $aiId, $cardIndices);

            // Update the start index for the next player
            $startIndex += $aiCards;
        }

        // 2. Add Human Participant
        // Add user to participants list and increment maxPlayers
        $this->addParticipant($sessionData, $userId, GameEntitiesConstants::USER, $playerCardCount);

        // Assign a contiguous range of indices for the user's cards
        $cardIndices = range($startIndex, $startIndex + $playerCardCount - 1);
        $this->assignCards($sessionData, $userId, $cardIndices);
    }
    
    /**
     * Internal method to register a participant in the session, updating the participant list 
     * and the total number of max players.
     *
     * @param GameSessionData $sessionData The current game session state (passed by reference).
     * @param string $userId The ID of the participant (human or AI).
     * @param string $type The type of participant (e.g., 'user', 'ai').
     * @param int $numberOfCards The number of cards assigned to this participant.
     * @return void
     */
    private function addParticipant(GameSessionData $sessionData, string $userId, string $type, int $numberOfCards): void {
        $sessionData->participants[$userId] = [
            GameEntitiesConstants::TYPE => $type,
            GameEntitiesConstants::NUMBER_OF_CARDS => $numberOfCards,
            GameEntitiesConstants::JOINED_AT => time()
        ];
        // maxPlayers is incremented for every participant added
        $sessionData->maxPlayers++;
    }

    /**
     * Assigns a set of global card indices to a participant's player card list.
     *
     * @param GameSessionData $sessionData The current game session state (passed by reference).
     * @param string $userId The ID of the participant.
     * @param array<int, int> $cardIndices An array of global index numbers assigned to the player.
     * @return void
     */
    private function assignCards(GameSessionData $sessionData, string $userId, array $cardIndices): void {
        $sessionData->playerCards[$userId] = $cardIndices;
    }

     /**
     * Adds a human participant to a lobby (PvP or Multiplayer) without assigning global card indices.
     * * Card indices will be generated and assigned later when the full session is initialized or joined.
     *
     * @param GameSessionData $sessionData The current game session state (passed by reference).
     * @param string $userId The unique identifier of the human player.
     * @param int $playerCardCount The number of cards the player has requested.
     * @return void
     */
    public function addPvPLobbyParticipant(
        GameSessionData $sessionData, 
        string $userId, 
        int $playerCardCount
    ): void {
        // Adds the participant and updates maxPlayers, but does not assign card indices.
        $this->addParticipant($sessionData, $userId, GameEntitiesConstants::USER, $playerCardCount);
    }
}