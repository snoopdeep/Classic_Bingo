<?php

namespace App\Resources;
use JsonException;

use App\Constants\GameSessionConstants;
/**
 * GameSessionData: A pure data structure (DTO) used to hold and manage the complete state 
 * of a single Bingo game session. It includes methods for serialization/deserialization.
 */
class GameSessionData {
    // === Session Identity ===
    /**
     * @var string The unique identifier for the current session.
     */
    public string $sessionId;

    /**
     * @var string The type of game (e.g., 'pvp', 'vs_ai', 'solo', 'practice', 'tournament').
     */
    public string $sessionType; 

    /**
     * @var bool Flag indicating if the number calling system is active.
     */
    public bool $isActive;

    /**
     * @var int Unix timestamp when the session was created.
     */
    public int $createdAt;

    /**
     * @var int|null Unix timestamp when the game officially started.
     */
    public ?int $startedAt;

    /**
     * @var int|null Unix timestamp when the game officially ended.
     */
    public ?int $gameEndTime;

    // === PvP Room Management [Play With Friends] ===

    /**
     * @var string ID of the player who created the room (the Host).
     */
    public string $hostUserId; 

    /**
     * @var string The 4-character code for joining the room.
     */
    public string $joinCode; 

    /**
     * @var int Minimum players required before the host can manually start the game.
     */
    public int $minPlayers; 

    // === Real-Time Multiplayer Mode [AutoSession Matching] ===

    /**
     * @var int|null Unix timestamp when the grace period for joining/auto-start expires.
     */
    public ?int $graceEndTime; 

    // === Practice Mode Configuration ===

    /**
     * @var bool Flag if the game is a no-stakes practice mode.
     */
    public bool $isPracticeMode;

    /**
     * @var string|null The specific pattern required to win in practice mode (e.g., 'standard','four_corners', 'X', etc.).
     */
    public ?string $practiceWinPattern; 

    /**
     * @var bool Flag to automatically mark the user's cards in practice mode.
     */
    public bool $practiceAutoDaub;

    /**
     * @var int The time delay (in milliseconds) between ball calls in practice mode.
     */
    public int $practiceBallSpeed; 

    
    // === Participants & Pricing ===

    /**
     * @var array<string, array<string, mixed>> List of all participants (AI and user). 
     * Format: [userId => ['type' => string, 'numberOfCards' => int, 'joinedAt' => int]]
     */
    public array $participants; 

    /**
     * @var int Maximum number of participants allowed in the session.
     */
    public int $maxPlayers;

    /**
     * @var int Total prize pool collected from entry costs.
     */
    public int $pricePool;

    /**
     * @var int The cost to enter the game for one player (used for checking funds).
     */
    public int $entryCost;
    
    // === Game State & Calling === 

    /**
     * @var array<int, int> The complete, pre-shuffled sequence of numbers to be called (1-75).
     */
    public array $numbersToCall;

    /**
     * @var array<int, int> The numbers that have been daubed by the player so far (subset of $numbersToCall).
     */
    public array $numbersCalledSoFar;

    /**
     * @var int The index in $numbersToCall of the *last* number called (-1 initially).
     */
    public int $currentNumberIndex;

    /**
     * @var int The time interval (in seconds) between number calls.
     */
    public int $callInterval;

    /**
     * @var int|null Unix timestamp of the last time a number was called.
     */
    public ?int $lastCallTime;
    
    // === Cards ===

    /**
     * @var array<int, array<string, mixed>> Master list of all cards in the session (AI and user). 
     * Indexed by global card index.
     */
    public array $bingoCards; // cardIndex => ['grid' => 1x25 1D array, 'daubed' => []]

    /**
     * @var array<string, array<int, int>> Map of participants to the indices of their cards. 
     * Format: [userId => [globalCardIndex1, globalCardIndex2, ...]]
     */
    public array $playerCards;
    
    // === Results & Claims ===

    /**
     * @var array<int, array<string, mixed>> List of all winners recorded.
     */
    public array $winners;

    /**
     * @var array<int, mixed> Historical records of game results.
     */
    public array $gameResults;

    /**
     * @var array<int, array<string, mixed>> List of AI claims waiting for the fairness delay to expire.
     */
    public array $pendingAIClaims;
    
    /**
     * GameSessionData constructor.
     * * Initializes the data structure with minimal required properties and sane defaults.
     *
     * @param string $sessionId The unique identifier for this session.
     */
    public function __construct(string $sessionId) {
        $this->sessionId = $sessionId;
        $this->sessionType = GameSessionConstants::TYPE_SOLO; // solo by default.. 
        $this->isActive = false;
        $this->createdAt = time();
        $this->startedAt = null;
        $this->gameEndTime = null;

        // PvP Defaults
        $this->hostUserId = ''; 
        $this->joinCode = ''; 
        $this->minPlayers = 2; // Default minimum

        // Practice Mode
        $this->isPracticeMode = false;
        $this->practiceWinPattern = null;
        $this->practiceAutoDaub = false;
        $this->practiceBallSpeed = 4000; // default 4 seconds

        // Multiplayer mode
        $this->graceEndTime = null; 
        
        // Participants
        $this->participants = [];
        $this->maxPlayers = 4; // default max player.. 
        $this->pricePool = 0;
        $this->entryCost = 0;
        
        // Number calling system
        $this->numbersToCall = [];
        $this->numbersCalledSoFar = [];
        $this->currentNumberIndex = -1;
        $this->callInterval = 4;
        $this->lastCallTime = null;
        
        // Bingo Cards
        $this->bingoCards = [];
        $this->playerCards = [];
        
        // Winners 
        $this->winners = [];
        $this->gameResults = [];
        $this->pendingAIClaims = [];
    }
    
