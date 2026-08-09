<?php

namespace App\Services;

use App\Resources\GameSessionData;
use App\Utils\BingoGenerator;
use App\Constants\GameEntitiesConstants;
use App\Constants\UserDataKeys;
use App\Utils\Logger;

/**
 * AIPlayer - Handles all AI opponent behavior and decision-making for VS_AI game modes,
 * 
 */
class AIPlayer {
    
    /**
     * @var int The delay (in seconds) the AI waits before officially processing a bingo claim, 
     * acting as a fairness window to allow the human player to claim first.
     */
    private const BINGO_CLAIM_DELAY_SECONDS = 3;

    /**
     * @var BingoGenerator The utility class used to check for bingo patterns.
     */
    private BingoGenerator $bingoGenerator;
    
    /**
     * AIPlayer constructor.
     *
     * @param BingoGenerator $bingoGenerator The dependency for checking bingo status.
     */
    public function __construct(BingoGenerator $bingoGenerator) {
        $this->bingoGenerator = $bingoGenerator;
    }
    
   /**
     * Processes all AI actions after a number is called: daubing and checking for bingo.
     *  @param GameSessionData $sessionData The current game session state (passed by reference).
     * @param int $calledNumber The number that was just called.
     * @return array{daubed: array<int, array<string, mixed>>, bingoClaims: array<int, array<string, mixed>>} 
     * An array of actions taken: ['daubed' => [...], 'bingoClaims' => [...]].
     */
    public function processAITurn(GameSessionData $sessionData, int $calledNumber): array {
        // Logger::info('Processing AI Turn on Calling getNextNumber... ');
        $actions = [
            GameEntitiesConstants::DAUBED => [],
            GameEntitiesConstants::BINGO_CLAIMS => []
        ];
        
        // Get all AI participants
        $aiParticipants = $this->getAIParticipants($sessionData); // [ 'aiUserId' => [type, numberOfCards, joinedAt], ]

        // Logger::info(message: 'AI PARTICIPANTS :: ', [$aiParticipants]);
        
        if (empty($aiParticipants)) {
            return $actions;
        }
        
        // Process each AI player
        foreach ($aiParticipants as $aiUserId => $aiData) {

            $aiCardIndices = $sessionData->playerCards[$aiUserId] ?? []; // playerCards = ['aiUserId' => [0,1], UserDataKeys::USER_ID => [2]]; => [0,1];
            
            //1: Auto-daub the called number on all AI cards
            foreach ($aiCardIndices as $cardIndex) {
                // Logger::info('$aiCardIndices :: ', $aiCardIndices);
                // Logger::info('Result before AutoDaubed :: ', [$sessionData->bingoCards]);
                $daubResult = $this->autoDaubNumber($sessionData, $cardIndex, $calledNumber); // [GameEntitiesConstants::SUCCESS => bool, GameEntitiesConstants::GRID_INDEX => int|null]
                // Logger::info('Result after AutoDaubed :: ', [[$sessionData->bingoCards], $daubResult]);
                
                // 2. Check for successful daubing
                if ($daubResult[GameEntitiesConstants::SUCCESS]) {
                    Logger::log('Successfull Daubed By AI :: ', GameEntitiesConstants::SUCCESS);
                    $actions[GameEntitiesConstants::DAUBED][] = [
                        UserDataKeys::USER_ID => $aiUserId,
                        GameEntitiesConstants::CARD_INDEX => $cardIndex,
                        GameEntitiesConstants::NUMBER => $calledNumber
                    ];
                    
                    // 3. Check for Bingo
                    if ($this->checkForBingo($sessionData, $cardIndex)) {

                        $actions[GameEntitiesConstants::BINGO_CLAIMS][] = [
                            UserDataKeys::USER_ID => $aiUserId,
                            GameEntitiesConstants::CARD_INDEX => $cardIndex,
                            GameEntitiesConstants::CLAIM_TIME => time(),
                            // Record the time the claim should be processed after the delay window
                            GameEntitiesConstants::DELAYED_CLAIM_TIME => time() + self::BINGO_CLAIM_DELAY_SECONDS // adding buffer time for the user to call bingo. => 3sec.. 
                        ];
                    }
                }
                // Tesing for bingo by manual daubing 
                // if ($this->checkForBingo($sessionData, $cardIndex)) {

                //         $actions['bingoClaims'][] = [
                //             UserDataKeys::USER_ID => $aiUserId,
                //             GameEntitiesConstants::CARD_INDEX => $cardIndex,
                //             GameEntitiesConstants::CLAIM_TIME => time(),
                //             GameEntitiesConstants::DELAYED_CLAIM_TIME => time() + self::BINGO_CLAIM_DELAY_SECONDS // adding buffer time for the user to call bingo. => 3sec.. 
                //         ];
                //     }

            }
        }
        
        return $actions;
    }
    
/**
     * Attempts to automatically mark a number on an AI's card.
     * * @param GameSessionData $sessionData The current session state.
     * @param int $cardIndex The index of the card to check.
     * @param int $number The number to daub.
     * @return array{success: bool, gridIndex: int|null} Result of the daubing attempt.
     */
    private function autoDaubNumber(GameSessionData $sessionData, int $cardIndex, int $number): array {
        // bingoCards = [ ['grid', 'daubed', 'cardId' ], ['grid', 'daubed', 'cardId' ] => The index is the cardIndex itself.

        // No card on the cardIndex
        if (!isset($sessionData->bingoCards[$cardIndex])) {
            return [GameEntitiesConstants::SUCCESS => false, GameEntitiesConstants::GRID_INDEX => null];
        }
        
        // Get the card by reference to modify the session data directly
        $card = &$sessionData->bingoCards[$cardIndex];

        // Logger::info('AI card :: ', $card);
        
        // Find the number in the grid
        $gridIndex = array_search($number, $card[GameEntitiesConstants::GRID], true);
        
        // Number not on this card
        if ($gridIndex === false) {
            return [GameEntitiesConstants::SUCCESS => false, GameEntitiesConstants::GRID_INDEX => null];
        }
        
        // Already daubed (check for marked status, assumed 1)
        if ($card[GameEntitiesConstants::DAUBED][$gridIndex] === 1) {
            return [GameEntitiesConstants::SUCCESS => false, GameEntitiesConstants::GRID_INDEX => $gridIndex];
        }
        
        // Don't daub FREE space (handled automatically)
        if ($gridIndex === 12 && $card[GameEntitiesConstants::GRID][$gridIndex] === GameEntitiesConstants::FREE) {
            return [GameEntitiesConstants::SUCCESS => false, GameEntitiesConstants::GRID_INDEX => $gridIndex];
        }
        
        // Mark the number (1 for marked)
        $card[GameEntitiesConstants::DAUBED][$gridIndex] = 1;
        
        return [GameEntitiesConstants::SUCCESS => true, GameEntitiesConstants::GRID_INDEX => $gridIndex];
    }
    
