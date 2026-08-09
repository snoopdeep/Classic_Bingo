<?php

namespace App\Services;

use App\Enums\GameMode;
use App\Resources\GameSessionData;
use App\Core\SessionManager; 
use App\Handlers\AppException; 
use App\Enums\ErrorCode; 
use App\Validators\GameValidator;
use App\Utils\BingoGenerator;
use App\Utils\Logger;
use App\Constants\GameEntitiesConstants;
use App\Constants\ErrorResponseKeys;
use App\Constants\UserDataKeys;
use App\Models\Wallet;
use App\Models\UserStat;      
use App\Models\GameResult;    
use App\Database\Database;    
use Exception;

/**
 * GameService - Orchestrates all game-related business logic.
 */

class GameService{

    // CONST 
    /**
     * @var int The grace period (in seconds) allowed for a user's bingo claim before a pending AI claim is considered a draw.
     */
    private const BINGO_USER_AI_DRAW_GRACE_PERIOD_SECONDS = 1; // 1 second grace period for a draw

    /**
     * @var SessionManager The utility class for managing game session state and persistence (Redis/Cache).
     */
    private SessionManager $sessionManager; 

    /**
     * @var BingoGenerator The utility class responsible for creating new game elements (e.g., Bingo cards, number sequence).
     */
    private BingoGenerator $bingoGenerator;

    /**
     * @var AIPlayer The handler for managing AI opponent actions within the game.
     */
    private AIPlayer $aiPlayer;

    /**
     * @var Wallet The model for interacting with the user_wallet table. 
     */
    private Wallet $walletModel;

    /**
     * @var UserStat The model for interacting with the user_stat table. 
     */
    private UserStat $userStatModel;  
    
    /**
     * @var GameResult The model for recording game outcomes in the game_result table. 
     */
    private GameResult $gameResultModel; 

    /**
     * @var Database The database wrapper used for managing atomic transactions. 
     */
    private Database $dbAccess;     
    
    // REAL TIME MULTIPLAYER MODE CONSTANTS

    /**
     * @var string The key used for the Redis set that tracks active multiplayer sessions awaiting players.
     */
    private const QUEUE_KEY = 'multiplayer:queue'; 

    

    /**
     * GameService constructor.
     * 
    * @param SessionManager $sessionManager The utility class for managing game session state.
     * @param BingoGenerator $bingoGenerator The utility class for creating new game elements.
     * @param AIPlayer $aiPlayer The handler for managing AI opponent actions.
     * @param Wallet $walletModel The model for interacting with the user_wallet table. 
     * @param UserStat $userStatModel The model for interacting with the user_stat table. 
     * @param GameResult $gameResultModel The model for recording game outcomes. 
     * @param Database $db The database wrapper for transaction control.
     */
    public function __construct(
        SessionManager $sessionManager, 
        BingoGenerator $bingoGenerator, 
        AIPlayer $aiPlayer,
        Wallet $walletModel,
        UserStat $userStatModel,      
        GameResult $gameResultModel,  
        Database $db) {

        $this->sessionManager = $sessionManager;
        $this->bingoGenerator = $bingoGenerator;
        $this->aiPlayer = $aiPlayer;
        $this->walletModel = $walletModel;
        $this->userStatModel = $userStatModel;      
        $this->gameResultModel = $gameResultModel;  
        $this->dbAccess = $db;                            
    }


    /**
     * Orchestrates starting a new game session. (Solo, VS_AI, Practice).
     *
     * @param array $requestData The validated request body data, containing game setup parameters (e.g., gameMode, cardCount).
     * @param string $userId The unique identifier of the user initiating the game.
     * @return array {data: array<string, mixed>, status: int} The structured HTTP response array, including the new session ID and initial session data.
     * @throws AppException If validation fails or an infrastructure/unexpected error occurs during session creation.
     */
public function startNewGame(array $requestData, string $userId): array {
       
        // Step 1: Input Validation
        GameValidator::validateStartGameInput($requestData);

        try {

            // 2. Core Logic: Create the session via SessionManager
            /** @var GameSessionData $sessionData */
            [$sessionId, $sessionData] = $this->sessionManager->createSession($requestData, $userId);

            // 3. Handle Entry Cost (Can throw GAME_INSUFFICIENT_FUNDS AppException)
            $this->handleEntryCostAndDeduction($sessionId, $sessionData, $userId);

        } catch (AppException $e) {
            // Log and re-throw known application errors (e.g., GAME_INSUFFICIENT_FUNDS)
            Logger::error("Game start failed with a controlled exception", [
                ErrorResponseKeys::ERROR_CODE => $e->errorCode->value, 
                UserDataKeys::USER_ID => $userId
            ]);
        throw $e;
        } catch (Exception $e) {
            // Wrap and log unexpected errors
            Logger::error("Game start failed during service execution", [
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage(),
            UserDataKeys::USER_ID => $userId
        ]);
        // Throw INFRA_UNEXPECTED_ERROR, which is caught by the global handler
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
        }

        // 3. Log Success & Format Response 
        Logger::info("Game session created successfully by service", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId
        ]);   


    Logger::info("Game session created and cost deducted successfully according to the game mode type.", [UserDataKeys::SESSION_ID => $sessionId, UserDataKeys::USER_ID => $userId]);
        
        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => true,
                GameEntitiesConstants::DATA => [
                    UserDataKeys::SESSION_ID => $sessionId,
                    GameEntitiesConstants::SESSION_DATA => $sessionData->toLobbyData($userId) // toLobbyData() decide what data to send to client on game init.. 
                ]
            ],
            GameEntitiesConstants::STATUS=> 201
        ];
    }

/**
 * Finalizes the game session: calculates rewards/stats and commits them in an atomic transaction.
 *
 * @param string $sessionId The ID of the session to complete.
 * @param string $userId The ID of the user whose result is being processed.
 * @return array{data: array<string, mixed>, status: int} A standardized success response.
 * @throws AppException On session not found, or any database/transaction failure.
 */