    /**
     * Serializes the entire data structure into a JSON string for storage (e.g., Redis).
     *
     * @return string The JSON representation of the object state.
     * @throws JsonException
     */
    public function toJson(): string {
        return json_encode([
            GameSessionConstants::KEY_SESSION_ID => $this->sessionId,
            GameSessionConstants::KEY_SESSION_TYPE => $this->sessionType,
            GameSessionConstants::KEY_IS_ACTIVE => $this->isActive,
            GameSessionConstants::KEY_CREATED_AT => $this->createdAt,
            GameSessionConstants::KEY_STARTED_AT => $this->startedAt,
            GameSessionConstants::KEY_GAME_END_TIME => $this->gameEndTime,
            // PvP fields
            GameSessionConstants::HOST_USER_ID => $this->hostUserId,
            GameSessionConstants::JOIN_CODE => $this->joinCode,
            GameSessionConstants::MIN_PLAYERS => $this->minPlayers,

            // RealTime Multiplayer mode
            GameSessionConstants::GRACE_END_TIME => $this->graceEndTime,

            GameSessionConstants::KEY_PARTICIPANTS => $this->participants,
            GameSessionConstants::KEY_MAX_PLAYERS => $this->maxPlayers,
            GameSessionConstants::KEY_PRICE_POOL => $this->pricePool,
            GameSessionConstants::KEY_ENTRY_COST => $this->entryCost,
            GameSessionConstants::KEY_NUMBERS_TO_CALL => $this->numbersToCall,
            GameSessionConstants::KEY_NUMBERS_CALLED_SO_FAR => $this->numbersCalledSoFar,
            GameSessionConstants::KEY_CURRENT_NUMBER_INDEX => $this->currentNumberIndex,
            GameSessionConstants::KEY_CALL_INTERVAL => $this->callInterval,
            GameSessionConstants::KEY_LAST_CALL_TIME => $this->lastCallTime,
            GameSessionConstants::KEY_BINGO_CARDS => $this->bingoCards,
            GameSessionConstants::KEY_PLAYER_CARDS => $this->playerCards,
            GameSessionConstants::KEY_WINNERS => $this->winners,
            GameSessionConstants::KEY_GAME_RESULTS => $this->gameResults,
            GameSessionConstants::KEY_PENDING_AI_CLAIMS => $this->pendingAIClaims,
            GameSessionConstants::KEY_IS_PRACTICE_MODE => $this->isPracticeMode,
            GameSessionConstants::KEY_PRACTICE_WIN_PATTERN => $this->practiceWinPattern,
            GameSessionConstants::KEY_PRACTICE_AUTO_DAUB => $this->practiceAutoDaub,
            GameSessionConstants::KEY_PRACTICE_BALL_SPEED => $this->practiceBallSpeed,
        ], JSON_THROW_ON_ERROR);
      }
    
