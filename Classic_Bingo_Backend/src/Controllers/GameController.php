<?php

namespace App\Controllers;

use App\Core\Request;
use App\Utils\Logger;
use App\Services\GameService;
use App\Constants\UserDataKeys;
use App\Constants\GameEntitiesConstants;
use App\Enums\GameMode;
/**
 * Controller responsible for managing game-related API endpoints.
 * It uses GameService to handle the business logic.
 */
class GameController {

    // Define the public "menu" of handler methods as constants
    public const METHOD_START = 'start';
    public const METHOD_GET_NEXT_NUMBER = 'getNextNumber';
    public const METHOD_DAUBED_NUMBER = 'daubedNumber';
    public const METHOD_CHECK_BINGO = 'checkBingo';
    public const METHOD_GET_GAME_SESSION_DATA = 'getGameSessionData';
    public const METHOD_UPDATE_GAME_SESSION_DATA = 'updateGameSessionData';
    public const METHOD_COMPLETE_GAME = 'completeGame';
    public const METHOD_CREATE_PVP_ROOM = 'createPvPRoom';
    public const METHOD_JOIN_PVP_ROOM = 'joinPvPRoom';
    public const METHOD_GET_LOBBY_STATUS = 'getLobbyStatus';
    public const METHOD_START_PVP_GAME = 'startPvPGame';    
    public const METHOD_JOIN_MULTIPLAYER_QUEUE = 'joinMultiplayerQueue';
    public const METHOD_GET_MULTIPLAYER_STATUS = 'getMultiplayerStatus';

    /**
     * @var string Placeholder for unknown or missing request data.
     */
    public const UNKNOWN = 'unknown';


    /**
     * @var GameService The service layer dependency for game operations.
     */
    private GameService $gameService; 
    
    /**
     * GameController constructor.
     * * @param GameService $gameService The service layer dependency for game operations.
     */
    public function __construct(GameService $gameService) {
        $this->gameService = $gameService;
    }
    
    /**
     * Handles the HTTP request to start a new game for the authenticated user.
     *
     * @param Request $request The incoming HTTP request object, expected to contain user auth and game details.
     * @return array The result of the game start operation, typically including the new game's ID and state.
     */
    public function start(Request $request): array {
        // 1. Get inputs from the request
        /** @var string $userId */
        $userId = $request->getAuthUser()->sub;
        $requestData = $request->getBody();

        // 2. Log the attempt 
         Logger::info("Game start request received by controller", [
            // 'requestData :: req.body() : ', $requestData,
            UserDataKeys::USER_ID => $userId,
            GameMode::GAME_MODE->value => $requestData[GameMode::GAME_MODE->value] ?? self::UNKNOWN
        ]);
        
        // 3. DELEGATE to the service and return its result 
        return $this->gameService->startNewGame($requestData, $userId);
    }

   /**
     * Handles the HTTP request to get the next number for an active game session.
     * 
     * @param Request $request The incoming HTTP request object. 
     * @param string $sessionId The unique identifier for the current game session, extracted from the route.
     * @return array The result of the operation, typically containing the next number and updated session state.
     */
    public function getNextNumber(Request $request, string $sessionId): array {
        // Logging the initial request is fine for an entry point.
        // Logger::info("Controller received request for next number", ['sessionId' => $sessionId]);

        $userId = $request->getAuthUser()->sub;
        // DELEGATE to the service and return its result 
        return $this->gameService->processNextNumberForSession($sessionId, $userId);
    }
    
 /**
 * Handles a player's number dabbing action (marking a number on their card).
 *
 * @param Request $request The incoming HTTP request object, containing the authenticated user and request body.
 * @param string $sessionId The unique identifier of the active game session.
 * @return array The API response array structure, confirming success or providing feedback.
 */
public function daubedNumber(Request $request, string $sessionId): array {
    
    // Get the user ID.
    /** @var string $userId */
    $userId = $request->getAuthUser()->sub;
    
    // Get the request payload (expected to contain 'dabbedNumber' and 'cardIndex').
    $requestData = $request->getBody();

    // Log the request details..
    Logger::info("Controller received dab number request", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::DAUBED_NUMBER => $requestData[GameEntitiesConstants::DAUBED_NUMBER] ?? self::UNKNOWN,
        GameEntitiesConstants::CARD_INDEX => $requestData[GameEntitiesConstants::CARD_INDEX] ?? self::UNKNOWN
    ]);
    
    // Delegate the process to the GameService.
    return $this->gameService->processDaubedNumber($sessionId, $userId, $requestData);
}


/**
 * Handles the player's request to claim Bingo on a card.
 *
 * @param Request $request The incoming HTTP request object, containing the authenticated user and request body.
 * @param string $sessionId The unique identifier of the active game session, extracted from the route.
 * @return array{data: mixed, status: int} The API response array structure (containing data and status), as returned by GameService.
 */
public function checkBingo(Request $request, string $sessionId): array {

    // Extract the user ID..
    /** @var string $userId */
    $userId = $request->getAuthUser()->sub;
    
    // Get the JSON request body 
    $requestData = $request->getBody();
    
    // Log the initiation of the bingo claim process..
    Logger::info("Controller received bingo claim", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::CARD_INDEX => $requestData[GameEntitiesConstants::CARD_INDEX] ?? self::UNKNOWN 
    ]);
    
    // Delegate process to the GameService
    return $this->gameService->processBindoClaim($sessionId, $userId, $requestData);
}