public function processGameCompletion(string $sessionId, string $userId): array {
    
    // 1. Retrieve and Validate Session State
    $sessionData = $this->sessionManager->getSession($sessionId);
    if ($sessionData === null) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }
    
    // Ensure the game is actually over and the user is involved
    if (empty($sessionData->gameEndTime) || !isset($sessionData->participants[$userId])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['reason' => 'Game is not finished or user is not participant.']);
    }
    
    // Handle practice-like modes (practice and solo) 
    // NOTE :: For solo mode we have isPracticeMode set to true.
    if ($sessionData->isPracticeMode) {
        $this->sessionManager->deleteSession($sessionId);

        $modeLabel = ($sessionData->sessionType === GameMode::SOLO->value) ? GameMode::SOLO->value : GameMode::PRACTICE->value;
        
        Logger::info("{$modeLabel} game completed (no stats recorded).", [
            UserDataKeys::SESSION_ID => $sessionId
        ]);

         return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => true,
                GameEntitiesConstants::MESSAGE => "{$modeLabel} game completed. No stats or coins affected.",
            ],
            GameEntitiesConstants::STATUS => 200
        ];
    }
    
    // --- A. CALCULATE REWARDS AND STATS ---
    $isWinner = in_array($userId, array_column($sessionData->winners, UserDataKeys::USER_ID));
    $result = $isWinner ? GameEntitiesConstants::WIN : GameEntitiesConstants::LOSS;
    
    $coinsPayout = $isWinner ? $sessionData->pricePool : 0;
    $diceEarned = $isWinner ? 1 : 0;
    $durationSeconds = $sessionData->gameEndTime - $sessionData->startedAt;
    
    // Fetch Current Stats for Win Streak Calculation
    /** @var array|false $currentStats */
    $currentStats = $this->userStatModel->getStat($userId);
    if ($currentStats === null) {
        Logger::error("Game completion failed: User stat record not found.", 
            [UserDataKeys::USER_ID => $userId]);
        throw new AppException(ErrorCode::RESOURCE_USER_NOT_FOUND);
    }
    
    // Calculate new stat values
    $newStreaks = $this->calculateWinStreaks($currentStats, $result);
    $newTotalWins = ($result === GameEntitiesConstants::WIN) ? ((int)$currentStats[GameEntitiesConstants::TOTAL_WINS] + 1) : (int)$currentStats[GameEntitiesConstants::TOTAL_WINS];
    $newTotalLosses = ($result === GameEntitiesConstants::LOSS) ? ((int)$currentStats[GameEntitiesConstants::TOTAL_LOSSES] + 1) : (int)$currentStats[GameEntitiesConstants::TOTAL_LOSSES];
    
    // --- B. EXECUTE ATOMIC DATABASE TRANSACTION ---
    try {
        // Use the Database wrapper for transaction control
        $this->dbAccess->beginTransaction();
        
        // 1. Record Result (INSERT into game_result)
        $this->gameResultModel->insertResult(
            $sessionId, 
            $userId, 
            $sessionData->sessionType, 
            $result, 
            $coinsPayout, 
            $diceEarned, 
            $durationSeconds
        );
        
        // 2. Update Wallet (UPDATE user_wallet)
        $this->walletModel->updateBalanceAfterGame($userId, $coinsPayout, $diceEarned);
        
        // 3. Update Stats (UPDATE user_stat)
        $this->userStatModel->updateStat(
            $userId,
            $newTotalWins,
            $newTotalLosses,
            $newStreaks[GameEntitiesConstants::CURRENT_WIN_STREAK],
            $newStreaks[GameEntitiesConstants::BEST_WIN_STREAK]
        );
        
        $this->dbAccess->commit();
        
        // 4. Clean up cache after successful transaction
        $this->sessionManager->deleteSession($sessionId);
        
        Logger::info("Game completion transaction succeeded and session cleaned up.", 
            [UserDataKeys::SESSION_ID => $sessionId]);
            
    } catch (AppException $e) {
        // Rollback on AppException (from models)
        $this->dbAccess->rollback();
        Logger::error("Game completion transaction failed, rolling back.", [
            UserDataKeys::SESSION_ID => $sessionId, 
            ErrorResponseKeys::ERROR => $e->getMessage()
        ]);
        throw $e; // Re-throw the AppException
        
    } catch (Exception $e) {
        // Rollback on any unexpected exception
        $this->dbAccess->rollback();
        Logger::error("Unexpected error during game completion transaction.", [
            UserDataKeys::SESSION_ID => $sessionId, 
            ErrorResponseKeys::ERROR => $e->getMessage()
        ]);
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
    }
    
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::MESSAGE => 'Game results recorded and stats updated.',
        ],
        GameEntitiesConstants::STATUS => 200
    ];
}

/**
 * Joins or creates a multiplayer session with automatic matchmaking.
 *
 * @param array $requestData Request data containing session parameters (e.g., number of cards).
 * @param string $userId The ID of the user requesting to join the queue.
 * @return array {data: array<string, mixed>, status: int} A response array containing session details.
 * @throws AppException On validation failure or unexpected infrastructure error.
 */
public function joinMultiplayerQueue(array $requestData, string $userId): array {
    GameValidator::validateMultiplayerQueueInput($requestData);
    
    $cardCount = (int)$requestData[GameEntitiesConstants::NUMBER_OF_CARDS];
    
    try {
        // Find an available session
        $availableSessionId = $this->sessionManager->findAvailableSession();
        Logger::info('Avaible Session Id :: ', [$availableSessionId]);
        
        if ($availableSessionId) {
            // Join existing session
            return $this->addPlayerToMultiplayerSession($availableSessionId, $userId, $cardCount);
        } else {
            // Create new session
            return $this->createMultiplayerSession($userId, $cardCount);
        }
    } catch (AppException $e) {
        // If it's a known AppException (e.g., GAME_SESSION_NOT_FOUND or VALIDATION_GAME_INVALID_REQUEST), simply re-throw it to preserve the original error code and message.
        Logger::error("Multiplayer queue join failed (Controlled Error)", [
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::ERROR => $e->getMessage(),
        ]);
        throw $e; 
    }
     catch (Exception $e) {
        Logger::error("Multiplayer queue join failed", [
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::ERROR => $e->getMessage()
        ]);
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
    }
}

/**
 * Creates a new multiplayer session, initializes the creator, and adds the session to the queue.
 *
 * @param string $userId The ID of the user creating the session.
 * @param int $cardCount The number of cards the user has requested.
 * @return array {data: array<string, mixed>, status: int} A standardized success response array containing session data.
 * @throws AppException On infrastructure error during creation, queueing, or saving.
 */
private function createMultiplayerSession(string $userId, int $cardCount): array {
    // Create session via SessionManager/Factory
    $requestData = [
        GameMode::GAME_MODE->value => GameMode::MULTIPLAYER->value,
        GameEntitiesConstants::NUMBER_OF_CARDS => $cardCount
    ];
    
    // 2. Create the initial session object and save it
    try {
        /** @var GameSessionData $sessionData */
        [$sessionId, $sessionData] = $this->sessionManager->createSession($requestData, $userId);
    } catch (Exception $e) {
        // Handle failure to create the initial session.
        Logger::error("Failed to create initial session object", [
            UserDataKeys::USER_ID => $userId,
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
        ]);
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, ['reason' => 'Session creation failed'], $e);
    }
    
    //4: Add to queue and save final state. 
    try {
        // Add session ID to the matchmaking queue (Redis/Cache operation).
        $this->sessionManager->addToQueue($sessionId);
        
    } catch (Exception $e) {
        // Log the failure to queue or save the fully configured session.
        Logger::error("Failed to queue or save final session data", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::ERROR => $e->getMessage()
        ]);
        // Throw an infrastructure error.
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, ['reason' => 'Session save/queue failed'], $e);
    }
    // 5. Success Logging and Response
    Logger::info("Multiplayer session created", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId
    ]);
    
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::DATA => [
                UserDataKeys::SESSION_ID => $sessionId,
                GameEntitiesConstants::SESSION_DATA => $sessionData->toLobbyData($userId)
            ]
        ],
        GameEntitiesConstants::STATUS => 201
    ];
}

/**
 * Adds a player to existing multiplayer session, performing necessary validation.
 *
 * @param string $sessionId The ID of the session to join.
 * @param string $userId The ID of the user to add.
 * @param int $cardCount The number of cards the user is requesting.
 * @return array {data: array<string, mixed>, status: int} A standardized success response array containing session data.
 * @throws AppException On session not found, validation failure, or infrastructure error.
 */
