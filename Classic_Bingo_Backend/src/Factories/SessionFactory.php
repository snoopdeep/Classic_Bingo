<?php

namespace App\Factories;

use App\Utils\BingoGenerator; 
use App\Services\ParticipantManager;
use App\Services\PricingCalculator;
use App\Resources\GameSessionData;
use App\Handlers\AppException;
use App\Constants\GameEntitiesConstants;
use App\Enums\ErrorCode;
use App\Enums\GameMode;

/**
 * SessionFactory : Responsible for constructing a fully-configured GameSessionData object based on the 
 * requested game mode. It orchestrates the BingoGenerator, ParticipantManager, and PricingCalculator.
 */
class SessionFactory {
    /**
     * @var string The key for retrieving the game mode from request data.
     */
    private const GAME_MODE = 'gameMode';

    /**
     * @var BingoGenerator The dependency for generating game mechanics (cards, number sequence).
     */
    private BingoGenerator $bingoGenerator; 

    /**
     * @var ParticipantManager The dependency for adding and configuring all players (human/AI).
     */
    private ParticipantManager $participantManager; 

    /**
     * @var PricingCalculator The dependency for determining financial aspects (costs, prize pool) and timing.
     */
    private PricingCalculator $pricingCalculator;
    
    /**
     * SessionFactory constructor.
     *
     * @param BingoGenerator $bingoGenerator Dependency for core game element creation.
     * @param ParticipantManager $participantManager Dependency for player configuration.
     * @param PricingCalculator $pricingCalculator Dependency for financial/timing calculations.
     */
    public function __construct(BingoGenerator $bingoGenerator,ParticipantManager $participantManager,PricingCalculator $pricingCalculator) {
        $this->bingoGenerator = $bingoGenerator;
        $this->participantManager = $participantManager;
        $this->pricingCalculator = $pricingCalculator;
    }
    
   /**
     * Creates a complete game session based on request data.
     *
     * @param string $sessionId The unique ID to assign to the new session.
     * @param array<string, mixed> $requestData The validated request body data.
     * @param string $userId The ID of the user initiating the session (the player/host).
     * @return GameSessionData The fully configured session object.
     * @throws AppException If the requested game mode is unsupported.
     */
    public function createSession(string $sessionId, array $requestData, string $userId): GameSessionData {
        
        $gameMode = $requestData[self::GAME_MODE];
        $sessionData = new GameSessionData($sessionId); 
        
        // 1. Configure the sessionData based on different game modes
        switch ($gameMode) {
            case GameMode::VS_AI->value:
                $this->configureVsAISession($sessionData, $requestData, $userId);
                break;
            case GameMode::PRACTICE->value:
                $this->configurePracticeSession($sessionData, $requestData, $userId);
                break; 
            case GameMode::SOLO->value: 
                $this->configureSoloSession($sessionData, $requestData, $userId);
                break;
            case GameMode::PVP->value:
                $this->configurePvPSession($sessionData, $requestData, $userId);
                break;    
            case GameMode::MULTIPLAYER->value:
                $this->configureMultiplayerSession($sessionData, $requestData, $userId);
                break;    
            default:
                throw new AppException(ErrorCode::GAME_MODE_UNSUPPORTED, ['mode' => $gameMode]);
        }
        
        // 2. Generate game elements (cards and number sequence)
        // $this->initializeGameElements($sessionData);

        // Only generate for modes that start immediately (VS_AI/Practice/Solo), ie PvP/Multiplayer generate on start/join.
        if ($gameMode !== GameMode::PVP->value && $gameMode !== GameMode::MULTIPLAYER->value) {
            // This is where cards and number sequence are generated for VS_AI/Practice.
            $this->initializeGameElements($sessionData);
        }
        
        return $sessionData;
    }