/**
     * Handles the HTTP request to finalize a game session, record results, and update stats.
     *
     * @param Request $request The incoming HTTP request object.
     * @param string $sessionId The unique identifier for the completed game session.
     * @return array array{data: mixed, status: int} The API response array structure, confirming the successful completion.
     */
    public function completeGame(Request $request, string $sessionId): array {
        /** @var string $userId */
        $userId = $request->getAuthUser()->sub;

        Logger::info("Controller received game completion request", [
            UserDataKeys::SESSION_ID => $sessionId,
            UserDataKeys::USER_ID => $userId,
        ]);

        // Delegate work to the GameService.
        return $this->gameService->processGameCompletion($sessionId, $userId);
    }

    /**
     * Handles getting the full game session data for debugging purposes.
     * * @param Request $request The incoming HTTP request object.
     * @param string $sessionId The ID of the session to retrieve.
     * @return array{data: mixed, status: int} The session data.
     */
    public function getGameSessionData(Request $request, string $sessionId) : array {
        return $this->gameService->getDebugSession( $sessionId);
    }    

    /**
     * Handles overwriting/modifying session data for debugging purposes.
     * * @param Request $request The incoming HTTP request object, containing the new session data in the body.
     * @param string $sessionId The ID of the session to update.
     * @return array{data: mixed, status: int} The confirmation of the update.
     */
    public function updateGameSessionData(Request $request, string $sessionId): array {
        $newSessionData = $request->getBody();
        return $this->gameService->updateDebugSession($sessionId, $newSessionData);
    }

/**
 * Controller endpoint to handle the request for creating a new PvP room (lobby).
 *
 * @param Request $request The incoming HTTP request object, containing headers and body.
 * @return array {data: mixed, status: int} The standardized response array containing the session details.
 */
public function createPvPRoom(Request $request): array {
    /** @var string $userId */
    $userId = $request->getAuthUser()->sub;
    $requestData = $request->getBody();
    
    Logger::info("PvP room creation", [
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::NUMBER_OF_CARDS => $requestData[GameEntitiesConstants::NUMBER_OF_CARDS] ?? self::UNKNOWN
    ]);
    
    return $this->gameService->createPvPRoom($requestData, $userId);
}

/**
 * Controller endpoint to handle the request for a user to join an existing PvP room (lobby).
 *
 * @param Request $request The incoming HTTP request object, containing headers and body.
 * @return array {data: mixed, status: int} The standardized response array containing the updated session details.
 */
public function joinPvPRoom(Request $request): array {
    /** @var string $userId */
    $userId = $request->getAuthUser()->sub;
    $requestData = $request->getBody();
    
    Logger::info("PvP room join", [
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::JOIN_CODE => $requestData[GameEntitiesConstants::JOIN_CODE] ?? self::UNKNOWN
    ]);
    
    return $this->gameService->joinPvPRoom($requestData, $userId);
}

/**
 * Controller endpoint to retrieve the current status or 'view' of a game lobby for an authenticated user.
*
 * @param Request $request The incoming HTTP request object.
 * @param string $sessionId The ID of the session/room to check.
 * @return array{data: mixed, status: int} The standardized response array containing the lobby data.
 */
public function getLobbyStatus(Request $request, string $sessionId): array {
    /** @var string $userId */
    $userId = $request->getAuthUser()->sub;
    return $this->gameService->getLobbyStatus($sessionId, $userId);
}

/**
 * Controller endpoint to handle the request to start an existing PvP game session.
 *
 * @param Request $request The incoming HTTP request object.
 * @param string $sessionId The ID of the session/room to be started.
 * @return array {data: mixed, status: int} The standardized response array containing the initial game state.
 */
public function startPvPGame(Request $request, string $sessionId): array {
    $userId = $request->getAuthUser()->sub;
    
    Logger::info("PvP game start", [
        UserDataKeys::SESSION_ID => $sessionId,
        UserDataKeys::USER_ID => $userId
    ]);
    
    return $this->gameService->startPvPGame($sessionId, $userId);
}

/**
 * Controller endpoint to handle the request for a user to join the public multiplayer matchmaking queue.
 *
 * @param Request $request The incoming HTTP request object.
 * @return array{data: mixed, status: int} The standardized response array confirming entry into the queue.
 */
public function joinMultiplayerQueue(Request $request): array {
    /** @var string $userId */
    $userId = $request->getAuthUser()->sub;
    $requestData = $request->getBody();
    
    Logger::info("Multiplayer queue join", [
        UserDataKeys::USER_ID => $userId,
        GameEntitiesConstants::NUMBER_OF_CARDS => $requestData[GameEntitiesConstants::NUMBER_OF_CARDS] ?? self::UNKNOWN
    ]);
    
    return $this->gameService->joinMultiplayerQueue($requestData, $userId);
}

/**
 * Controller endpoint to retrieve the current status of the multiplayer queue or assigned session.
 *
 * @param Request $request The incoming HTTP request object.
 * @param string $sessionId The user's ID or session ID used for status lookup.
 * @return array{data: mixed, status: int} The standardized response array containing the matchmaking status.
 */
public function getMultiplayerStatus(Request $request, string $sessionId): array {
    /** @var string $userId */
    $userId = $request->getAuthUser()->sub;
    // $requestData = $request->getBody();
    return $this->gameService->getMultiplayerStatus($sessionId, $userId);
}


}