private function addPlayerToMultiplayerSession(string $sessionId, string $userId, int $cardCount): array {
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);
    Logger::info('addPlayerToMultiplayerSession, sessionData : ', [$sessionData]);
    
    if (!$sessionData) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }
    
    // Validate session state
    if ($sessionData->isActive) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Game already started'
        ]);
    }
    
    if ($sessionData->graceEndTime <= time()) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Session expired'
        ]);
    }
    
    if (count($sessionData->participants) >= $sessionData->maxPlayers) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Session full'
        ]);
    }
    
    if (isset($sessionData->participants[$userId])) {
        Logger::info('Already in session', [$userId]);
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Already in session'
        ]);
    }
    
    // 3. Update Session Data 
    
    // Add player to the participants list.
    $sessionData->participants[$userId] = [
        GameEntitiesConstants::TYPE => GameEntitiesConstants::USER,
        GameEntitiesConstants::NUMBER_OF_CARDS => $cardCount,
        GameEntitiesConstants::JOINED_AT => time()
    ];

    // Generate new cards with CORRECT global cardId
    $playerCards = $this->bingoGenerator->generateBingoCards($cardCount);
    $sessionData->playerCards[$userId] = [];
    
    foreach ($playerCards as $card) {
        $globalCardIndex = count($sessionData->bingoCards);
        // Override the cardId to match global index**
        $card[GameEntitiesConstants::CARD_ID] = $globalCardIndex;
        $sessionData->bingoCards[$globalCardIndex] = $card;
        $sessionData->playerCards[$userId][] = $globalCardIndex;
    }
    
    // 4. Save the updated session data. 
    try {
        $this->sessionManager->saveSession($sessionData, $sessionId);
    } catch (Exception $e) {
        // Log the critical failure to persist the changes.
        Logger::error("Failed to save session after adding player", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
        ]);
        // Throw an infrastructure error, letting the outer logic handle the context.
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
    }
    
    Logger::info("Player joined multiplayer session", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId
    ]);
    
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::DATA => [
                UserDataKeys::SESSION_ID => $sessionId,
                GameEntitiesConstants::SESSION_DATA => $sessionData->toLobbyData($userId)
            ]
        ],
        GameEntitiesConstants::STATUS => 200
    ];
}

/**
 * Gets multiplayer session status with auto-start/auto-delete logic.
 *
 *
 * @param string $sessionId The ID of the session.
 * @param string $userId The ID of the user requesting the status.
 * @return array {data: array<string, mixed>, status: int} A standardized success response array with current session status.
 * @throws AppException On session not found, not a participant, or infrastructure failure.
 */
public function getMultiplayerStatus(string $sessionId, string $userId): array {
    // 1. Initial Data Retrieval
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);
    
    if (!$sessionData) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }
    
    if (!isset($sessionData->participants[$userId])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Not a participant'
        ]);
    }
    
    // 2. Auto-Start/Auto-Delete Business Logic
    $currentTime = time();
    $graceExpired = $sessionData->graceEndTime && $sessionData->graceEndTime <= $currentTime;
    
    // Check if the session is expired but not yet active.
    if ($graceExpired && !$sessionData->isActive) {
        $currentCount = count($sessionData->participants);
        
        // I/O Section (Auto-Start/Cleanup)
        try {
            if ($currentCount >= $sessionData->minPlayers) {

                // Auto-start the game: update data structure.
                $sessionData->isActive = true;
                $sessionData->startedAt = time();
                $sessionData->lastCallTime = time(); 

                // Generate the number sequence 
                $sessionData->numbersToCall = $this->bingoGenerator->generateNumberSequence();
                $sessionData->currentNumberIndex = -1;
                $sessionData->numbersCalledSoFar = [];
                
                // I/O operations to persist changes.
                $this->sessionManager->removeFromQueue($sessionId);
                $this->sessionManager->saveSession($sessionData, $sessionId);
                
                Logger::info("Multiplayer game auto-started", [
                    UserDataKeys::SESSION_ID => $sessionId,
                ]);
            } else {
                // Not enough players, delete session.
                $this->sessionManager->removeFromQueue($sessionId);
                $this->sessionManager->deleteSession($sessionId);
                
                // Throw an expected AppException after cleanup 
                throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
                    'reason' => 'Session expired - not enough players'
                ]);
            }
        } catch (AppException $e) {
            // Re-throw expected business exceptions (like the 'Session expired' one).
            throw $e;
        } catch (Exception $e) {
            // Catch unexpected infrastructure failures during queue removal or session save/delete.
            Logger::error("Infrastructure failure during session auto-start/cleanup", [
                UserDataKeys::SESSION_ID => $sessionId,
                ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
            ]);
            // Throw a general infrastructure error.
            throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
        }
    }
    
    // 3. Final Status Retrieval and Response

    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId); 
    
    // Calculate final status details.
    $currentCount = count($sessionData->participants);
    $timeRemaining = $sessionData->graceEndTime ? max(0, $sessionData->graceEndTime - $currentTime) : 0;
    
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SESSION_ID => $sessionData->sessionId,
                GameEntitiesConstants::PARTICIPANTS => array_keys($sessionData->participants),
                GameEntitiesConstants::CURRENT_COUNT => $currentCount,
                GameEntitiesConstants::MAX_PLAYERS => $sessionData->maxPlayers,
                GameEntitiesConstants::TIME_REMAINING => $timeRemaining,
                GameEntitiesConstants::IS_ACTIVE => $sessionData->isActive
            ]
        ],
        GameEntitiesConstants::STATUS => 200
    ];
}


    /**
     * Orchestrates fetching and calling the next number for a session.
     *
    * @param string $sessionId The unique identifier of the game session to process.
     * @param string $userId The ID of the user requesting the next number (for context).
     * @return array{data: array<string, mixed>, status: int} The structured HTTP response array.
     * @throws AppException If the session is not found or if saving the session fails.
     */
    public function processNextNumberForSession(string $sessionId, string $userId): array {

        // 1. Get the current session state
        /** @var GameSessionData|null $sessionData */
        $sessionData = $this->sessionManager->getSession($sessionId);
        if ($sessionData === null) {
            throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
        }

        // 1.1 Add Interval Check :: The number calling system only allow number to call at interval of $callInterval
        $currentTime = time();
        // Use startedAt if lastCallTime is null (first call)
        $lastEventTime = $sessionData->lastCallTime ?? $sessionData->startedAt; 
        $timeSinceLastCall = $currentTime - $lastEventTime;
        $callInterval = $sessionData->callInterval; // Use the configured interval

        // Special case: If this is the first call (currentNumberIndex = -1), allow it immediately
        $isFirstCall = ($sessionData->currentNumberIndex === -1);

        // If the required interval has NOT passed, stop here and tell the client to wait.
        if (!$isFirstCall && $timeSinceLastCall < $callInterval) {
            // Calculate how much time is left until the next number should be called
            $nextCallIn = $callInterval - $timeSinceLastCall;
            
            // Get the last number called to confirm sync
            $lastCalledNumber = ($sessionData->currentNumberIndex >= 0) 
                                    ? $sessionData->numbersToCall[$sessionData->currentNumberIndex] 
                                    : null;
            
            return [
                GameEntitiesConstants::DATA => [
                    GameEntitiesConstants::SUCCESS => true,
                    GameEntitiesConstants::DATA => [
                        GameEntitiesConstants::CALLED_NUMBERS => $lastCalledNumber !== null ? [$lastCalledNumber] : [], 
                        GameEntitiesConstants::IS_GAME_OVER=> !empty($sessionData->winners),
                        GameEntitiesConstants::NEXT_CALL_IN => $nextCallIn 
                    ]
                ],
                GameEntitiesConstants::STATUS=> 200
            ];
        }

        
        // 3. Apply Core Game Logic (Increment Index)
        $wasNumberCalled = $this->applyCallNextNumberLogic($sessionData); // bool -> increament $currentNumberIndex

        $newlyCalledNumber = null;
        $aiActions = [];        
        // 4. Process all follow-up actions if a number was successfully called
        if ($wasNumberCalled) {
            
            // Get the new number that was just called
            $newlyCalledNumber = $sessionData->numbersToCall[$sessionData->currentNumberIndex];

            // 4.1 Execute mode-specific processing (AI, Practice Auto-Daub, Claim Validation)
            $aiActions = $this->processPostCallActions($sessionData, $userId, $newlyCalledNumber);

            // 4.2 Save the updated state
            try {
                $this->sessionManager->saveSession($sessionData, $sessionId);

                // Logger::info("Called next number and processed AI actions, and save to cache.", [
                //     UserDataKeys::SESSION_ID => $sessionId,
                //     GameEntitiesConstants::NUMBER => $newlyCalledNumber,
                //     GameEntitiesConstants::AI_DAUBED => count($aiActions[GameEntitiesConstants::DAUBED] ?? []),
                //     GameEntitiesConstants::AI_BINGO_CLAIMS => count($aiActions[GameEntitiesConstants::BINGO_CLAIMS] ?? [])
                // ]);

            } catch (Exception $e) {
                Logger::error("Failed to save session after calling next number", [UserDataKeys::SESSION_ID => $sessionId]);
                throw new AppException(ErrorCode::INFRA_CACHE_CONNECTION_FAILED, [], $e);
            }
        }

        $isGameOver = ($sessionData->currentNumberIndex + 1 >= count($sessionData->numbersToCall))
                  || !empty($sessionData->winners);

                  // Build response data
                $responseData = [
                    GameEntitiesConstants::CALLED_NUMBERS => $newlyCalledNumber !== null ? [$newlyCalledNumber] : [],
                    GameEntitiesConstants::IS_GAME_OVER=> $isGameOver,
                    GameEntitiesConstants::WINNER => $isGameOver ? $sessionData->winners : []
                ];

                // Include auto-daub info for practice mode
                if ($sessionData->isPracticeMode && $sessionData->practiceAutoDaub && $newlyCalledNumber !== null) {
                    $autoDaubedCells = $this->getAutoDaubedCells($sessionData, $userId, $newlyCalledNumber);
                    $responseData[GameEntitiesConstants::AUTO_DAUB] = $autoDaubedCells;
                }

                return [
                    GameEntitiesConstants::DATA => [
                        GameEntitiesConstants::SUCCESS => true,
                        GameEntitiesConstants::DATA => $responseData
                    ],
                    GameEntitiesConstants::STATUS=> 200
                ];

}   
    