    /**
     * Deserializes a JSON string to create a GameSessionData instance.
     *
     * @param string $json The JSON string representing the object state.
     * @return self The fully hydrated GameSessionData instance.
     * @throws JsonException
     */
    public static function fromJson(string $json): self {

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

         // Access JSON keys using constants and provide defensive null-coalescing defaults
        $instance = new self($data[GameSessionConstants::KEY_SESSION_ID]);
        $instance->sessionType = $data[GameSessionConstants::KEY_SESSION_TYPE] ?? GameSessionConstants::TYPE_SOLO;
        $instance->isActive = $data[GameSessionConstants::KEY_IS_ACTIVE] ?? false;
        $instance->createdAt = $data[GameSessionConstants::KEY_CREATED_AT] ?? time();
        $instance->startedAt = $data[GameSessionConstants::KEY_STARTED_AT] ?? null;
        $instance->gameEndTime = $data[GameSessionConstants::KEY_GAME_END_TIME] ?? null;

        // PvP fields
        $instance->hostUserId = $data[GameSessionConstants::HOST_USER_ID] ?? '';
        $instance->joinCode = $data[GameSessionConstants::JOIN_CODE] ?? '';
        $instance->minPlayers = $data[GameSessionConstants::MIN_PLAYERS] ?? 2;

        $instance->graceEndTime = $data[GameSessionConstants::GRACE_END_TIME] ?? null;

        $instance->participants = $data[GameSessionConstants::KEY_PARTICIPANTS] ?? [];
        $instance->maxPlayers = $data[GameSessionConstants::KEY_MAX_PLAYERS] ?? 0;
        $instance->pricePool = $data[GameSessionConstants::KEY_PRICE_POOL] ?? 0;
        $instance->entryCost = $data[GameSessionConstants::KEY_ENTRY_COST] ?? 0;
        $instance->numbersToCall = $data[GameSessionConstants::KEY_NUMBERS_TO_CALL] ?? [];
        $instance->numbersCalledSoFar = $data[GameSessionConstants::KEY_NUMBERS_CALLED_SO_FAR] ?? [];
        $instance->currentNumberIndex = $data[GameSessionConstants::KEY_CURRENT_NUMBER_INDEX] ?? -1;
        $instance->callInterval = $data[GameSessionConstants::KEY_CALL_INTERVAL] ?? 4;
        $instance->lastCallTime = $data[GameSessionConstants::KEY_LAST_CALL_TIME] ?? null;
        $instance->bingoCards = $data[GameSessionConstants::KEY_BINGO_CARDS] ?? [];
        $instance->playerCards = $data[GameSessionConstants::KEY_PLAYER_CARDS] ?? [];
        $instance->winners = $data[GameSessionConstants::KEY_WINNERS] ?? [];
        $instance->gameResults = $data[GameSessionConstants::KEY_GAME_RESULTS] ?? [];
        $instance->pendingAIClaims = $data[GameSessionConstants::KEY_PENDING_AI_CLAIMS] ?? [];
        $instance->isPracticeMode = $data[GameSessionConstants::KEY_IS_PRACTICE_MODE] ?? false;
        $instance->practiceWinPattern = $data[GameSessionConstants::KEY_PRACTICE_WIN_PATTERN] ?? null;
        $instance->practiceAutoDaub = $data[GameSessionConstants::KEY_PRACTICE_AUTO_DAUB] ?? false;
        $instance->practiceBallSpeed = $data[GameSessionConstants::KEY_PRACTICE_BALL_SPEED] ?? 4000;
        
        return $instance;
    }

    /**
         * Prepares data for client side on game initiation, exposing only public and player-specific data.
         *
         * @param string $userId The ID of the player requesting the lobby data.
         * @return array The filtered session data suitable for the client.
     */
   public function toLobbyData(string $userId): array {

    // 1. Get the card indices owned by the current user
        $playerCardIndices = $this->playerCards[$userId] ?? [];
        $filteredBingoCards = [];

        // 2. Filter the master bingoCards array to only include the player's cards
        foreach ($playerCardIndices as $cardIndex) {
            // Ensure the card exists before adding
            if (isset($this->bingoCards[$cardIndex])) {
                // We keep the original index (0, 1, 2...) for the client to reference the card.
                // $filteredBingoCards[$cardIndex] = $this->bingoCards[$cardIndex]; 
                $filteredBingoCards[] = $this->bingoCards[$cardIndex];
            }
        }

    return [
        // 'sessionId' => $this->sessionId,
        // 'sessionType' => $this->sessionType,
        // 'isActive' => $this->isActive,
        // 'createdAt' => $this->createdAt,             
        // 'startedAt' => $this->startedAt,             
        // 'lastCallTime' => $this->lastCallTime,       
        // 'gameEndTime' => $this->gameEndTime,         
        // 'maxPlayers' => $this->maxPlayers,
        // 'entryCost' => $this->entryCost,
        // 'pricePool' => $this->pricePool,
        // 'participants' => $this->participants,
        // 'playerCards' => $this->playerCards,
        // 'numbersToCall' => $this->numbersToCall,
        // 'callInterval' => $this->callInterval,
        // 'bingoCards' =>  $filteredBingoCards, 
        // 'isPracticeMode' => $this->isPracticeMode,
        // 'practiceAutoDaub' => $this->practiceAutoDaub ?? false,
        // 'practiceBallSpeed' => $this->practiceBallSpeed ?? 4000,
        GameSessionConstants::KEY_BINGO_CARDS =>  $filteredBingoCards, 
        GameSessionConstants::KEY_CALL_INTERVAL => $this->callInterval,
        GameSessionConstants::KEY_IS_PRACTICE_MODE => $this->isPracticeMode,
        GameSessionConstants::KEY_PRACTICE_AUTO_DAUB => $this->practiceAutoDaub ?? false,
        GameSessionConstants::KEY_PRACTICE_BALL_SPEED => $this->practiceBallSpeed ?? 4000,

        // Data needed for synchronization during PvP gameplay
        GameSessionConstants::HOST_USER_ID => $this->hostUserId,
        // GameSessionConstants::JOIN_CODE => $this->joinCode,
        GameSessionConstants::KEY_IS_ACTIVE => $this->isActive, // Critical for knowing when the game starts
        GameSessionConstants::KEY_NUMBERS_CALLED_SO_FAR => $this->numbersCalledSoFar, // To catch up on missed numbers
        GameSessionConstants::KEY_CURRENT_NUMBER_INDEX => $this->currentNumberIndex,
        GameSessionConstants::KEY_WINNERS => $this->winners, // To see who won
        GameSessionConstants::KEY_PARTICIPANTS => array_keys($this->participants), // Simple list of who is in the room
        GameSessionConstants::KEY_MAX_PLAYERS => $this->maxPlayers,
    ];
}
}