   /**
     * Checks if an AI card has a winning bingo pattern.
     * * @param GameSessionData $sessionData The current session state.
     * @param int $cardIndex The index of the card to check.
     * @return bool TRUE if a winning pattern is found, FALSE otherwise.
     */
    private function checkForBingo(GameSessionData $sessionData, int $cardIndex): bool {

        if (!isset($sessionData->bingoCards[$cardIndex])) {
            return false;
        }
        
        $card = $sessionData->bingoCards[$cardIndex];
        // The checkForBingoClaim method is assumed to return an array containing an IS_WINNER key.
        $bingoResult = $this->bingoGenerator->checkForBingoClaim($card[GameEntitiesConstants::DAUBED]);
        
        return $bingoResult[GameEntitiesConstants::IS_WINNER] === 1;
    }
    
    /**
     * Processes delayed AI bingo claims, checking which ones are past their delay and still valid.
     * * @param GameSessionData $sessionData The current session state.
     * @param array<int, array<string, mixed>> $pendingClaims Array of pending AI bingo claims.
     * @return array<int, array<string, mixed>> Validated claims that should be recorded as winners.
     */
    public function processDelayedBingoClaims(GameSessionData $sessionData, array $pendingClaims): array {
        $currentTime = time();
        $validatedClaims = [];
        
        foreach ($pendingClaims as $claim) {
            // Check if delay period has passed
            if ($currentTime >= $claim[GameEntitiesConstants::DELAYED_CLAIM_TIME]) {
                // Revalidate the bingo (in case a number was un-daubed or state changed)
                if ($this->checkForBingo($sessionData, $claim[GameEntitiesConstants::CARD_INDEX])) {
                    $validatedClaims[] = $claim;
                    
                    Logger::info("AI bingo claim validated after delay", [
                        UserDataKeys::SESSION_ID => $sessionData->sessionId,
                        UserDataKeys::USER_ID => $claim[UserDataKeys::USER_ID],
                        GameEntitiesConstants::CARD_INDEX => $claim[GameEntitiesConstants::CARD_INDEX]
                    ]);
                }
            }
        }
        
        return $validatedClaims;
    }
    
    /**
     * Records an AI winner in the session data's winner list.
     * * @param GameSessionData $sessionData The current session state (passed by reference).
     * @param array<string, mixed> $claim The AI bingo claim data.
     * @return void
     */
    public function recordAIWinner(GameSessionData $sessionData, array $claim): void {
        $sessionData->winners[] = [
            UserDataKeys::USER_ID => $claim[UserDataKeys::USER_ID],
            GameEntitiesConstants::CARD_INDEX => $claim[GameEntitiesConstants::CARD_INDEX],
            GameEntitiesConstants::TIMESTAMP => $claim[GameEntitiesConstants::CLAIM_TIME],
            GameEntitiesConstants::TYPE => GameEntitiesConstants::AI
        ];
    }
    
    /**
     * Gets all AI participants from the session's participant list.
     * * @param GameSessionData $sessionData The current session state.
     * @return array<string, array<string, mixed>> AI participants in the format [userId => participantData].
     */
    private function getAIParticipants(GameSessionData $sessionData): array {
        // participants = [ 'ai_Id' => [type, numberOfCards, joinedAt], UserDataKeys::USER_ID => [ type, numberOfCards, joinedAt ]];
        return array_filter(
            $sessionData->participants,
            fn($participant) => ($participant[GameEntitiesConstants::TYPE] ?? GameEntitiesConstants::USER) === GameEntitiesConstants::AI
        );
    }
    
    /**
     * Checks if there are any pending AI bingo claims.
     * * @param array<int, array<string, mixed>> $pendingClaims Array of pending AI bingo claims.
     * @return bool TRUE if the array is not empty, FALSE otherwise.
     */
    public function hasPendingClaims(array $pendingClaims): bool {
        return !empty($pendingClaims);
    }
    
    /**
     * Determines if the game should end based on the current winners list.
     * * @param GameSessionData $sessionData The current session state.
     * @return bool TRUE if there is at least one winner recorded, FALSE otherwise.
     */
    public function shouldEndGame(GameSessionData $sessionData): bool {
        // Game ends if there's at least one winner
        return !empty($sessionData->winners);
    }
}