/**
 *  Processes a player's request to 'dab' a number on their bingo card.
 * 
 * @param string $sessionId   The unique identifier for the game session.
 * @param string $userId      The unique identifier for the player dabbing the number.
 * @param array  $requestData The incoming request data, expected to contain: {dabbedNumber' (int), cardIndex' (int) }
 * @return array {data: array<string, mixed>, status: int} An associative array structured for an HTTP response, containing GameEntitiesConstants::DATA and GameEntitiesConstants::STATUSkeys.
 * @throws AppException If a critical validation error occurs
 */
public function processDaubedNumber(string $sessionId, string $userId, array $requestData): array {
    // Step 1: Validate input 
    GameValidator::validateDabbedNumberInput($requestData);

    // Step 2: Get current session state
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);
    if ($sessionData === null) {
         throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }  

    $dabbedNumber = (int)$requestData[GameEntitiesConstants::DAUBED_NUMBER];
    // $cardIndex = (int)$requestData[GameEntitiesConstants::CARD_INDEX] + $totalNumberOfAICards;
    $cardIndex = (int)$requestData[GameEntitiesConstants::CARD_INDEX]; // Use as-is (no offset for multiplayer)
    
    // Step 3: Verify user is a participant
    if (!isset($sessionData->participants[$userId])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
         ['reason' => 'User is not a participant in this session']);
    }

    // Step 4: Verify card ownership 
    $playerCardIndices = $sessionData->playerCards[$userId] ?? [];
    if (!in_array($cardIndex, $playerCardIndices)) {
        Logger::info(
            'User does not own this card',
            [
                GameEntitiesConstants::PLAYER_CARD_INDICES => $playerCardIndices,
                GameEntitiesConstants::CARD_INDEX => $cardIndex
            ]
        );
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            [
                'reason' => 'User does not own this card',
            ]
        );
    }

    // Step 5: Verify card exists 
    if (!isset($sessionData->bingoCards[$cardIndex])) {
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['reason' => 'Card does not exist in this session']
        );
    }

    // Step 5.5: Verify card index is within valid range for this session
    $totalCards = count($sessionData->bingoCards);
    if ($cardIndex >= $totalCards) {
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['reason' => "Card index {$cardIndex} exceeds total cards {$totalCards} in session"]
        );
    }

    // Step 6: Verify game state 
    if ($sessionData->currentNumberIndex < 0 || $sessionData->currentNumberIndex >= count($sessionData->numbersToCall)) {
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['reason' => 'No number has been called yet']
        );
    }
    // Get the last called number
    $lastCalledNumber = $sessionData->numbersToCall[$sessionData->currentNumberIndex];

    // Step 7: Verify dabbed number matches last called number 
    if ($dabbedNumber !== $lastCalledNumber) {
        Logger::info("Incorrect dab attempt, daubedNumber must be same as lastCalledNumber.", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::CARD_INDEX => $cardIndex,
            GameEntitiesConstants::DAUBED_NUMBER => $dabbedNumber,
            GameEntitiesConstants::LAST_CALLED_NUMBER => $lastCalledNumber
        ]);

        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => false,
                GameEntitiesConstants::MESSAGE=> 'Dabbed number does not match the last called number'
            ],
            GameEntitiesConstants::STATUS=> 400
        ];
    }

    // Step 8: Mark the number on the specific card 
    $indexDaubed = $this->markNumberOnCard($sessionData, $cardIndex, $dabbedNumber);
    
    // Handle edge case responses BEFORE saving
    if ($indexDaubed === -1) {
        Logger::info("Dab rejected: number not on card", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::CARD_INDEX => $cardIndex,
            GameEntitiesConstants::DAUBED_NUMBER => $dabbedNumber
        ]);
        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => false,
                GameEntitiesConstants::MESSAGE=> 'This number is not on your card',
                GameEntitiesConstants::CODE => 'NUMBER_NOT_ON_CARD'
            ],
            GameEntitiesConstants::STATUS=> 400
        ];
    }
    
    if ($indexDaubed === -2) {
        Logger::info("Dab rejected: already daubed", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::CARD_INDEX => $cardIndex,
            GameEntitiesConstants::DAUBED_NUMBER => $dabbedNumber
        ]);
        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => false,
                GameEntitiesConstants::MESSAGE=> 'You already daubed this number on this card',
                GameEntitiesConstants::CODE => 'ALREADY_DAUBED'
            ],
            GameEntitiesConstants::STATUS=> 400
        ];
    }
    
    if ($indexDaubed === -3) {
        Logger::info("Dab rejected: FREE space auto-marked", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::CARD_INDEX => $cardIndex
        ]);
        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => false,
                GameEntitiesConstants::MESSAGE=> 'FREE space is automatically marked',
                GameEntitiesConstants::CODE => 'FREE_SPACE_AUTO_MARKED'
            ],
            GameEntitiesConstants::STATUS=> 400
        ];
    }

    // Step 9: Save to cache
    try {
        $this->sessionManager->saveSession($sessionData, $sessionId);
    } catch (Exception $e) {
        Logger::error("Failed to save session after dabbing number", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::CARD_INDEX => $cardIndex
        ]);
        throw new AppException(ErrorCode::INFRA_CACHE_CONNECTION_FAILED, [], $e);
    }

    Logger::info("Number successfully daubed", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::CARD_INDEX => $cardIndex,
        GameEntitiesConstants::DAUBED_NUMBER => $dabbedNumber,
        GameEntitiesConstants::INDEX_DAUBED => $indexDaubed
    ]);

    return [
        GameEntitiesConstants::STATUS => 200,
        GameEntitiesConstants::DATA => [1]
    ];


}