    /**
     * Configures a Solo game session (single player, no cost, simple rules).
     *
     * @param GameSessionData $sessionData The session object being configured.
     * @param array<string, mixed> $requestData The request data.
     * @param string $userId The ID of the human player.
     * @return void
     */
    private function configureSoloSession(GameSessionData $sessionData, array $requestData, string $userId): void {
    // Set session type
    $sessionData->sessionType = GameMode::SOLO->value;
    
    // Treated as a practice-like mode (no wallet involvement)
    $sessionData->isPracticeMode = true;
    
    // Extract card count
    $playerCardCount = $requestData[GameEntitiesConstants::NUMBER_OF_CARDS];
    
    // Add single player (0 AI)
    $this->participantManager->addParticipants(
        $sessionData, 
        $userId, 
        $playerCardCount, 
        0,  // No AI opponents
        []  // No AI card counts
    );
    
    // Set timing and cost
    $sessionData->callInterval = $this->pricingCalculator->getCallInterval(GameMode::SOLO->value); 
    // No entry cost, no prize pool
    $sessionData->entryCost = 0;
    $sessionData->pricePool = 0;
    
    // Solo-specific settings (no pattern restriction, no auto-daub)
    $sessionData->practiceWinPattern = null;  // Any bingo pattern wins
    $sessionData->practiceAutoDaub = false;   // Manual daubing only
    $sessionData->practiceBallSpeed = 4000;   // 4 seconds
}
    
/**
     * Configures a VS_AI game session (competitive, cost, AI opponents).
     *
     * @param GameSessionData $sessionData The session object being configured.
     * @param array<string, mixed> $requestData The request data.
     * @param string $userId The ID of the human player.
     * @return void
     */
    private function configureVsAISession(GameSessionData $sessionData, array $requestData, string $userId): void {
        // SET sessionType FIRST ---
        $sessionData->sessionType = $requestData[self::GAME_MODE]; // 'vs_ai'

        // Extract participant card counts
        $numberOfCards = $requestData[GameEntitiesConstants::NUMBER_OF_CARDS];
        $numAI = $requestData[GameEntitiesConstants::NUMBER_OF_AI_OPPONENTS];

        // The last element is the human player's card count
        $playerCardCount = array_pop($numberOfCards); 
        $aiCardCounts = $numberOfCards; // // Remaining elements are AI card counts

        // Add all participants (human and AI)
        $this->participantManager->addParticipants(
            $sessionData, 
            $userId, 
            $playerCardCount, 
            $numAI, 
            $aiCardCounts
        );
        
        // Add participants 
        // $this->participantManager->addAIParticipants($sessionData, $numAI, $numberOfCards);
        // $this->participantManager->addHumanParticipant($sessionData, $userId, end($numberOfCards));
        
        // Calculate pricing
        // $playerCards = end($numberOfCards); // number of player card will be the last element in numberOfCards array. 
        $sessionData->entryCost = $this->pricingCalculator->calculateEntryCost(GameMode::VS_AI->value, $playerCardCount);
        $sessionData->callInterval = $this->pricingCalculator->getCallInterval(GameMode::VS_AI->value); // setting call Interval for the vs_AI mode..

        // Calculate the prize pool based on ALL participants' entry contributions
        $sessionData->pricePool = $this->pricingCalculator->calculatePrizePool($sessionData);
        
    }

    /**
     * Configures a Practice game session (no cost, customized rules, auto-daub option).
     *
     * @param GameSessionData $sessionData The session object being configured.
     * @param array<string, mixed> $requestData The request data.
     * @param string $userId The ID of the human player.
     * @return void
     */
    private function configurePracticeSession(GameSessionData $sessionData, array $requestData, string $userId): void{
        // set session type
        $sessionData->sessionType = $requestData[self::GAME_MODE]; // practice mode
        
        $sessionData->isPracticeMode = true;
        $sessionData->practiceWinPattern = $requestData[GameEntitiesConstants::WINNING_PATTERN];
        $sessionData->practiceAutoDaub = $requestData[GameEntitiesConstants::AUTO_DAUB];

        // Extract the single user card count
        $playerCardCount = $requestData[GameEntitiesConstants::NUMBER_OF_CARDS];

        // Add single human participant (0 AI)
        $this->participantManager->addParticipants(
            $sessionData, 
            $userId, 
            $playerCardCount, 
            0, 
            []
        );

        // Set timing
        $speeds = $this->pricingCalculator->getSpeeds();
        $sessionData->practiceBallSpeed = $speeds[$requestData[GameEntitiesConstants::BALL_SPEED]];
        $sessionData->callInterval = (int)($sessionData->practiceBallSpeed / 1000); // Convert to seconds

        // Practice mode: NO entry cost, NO prize pool
        $sessionData->entryCost = 0;
        $sessionData->pricePool = 0;

        // Single player only
        // $sessionData->maxPlayers is now set by ParticipantManager::addParticipants
        // $sessionData->maxPlayers = 1;

    }

/**
     * Configures a PvP session (lobby creation).
     * * Only registers the host and generates their cards. Other players join later.
     *
     * @param GameSessionData $sessionData The session object being configured.
     * @param array<string, mixed> $requestData The request data.
     * @param string $userId The ID of the host.
     * @return void
    //  * @throws RandomException
     */
    private function configurePvPSession(GameSessionData $sessionData, array $requestData, string $userId): void {
        $sessionData->sessionType = GameMode::PVP->value;
        $sessionData->isActive = false;
        $sessionData->hostUserId = $userId;
        $sessionData->joinCode = $this->generateJoinCode(); // Generate unique join code
        $sessionData->minPlayers = 2; // Min players is 2 for PvP
        $sessionData->maxPlayers = 4; // todo :: Max players configurable via config later
        
        // Get call interval from config
        $sessionData->callInterval = $this->pricingCalculator->getCallInterval(GameMode::PVP->value);
        
        //todo:: No entry cost for PvP 
        $sessionData->entryCost = 0;
        $sessionData->pricePool = 0;
        
        // Add host as first participant (LOBBY state)
        $playerCardCount = $requestData[GameEntitiesConstants::NUMBER_OF_CARDS];
        $this->participantManager->addPvPLobbyParticipant(
            $sessionData,
            $userId,
            $playerCardCount
        );

        // Generate and assign cards for the session host immediately upon creation.
        $this->assignBingoCardsToParticipant($sessionData, $userId, $playerCardCount);
    }