/**
 * Processes a player's bingo claim and verifies if it's a valid win
 * 
 * @param string $sessionId The game session ID
 * @param string $userId The player claiming bingo
 * @param array $requestData Must contain GameEntitiesConstants::CARD_INDEX
 * @return array {data: array<string, mixed>, status: int} Response with success status and pattern info
 * @throws AppException for authorization/validation errors
 */

public function processBindoClaim(string $sessionId, string $userId, array $requestData): array {

    // Step 1: Validate input
    GameValidator::validateBingoClaimInput($requestData);
        
    // Step 2: Get session
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);
    if ($sessionData === null) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }

    // The cardIndex on the server side will be used directly.
    $cardIndex = (int)$requestData[GameEntitiesConstants::CARD_INDEX]; // Use as-is (no offset for multiplayer)

    
    // Step 3: Verify participant and card ownership 
    if (!isset($sessionData->participants[$userId])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['reason' => 'User is not a participant in this session']);
    }
    
    $playerCardIndices = $sessionData->playerCards[$userId] ?? [];
    if (!in_array($cardIndex, $playerCardIndices)) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['reason' => 'User does not own this card']);
    }
    
    if (!isset($sessionData->bingoCards[$cardIndex])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['reason' => 'Card does not exist in this session']);
    }
    
    // Step 4: Check for bingo
    $card = $sessionData->bingoCards[$cardIndex];
    $bingoResult = $this->bingoGenerator->checkForBingoClaim($card[GameEntitiesConstants::DAUBED], $sessionData->practiceWinPattern); // null [vs_AIMode], standard etc [for practice]... 
    
    // Step 7: If no bingo, return error
    if (!$bingoResult[GameEntitiesConstants::IS_WINNER]) {
        Logger::info("False bingo claim", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
            GameEntitiesConstants::CARD_INDEX => $cardIndex
        ]);
        
        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => false,
                GameEntitiesConstants::MESSAGE=> 'No winning pattern detected on this card',
                GameEntitiesConstants::CODE => 'NO_BINGO'
            ],
            GameEntitiesConstants::STATUS=> 400
        ];
    }
    
    // Step 8: Valid bingo - record the win
    Logger::info("Valid bingo claim", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::CARD_INDEX => $cardIndex,
    ]);

    $userClaimTime = time();

    // 8.1: Record User Winner
     $sessionData->winners[] = [
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::CARD_INDEX => $cardIndex,
        GameEntitiesConstants::TIMESTAMP => $userClaimTime,
        GameEntitiesConstants::TYPE => GameEntitiesConstants::USER
    ];

    // 8.2: Process any pending AI claims to check for a DRAW 
    if ($sessionData->sessionType === GameMode::VS_AI->value && isset($sessionData->pendingAIClaims) && !empty($sessionData->pendingAIClaims)) {
        
         // Loop over pending AI claims
        foreach ($sessionData->pendingAIClaims as $aiClaim) {
            // Check if the AI's delayed claim time is within the user's claim window
            // Use delayedClaimTime to ensure AI had to wait its fairness window
            if ($aiClaim['delayedClaimTime'] <= $userClaimTime + self::BINGO_USER_AI_DRAW_GRACE_PERIOD_SECONDS) {

                // Re-check AI's card 
                $card = $sessionData->bingoCards[$aiClaim[GameEntitiesConstants::CARD_INDEX]];
                $aiBingoResult = $this->bingoGenerator->checkForBingoClaim($card[GameEntitiesConstants::DAUBED]);
                
                if ($aiBingoResult[GameEntitiesConstants::IS_WINNER]) {
                    // Record AI as a winner (DRAW condition)
                    $this->aiPlayer->recordAIWinner($sessionData, $aiClaim);
                    
                    Logger::info("Draw detected - both user and AI have bingo", [
                        UserDataKeys::SESSION_ID => $sessionId,
                        GameEntitiesConstants::USER_CARD_INDEX => $cardIndex,
                        GameEntitiesConstants::AI_CARD_INDEX => $aiClaim[GameEntitiesConstants::CARD_INDEX]
                    ]);
                }
            }
        }
        // Clear all pending AI claims, as the game is now over.
        $sessionData->pendingAIClaims = [];
    }
    
    $sessionData->gameEndTime = time();
    
    // Step 9: Save updated session
    try {
        $this->sessionManager->saveSession($sessionData, $sessionId);
    } catch (Exception $e) {
        Logger::error("Failed to save session after bingo", [UserDataKeys::SESSION_ID => $sessionId]);
        throw new AppException(ErrorCode::INFRA_CACHE_CONNECTION_FAILED, [], $e);
    }
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::CLAIM_VALID => true,
                GameEntitiesConstants::MESSAGE => "BINGO! Claim successfully validated.",
                GameEntitiesConstants::IS_GAME_OVER=> true,
                GameEntitiesConstants::WINNERS => $sessionData->winners
            ]
        ],
        GameEntitiesConstants::STATUS=> 200
    ];
}

/**
 * Fetches the entire session data object for debugging.
 * Used by debug/admin routes.
 * 
 * @param string $sessionId The ID of the session to retrieve.
 * @return array{data: GameSessionData, status: int} The complete session data wrapped in the standard response structure.
 * @throws AppException If the session is not found.
 */
public function getDebugSession(string $sessionId): array {
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);

    if ($sessionData === null) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }

    $response = [
        GameEntitiesConstants::DATA => $sessionData,
        GameEntitiesConstants::STATUS=> 200
    ];

    // --- ADD THIS FOR DEBUGGING ---
    // var_dump($response);
    // die('Stopped in GameService');
    // // --- END DEBUGGING ---

    return $response;
}

/**
 * Overwrites the session data object in the cache for debugging.
 * Used by debug/admin routes.
 * 
 * @param string $sessionId The session to overwrite.
 * @param array $newSessionData The full session data array from the request body.
 * @return array{data: array<string, mixed>, status: int} A success response.
 * @throws AppException If saving the session fails.
 */
public function updateDebugSession(string $sessionId, array $newSessionData): array {

        // 1. Convert the incoming PHP array back into a JSON string.
        $jsonPayload = json_encode($newSessionData);

        // 2. Use your static factory method to create a  GameSessionData instance.
        /** @var GameSessionData $sessionDataObject */
        $sessionDataObject = GameSessionData::fromJson($jsonPayload);

        // 3. Save the correctly typed object to the cache.
        try {
            $this->sessionManager->saveSession($sessionDataObject, $sessionId);
        } catch (Exception $e) {
            Logger::error("Failed to save debug session update", [UserDataKeys::SESSION_ID => $sessionId, 'error' => $e->getMessage()]);
            throw new AppException(ErrorCode::INFRA_CACHE_CONNECTION_FAILED, [], $e);
        }

    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::MESSAGE=> 'Session data updated successfully.'
        ],
        GameEntitiesConstants::STATUS=> 200
    ];
}

/**
 * Creates a new PvP room (host creates lobby)
 *
 * @param array $requestData Request data including game settings (e.g., number of cards).
 * @param string $userId The ID of the user creating the room (the host).
 * @return array {data: array<string, mixed>, status: int} The response array containing session details.
 * @throws AppException If an unexpected error occurs during room creation.
 */
public function createPvPRoom(array $requestData, string $userId): array {

    GameValidator::validatePvPCreateInput($requestData);
    $requestData[GameMode::GAME_MODE->value] = GameMode::PVP->value;

    try {
        // Get the sessionData 
        /** @var GameSessionData $sessionData */
        [$sessionId, $sessionData] = $this->sessionManager->createSession($requestData, $userId);
        
        // Save the newly created session.
        $this->sessionManager->saveSession($sessionData, $sessionId);
        
    } catch (Exception $e) {
        Logger::error("Failed to create PvP room", ['error' => $e->getMessage()]);
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
    }

        Logger::info("PvP room created", [
            UserDataKeys::SESSION_ID => $sessionId,
            GameEntitiesConstants::JOIN_CODE => $sessionData->joinCode
        ]);
        
        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::SUCCESS => true,
                GameEntitiesConstants::DATA => [
                    UserDataKeys::SESSION_ID => $sessionId,
                    GameEntitiesConstants::JOIN_CODE => $sessionData->joinCode,
                    GameEntitiesConstants::SESSION_DATA => $sessionData->toLobbyData($userId)
                ]
            ],
            GameEntitiesConstants::STATUS => 201
        ];
}

/**
 * Joins an existing PvP room using a join code, validates the room state, 
 *
 * @param array $requestData Request data containing the 'joinCode' and 'numberOfCards'.
 * @param string $userId The ID of the user attempting to join the room.
 * @return array {data: array<string, mixed>, status: int} The response array containing the updated session details.
 * @throws AppException If validation fails, the session is not found, or an error occurs during save.
 */
public function joinPvPRoom(array $requestData, string $userId): array {
    GameValidator::validatePvPJoinInput($requestData);
    
    $joinCode = strtoupper(trim($requestData[GameEntitiesConstants::JOIN_CODE]));
    $cardCount = (int)$requestData[GameEntitiesConstants::NUMBER_OF_CARDS];
    
    $sessionId = $this->sessionManager->findSessionByJoinCode($joinCode);
    if (!$sessionId) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Invalid join code'
        ]);
    }
    
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);
    if (!$sessionData) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }
    
    // ROOM STATE VALIDATIONS
    if ($sessionData->isActive) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Game already started'
        ]);
    }
    
    if (count($sessionData->participants) >= $sessionData->maxPlayers) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Room is full'
        ]);
    }
    
    if (isset($sessionData->participants[$userId])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Already in this room'
        ]);
    }
    
    // Add player
    $sessionData->participants[$userId] = [
        GameEntitiesConstants::TYPE => GameEntitiesConstants::USER,
        GameEntitiesConstants::NUMBER_OF_CARDS => $cardCount,
        GameEntitiesConstants::JOINED_AT => time()
    ];
    
    // Generate cards
    $playerCards = $this->bingoGenerator->generateBingoCards($cardCount);
    $sessionData->playerCards[$userId] = [];
    
    foreach ($playerCards as $card) {
        $cardIndex = count($sessionData->bingoCards);
        $sessionData->bingoCards[$cardIndex] = $card;
        $sessionData->playerCards[$userId][] = $cardIndex;
    }
    
    try {
        // 10. Save Session: Persist the updated session data with the new player and their cards.
        $this->sessionManager->saveSession($sessionData, $sessionId);
    } catch (Exception $e) {
        // Handle persistence failure gracefully, logging the error.
        Logger::error("Failed to save session after player join", ['error' => $e->getMessage(), UserDataKeys::SESSION_ID => $sessionId]);
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, ['reason' => 'Could not save game session state.'], $e);
    }
    
    Logger::info("Player joined PvP room", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId
    ]);
    
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::DATA => [
                UserDataKeys::SESSION_ID => $sessionId,
                GameEntitiesConstants::SESSION_DATA => $sessionData->toLobbyData($userId)
            ]
        ],
        GameEntitiesConstants::STATUS => 200
    ];
}

/**
 * Get lobby status (lightweight polling endpoint).
 *
 * @param string $sessionId The ID of the game session/room to check.
 * @param string $userId The ID of the authenticated user requesting the status.
 * @return array {data: array<string, mixed>, status: int} The standardized response array containing the essential lobby status data.
 * @throws AppException If the session is not found or the user is not a participant.
 */
public function getLobbyStatus(string $sessionId, string $userId): array {
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);
    
    if (!$sessionData) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }
    
    if (!isset($sessionData->participants[$userId])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Not a participant'
        ]);
    }
    
    $currentCount = count($sessionData->participants);
    // Determine if the 'Start Game' button should be enabled for the current user (host and min players met).
    $canStart = ($userId === $sessionData->hostUserId) && 
                ($currentCount >= $sessionData->minPlayers);
    
    // 5. Return Response
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::HOST_USER_ID => $sessionData->hostUserId,
                GameEntitiesConstants::PARTICIPANTS => array_keys($sessionData->participants),
                GameEntitiesConstants::CURRENT_COUNT => $currentCount,
                GameEntitiesConstants::MAX_PLAYERS => $sessionData->maxPlayers,
                GameEntitiesConstants::CAN_START => $canStart,
                GameEntitiesConstants::IS_ACTIVE => $sessionData->isActive
            ]
        ],
        GameEntitiesConstants::STATUS => 200
    ];
}