    /**
     * Configures a Multiplayer Matchmaking session (queue creation).
     * * Only registers the host. Other players join via the queue.
     *
     * @param GameSessionData $sessionData The session object being configured.
     * @param array<string, mixed> $requestData The request data.
     * @param string $userId The ID of the host/creator.
     * @return void
     */
    private function configureMultiplayerSession(GameSessionData $sessionData, array $requestData, string $userId): void {
    $sessionData->sessionType = GameMode::MULTIPLAYER->value;
    $sessionData->isActive = false;
    $sessionData->hostUserId = $userId; // First player is designated host
    $sessionData->minPlayers = 2;
    $sessionData->maxPlayers = 5; // todo :: set up from the configuration file
    $sessionData->graceEndTime = time() + 15; // Grace period for joining/auto-start //todo :: set up from the configuration file
    
    // Get call interval from config
    $sessionData->callInterval = $this->pricingCalculator->getCallInterval(GameMode::MULTIPLAYER->value);
    
    // todo::  No entry cost for now
    $sessionData->entryCost = 0;
    $sessionData->pricePool = 0;
    
    // Add first player WITHOUT assigning cards (LOBBY state, cards generated on actual start)
    $playerCardCount = $requestData[GameEntitiesConstants::NUMBER_OF_CARDS];
    $this->participantManager->addPvPLobbyParticipant(
        $sessionData,
        $userId,
        $playerCardCount
    );

    // Generate and assign cards for the session creator (host) immediately upon creation.
    $this->assignBingoCardsToParticipant($sessionData, $userId, $playerCardCount);
}

/**
     * Generates and assigns bingo cards to a specific participant, updating both
     * the global bingoCards array and the participant's playerCards mapping.
     *
     * @param GameSessionData $sessionData The session object being configured (passed by reference).
     * @param string $userId The ID of the participant receiving the cards.
     * @param int $cardCount The number of cards to generate.
     * @return void
     */
private function assignBingoCardsToParticipant(
    GameSessionData $sessionData, 
    string $userId, 
    int $cardCount
): void {
    // Generate cards for the user.
    $playerCards = $this->bingoGenerator->generateBingoCards($cardCount);
    $sessionData->playerCards[$userId] = [];
    
    // Add the new cards to the global list and record their index for the user.
    foreach ($playerCards as $card) {

        $globalCardIndex = count($sessionData->bingoCards);
        
        // Set the cardId to the correct global index
        $card[GameEntitiesConstants::CARD_ID] = $globalCardIndex;
        
        $sessionData->bingoCards[$globalCardIndex] = $card;
        $sessionData->playerCards[$userId][] = $globalCardIndex;
    }
}

/**
     * Generates a 4-character uppercase alphanumeric join code for PvP rooms.
     *
     * @return string The generated join code.
     */
    private function generateJoinCode(): string {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
        
    /**
     * Generates and assigns the master Bingo cards and number sequence for modes that start immediately.
     *
     * @param GameSessionData $sessionData The session object being configured (passed by reference).
     * @return void
     */
    private function initializeGameElements(GameSessionData $sessionData): void {
            // Total cards involves in the session
            $totalCards = array_sum(array_column($sessionData->participants, GameEntitiesConstants::NUMBER_OF_CARDS));

            // Check if cards were already generated (e.g., during lobby creation, but should be handled above)
            if (empty($sessionData->bingoCards)) {
                // Generate and set all the bingo cards
                $sessionData->bingoCards = $this->bingoGenerator->generateBingoCards($totalCards);
            }
            
            // Generate and set the numberToCall
            $sessionData->numbersToCall = $this->bingoGenerator->generateNumberSequence();
            $sessionData->currentNumberIndex = -1;


            // // Generate and set all the bingo cards
            // $sessionData->bingoCards = $this->bingoGenerator->generateBingoCards($totalCards);
            // // Generate and set the numberToCall
            // $sessionData->numbersToCall = $this->bingoGenerator->generateNumberSequence();
            // $sessionData->currentNumberIndex = -1;
        }
}