/**
 * Start PvP game (host only).
 *
 * @param string $sessionId The ID of the game session to start.
 * @param string $userId The ID of the user attempting to start the game.
 * @return array {data: array<string, mixed>, status: int} The standardized success response indicating the game has started.
 * @throws AppException If the session is not found, the user is not the host, the player count is too low, or the game is already active.
 */
public function startPvPGame(string $sessionId, string $userId): array {
    /** @var GameSessionData|null $sessionData */
    $sessionData = $this->sessionManager->getSession($sessionId);
    
    if (!$sessionData) {
        throw new AppException(ErrorCode::GAME_SESSION_NOT_FOUND);
    }
    
    if ($sessionData->hostUserId !== $userId) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Only host can start'
        ]);
    }
    
    if (count($sessionData->participants) < $sessionData->minPlayers) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => "Need at least {$sessionData->minPlayers} players"
        ]);
    }
    
    if ($sessionData->isActive) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'Game already started'
        ]);
    }
    
    // Initialize game
    $sessionData->isActive = true;
    $sessionData->startedAt = time();
    $sessionData->numbersToCall = $this->bingoGenerator->generateNumberSequence();
    $sessionData->currentNumberIndex = -1;
    $sessionData->numbersCalledSoFar = [];
    $sessionData->lastCallTime = time();
    
    //Save the fully initialized session data,
    try {
        $this->sessionManager->saveSession($sessionData, $sessionId);
    } catch (Exception $e) {
        // Handle persistence failure gracefully, logging the error.
        Logger::error("Failed to save session upon game start", ['error' => $e->getMessage(), UserDataKeys::SESSION_ID => $sessionId]);
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, ['reason' => 'Could not save game session state after initialization.'], $e);
    }
    
    Logger::info("PvP game started", [
        UserDataKeys::SESSION_ID => $sessionId,
        GameEntitiesConstants::PLAYERS => count($sessionData->participants)
    ]);
    
    return [
        GameEntitiesConstants::DATA => [
            GameEntitiesConstants::SUCCESS => true,
            GameEntitiesConstants::MESSAGE => 1
        ],
        GameEntitiesConstants::STATUS => 200
    ];
}



/**
 * Calculates the new win streak values based on the game result and current stats.
 *
 * @param array<string, mixed> $currentStats Current stats fetched from the user_stat table.
 * @param string $result The game result (GameEntitiesConstants::WIN, GameEntitiesConstants::LOSS, 'tie').
 * @return array{current_win_streak: int, best_win_streak: int} The updated stats subarray: [current_win_streak, best_win_streak]
 */
private function calculateWinStreaks(array $currentStats, string $result): array {
    $currentStreak = (int)$currentStats[GameEntitiesConstants::CURRENT_WIN_STREAK];
    $bestStreak = (int)$currentStats[GameEntitiesConstants::BEST_WIN_STREAK];

    if ($result === GameEntitiesConstants::WIN) {
        $currentStreak++;
        // Update best streak if the current one surpassed it
        if ($currentStreak > $bestStreak) {
            $bestStreak = $currentStreak;
        }
    } else {
        // GameEntitiesConstants::LOSS or 'tie' resets the current streak
        $currentStreak = 0;
    }

    return [
        GameEntitiesConstants::CURRENT_WIN_STREAK => $currentStreak,
        GameEntitiesConstants::BEST_WIN_STREAK => $bestStreak,
    ];
}

/**
 * Calculates the total number of bingo cards held by all AI opponents.
 * * @param array $participants The 'participants' map from GameSessionData.
 * @return int The total number of AI cards.
 */
private function getTotalAICards(GameSessionData $sessionData): int {

    // Multiplayer and PvP modes have NO AI cards
    if ($sessionData->sessionType === GameMode::MULTIPLAYER->value || 
        $sessionData->sessionType === GameMode::PVP->value) {
        return 0;
    }

    // For VS_AI mode, count AI participant cards
    $totalAICards = 0;
    if($sessionData->isPracticeMode)return $totalAICards;
    
    $participants = $sessionData->participants;
    foreach ($participants as $participantId => $data) {
        // AI IDs generally start with a prefix like "AI_", or you can check the 'type' key
        if ($data[GameEntitiesConstants::TYPE] === GameEntitiesConstants::AI) {
            // Add the explicit card count for this AI participant
            $totalAICards += $data[GameEntitiesConstants::NUMBER_OF_CARDS];
        }
    }

    return $totalAICards;
}

/**
 * Marks a number as daubed on a specific bingo card.
 * 
 * @param GameSessionData $sessionData The current session state (passed by reference)
 * @param int $cardIndex The index of the card to mark
 * @param int $dabbedNumber The number to mark (1-75)
 * @return int The 0-based grid index where the number was found (or -ve for edges cases)
 */
private function markNumberOnCard(GameSessionData $sessionData, int $cardIndex, int $dabbedNumber): int {

    $card = &$sessionData->bingoCards[$cardIndex];
    
    // Find the index in the grid where this number is located
    $gridIndex = array_search($dabbedNumber, $card[GameEntitiesConstants::GRID], true);
    
    // Edge Case 1: Number doesn't exist on this card
    if ($gridIndex === false) {
        // Return -1 to indicate number not found on card 
        return -1;
    }

    // Edge Case 2: Double-dab detection - number already marked on this card
    if ($card[GameEntitiesConstants::DAUBED][$gridIndex] === true) {
        // Return -2 for "already daubed"
        return -2;
    }

    // Edge Case 3: Don't mark FREE space (center cell at grid index 12)
    if ($gridIndex === 12 && $card[GameEntitiesConstants::GRID][$gridIndex] === GameEntitiesConstants::FREE) {
        // FREE space is auto-marked, return -3 to indicate it's already free
        return -3;
    }


    // All checks passed - mark the number [ 1 => marked || 0 => unmarked]
    $card[GameEntitiesConstants::DAUBED][$gridIndex] = 1;
    
    // Add to numbersCalledSoFar if not already there (only once per session, not per card)
    if (!in_array($dabbedNumber, $sessionData->numbersCalledSoFar, true)) {
        $sessionData->numbersCalledSoFar[] = $dabbedNumber;
    }
    
    return $gridIndex;
}

    
   /**
     * Applies the core logic of calling the next number (incrementing the index and updating time).
     *
     * @param GameSessionData $sessionData The current state of the game.
     * @return bool TRUE if the state was changed (a number was called), FALSE otherwise.
     */
private function applyCallNextNumberLogic(GameSessionData $sessionData): bool {

    // Boundary condition: all numbers are already called. No change is possible.
    if ($sessionData->currentNumberIndex + 1 >= count($sessionData->numbersToCall)) {
        return false; // The state did not change.
    }

    // For multiplayer mode, ensure game is active before calling numbers
    if ($sessionData->sessionType === GameMode::MULTIPLAYER->value && !$sessionData->isActive) {
        Logger::warning("Attempted to call number before game started", [
            GameEntitiesConstants::SESSION_ID => $sessionData->sessionId,
            GameEntitiesConstants::IS_ACTIVE => $sessionData->isActive
        ]);
        return false;
    }

    // If this is the very first call, mark the game as started, (especially for Solo/AI modes).
    if (!$sessionData->isActive) {
        $sessionData->isActive = true;
        $sessionData->startedAt = time();
    }

    // Move to the next number in the sequence
    $sessionData->currentNumberIndex++;
    
    // Mark the time of the call
    $sessionData->lastCallTime = time();
    
    return true; // The state changed successfully.
    }

/**
 * Handles all mode-specific actions (AI turn, Practice Auto-Daub, Delayed AI Claims) after a number is called.
 * * @param GameSessionData $sessionData The current game state (passed by reference).
 * @param string $userId The ID of the user (for auto-daub scope).
 * @param int $newlyCalledNumber The number that was just drawn.
 * @return array<string, array> An array containing actions taken by AI (e.g., daubs, claims).
 */
private function processPostCallActions(GameSessionData $sessionData, string $userId, int $newlyCalledNumber): array {
    $aiActions = [];

    // --- A. VS_AI Mode Logic: AI Turn ---
    if ($sessionData->sessionType === GameMode::VS_AI->value) {
        $aiActions = $this->aiPlayer->processAITurn($sessionData, $newlyCalledNumber);
        
        if (!empty($aiActions[GameEntitiesConstants::BINGO_CLAIMS])) {
            // Adding the bingo claims to the gameSessionData.. 
            $sessionData->pendingAIClaims = array_merge(
                $sessionData->pendingAIClaims,
                $aiActions[GameEntitiesConstants::BINGO_CLAIMS]
            );
        }
    }
    
    // --- B. Practice Mode Auto-Daub Logic 
    if ($sessionData->isPracticeMode && $sessionData->practiceAutoDaub) {
        // Auto-daub the newly called number on all user cards
        $this->autoDaubUserCards($sessionData, $userId, $newlyCalledNumber);
    }

    // --- C. Delayed AI Claims Processing (Fairness Window) ---
    if ($sessionData->sessionType === GameMode::VS_AI->value && isset($sessionData->pendingAIClaims) && !empty($sessionData->pendingAIClaims)) {

        $validatedClaims = $this->aiPlayer->processDelayedBingoClaims(
            $sessionData,
            $sessionData->pendingAIClaims
        );

        // If Claims are found, record AI as the winner in sessionData->winners[];
        foreach ($validatedClaims as $claim) {
            $this->aiPlayer->recordAIWinner($sessionData, $claim);
        }
        
        // Remove the validatedClaims from the sessionData->pendingAIClaims..  
        $sessionData->pendingAIClaims = array_filter(
            $sessionData->pendingAIClaims,
            fn($claim) => !in_array($claim, $validatedClaims, true)
        );
        
        if ($this->aiPlayer->shouldEndGame($sessionData)) {
            $sessionData->gameEndTime = time();
        }
    }
    
    return $aiActions;
}


/**
* Automatically daubs a number on all user's cards in practice mode.
*
* @param GameSessionData $sessionData The current game state (passed by reference).
* @param string $userId The ID of the user whose cards to daub.
* @param int $number The number to mark.
* @return void
*/
private function autoDaubUserCards(GameSessionData $sessionData, string $userId, int $number): void {
        $userCardIndices = $sessionData->playerCards[$userId] ?? [];
        
        foreach ($userCardIndices as $cardIndex) {
            // Step 8: Mark the number on the specific card 
            $this->markNumberOnCard($sessionData, $cardIndex, $number);
        }
    }

/**
     * Handles all financial logic: checks balance, deducts cost, and performs cleanup
     * (session deletion) if the transaction fails.
     *
     * @param string $sessionId The ID of the session created.
     * @param GameSessionData $sessionData The object containing entryCost and isPracticeMode.
     * @param string $userId The unique identifier of the user.
     * @return void
     * @throws AppException If funds are insufficient or deduction fails.
 */
private function handleEntryCostAndDeduction(string $sessionId, GameSessionData $sessionData, string $userId): void {
    
    // 1. Check if practice mode (skip coin deduction)
    if ($sessionData->isPracticeMode) {
        Logger::info("Practice mode session created (no cost).", [
            UserDataKeys::SESSION_ID => $sessionId, 
            UserDataKeys::USER_ID => $userId
        ]);
        return;
    }

    $entryCost = $sessionData->entryCost;

    // 2. CHECK BALANCE
    /** @var array|false $currentBalance */
    $currentBalance = $this->walletModel->getBalance($userId);
    $currentCoins = $currentBalance['bingo_coins'] ?? 0;

    if ($currentCoins < $entryCost) {
        // Log, clean up the session, and throw the error
        Logger::warning("Game start failed: Insufficient coins.", [UserDataKeys::USER_ID => $userId, 'required' => $entryCost, 'current' => $currentCoins]);
        $this->sessionManager->deleteSession($sessionId);
        throw new AppException(ErrorCode::GAME_INSUFFICIENT_FUNDS, ['required' => $entryCost, 'current' => $currentCoins]);
    }

    // 3. DEDUCT COST
    // Use the single atomic deduction call.
    if (!$this->walletModel->deductEntryCost($userId, $entryCost)) {
        // Safety check: Clean up the session if deduction fails unexpectedly
        Logger::error("Failed to deduct entry cost. Race condition suspected.", [UserDataKeys::USER_ID => $userId, 'cost' => $entryCost]);

        $this->sessionManager->deleteSession($sessionId);
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, ['msg' => 'Failed to deduct entry cost due to race condition or DB error']);
    }
}

/**
 * Gets information about which cells were auto-daubed in practice mode.
 * 
 * @param GameSessionData $sessionData Current session state
 * @param string $userId User whose cards to check
 * @param int $number The number that was daubed
 * @return array<int, array{cardIndex: int, cellIndex: int}> Array of auto-daubed cell info.
 */
private function getAutoDaubedCells(GameSessionData $sessionData, string $userId, int $number): array {
    $autoDaubedCells = [];
    $userCardIndices = $sessionData->playerCards[$userId] ?? [];
    
    foreach ($userCardIndices as $serverCardIndex) {
        $card = $sessionData->bingoCards[$serverCardIndex];
        
        // Find if this number exists on this card
        $cellIndex = array_search($number, $card[GameEntitiesConstants::GRID], true);
        
        // Check if the number was found AND if it is marked (1)
        if ($cellIndex !== false && $card[GameEntitiesConstants::DAUBED][$cellIndex] === 1) {
            // // Convert server card index to client card index (subtract AI cards)
            // $totalAICards = $this->getTotalAICards($sessionData);
            // $clientCardIndex = $serverCardIndex - $totalAICards;

            // NOTE: The calculation of $clientCardIndex using getTotalAICards is only relevant 
            // if AI cards were ever included and indexed before user cards, which shouldn't 
            // happen in Practice/Solo mode (where getTotalAICards returns 0). It's safer to 
            // assume serverCardIndex is the correct client index for these modes.
            $clientCardIndex = $serverCardIndex;
            
            $autoDaubedCells[] = [
                GameEntitiesConstants::CARD_INDEX => $clientCardIndex,
                GameEntitiesConstants::CELL_INDEX => $cellIndex
            ];
        }
    }
    
    return $autoDaubedCells;
}

}