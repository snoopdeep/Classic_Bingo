<?php 

use App\Controllers\AuthController;
use App\Controllers\GameController;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\GlobalHmacMiddleware;
use App\Enums\UserRole;
use App\Enums\AppEnvironment;


// Define local constant for environment key lookup
const APP_ENV_KEY = 'APP_ENV';


// =========================================================
// 1. PUBLIC AUTHENTICATION ROUTES (No prior authorization)
// =========================================================

// POST /api/v1/auth/signup: Registers a new user account. Requires HMAC signature.
$router->post('/api/v1/auth/signup', [AuthController::class, AuthController::METHOD_SIGN_UP], [GlobalHmacMiddleware::class]);

// POST /api/v1/auth/login: Authenticates a user and returns tokens. Requires HMAC signature.
$router->post('/api/v1/auth/login', [AuthController::class, AuthController::METHOD_LOGIN], [GlobalHmacMiddleware::class]); 

// POST /api/v1/auth/refresh: Generates a new access token using a valid refresh token. Requires HMAC signature.
$router->post('/api/v1/auth/refresh', [AuthController::class, AuthController::METHOD_REFRESH], [GlobalHmacMiddleware::class]); 

// =========================================================
// 2. PROTECTED AUTHENTICATION ROUTES (Requires Authentication)
// =========================================================

// POST /api/v1/auth/logout: Invalidates the user's current session/tokens. Requires HMAC and Auth.
$router->post('/api/v1/auth/logout', [AuthController::class, AuthController::METHOD_LOGOUT], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]); 

// GET /api/v1/auth/users/{userId}: Fetches user profile data by ID. 
// Requires Authentication and Authorization (User must be OWNER of the profile OR an ADMIN).
$router->get('/api/v1/auth/users/{userId}', [AuthController::class, AuthController::METHOD_GET_USER], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
    AuthorizationMiddleware::class . ':' . UserRole::OWNER->value . ':' . UserRole::ADMIN->value,
]); 

// =========================================================
// 3. SINGLE-PLAYER [SOLO]/AI GAMEPLAY ENDPOINTS
// =========================================================

// POST /api/v1/game/start: Initializes a new single-player game session.
$router->post('/api/v1/game/start', [GameController::class, GameController::METHOD_START], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);

// GET /api/v1/game/{sessionId}/next-number: Fetches the next number drawn by the caller (polling endpoint).
$router->get('/api/v1/game/{sessionId}/next-number', [GameController::class, GameController::METHOD_GET_NEXT_NUMBER], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);

// POST /api/v1/game/{sessionId}/daubedNumber: Marks a number on the player's card.
$router->post('/api/v1/game/{sessionId}/daubedNumber',[GameController::class, GameController::METHOD_DAUBED_NUMBER],[
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class
]);

// POST /api/v1/game/{sessionId}/bingo: Checks if the player has achieved a winning BINGO pattern.
$router->post('/api/v1/game/{sessionId}/bingo', [GameController::class, GameController::METHOD_CHECK_BINGO], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class
]);

// POST /api/v1/game/{sessionId}/complete: Logs and persists the final game result (win/loss) in database.
$router->post('/api/v1/game/{sessionId}/complete', [GameController::class, GameController::METHOD_COMPLETE_GAME], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class
]);

// =========================================================
// 4. MULTIPLAYER ENDPOINTS (PVP / Matchmaking)
// ---------------------------------------------------------

//4.1 --- Multiplayer Matchmaking ---

// POST /api/v1/multiplayer/queue: Adds the user to the public matchmaking queue or create a new session.
$router->post('/api/v1/multiplayer/queue', [GameController::class, GameController::METHOD_JOIN_MULTIPLAYER_QUEUE], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);

// GET /api/v1/multiplayer/{sessionId}/status: Polls for the current matchmaking status or game assignment.
$router->get('/api/v1/multiplayer/{sessionId}/status', [GameController::class, GameController::METHOD_GET_MULTIPLAYER_STATUS], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);


//4.2 --- Player vs Player (PvP) / Play with Friends ---

// NOTE: This PvP mode is currently implemented in the backend only and is NOT 
// fully integrated with the client-side application code.

// POST /api/v1/pvp/create: Creates a new private PvP room for friends.
$router->post('/api/v1/pvp/create', [GameController::class, GameController::METHOD_CREATE_PVP_ROOM], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);

// POST /api/v1/pvp/join: Allows a user to join an existing private PvP room.
$router->post('/api/v1/pvp/join', [GameController::class, GameController::METHOD_JOIN_PVP_ROOM], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);

// GET /api/v1/pvp/{sessionId}/lobby: Polls for the current status of the PvP lobby (e.g., list of joined players).
$router->get('/api/v1/pvp/{sessionId}/lobby', [GameController::class, GameController::METHOD_GET_LOBBY_STATUS], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);

// POST /api/v1/pvp/{sessionId}/start: Starts the PvP game once enough players have joined.
$router->post('/api/v1/pvp/{sessionId}/start', [GameController::class, GameController::METHOD_START_PVP_GAME], [
    GlobalHmacMiddleware::class,
    AuthenticationMiddleware::class,
]);


// =========================================================
// 5. DEBUG ROUTES - ONLY ENABLE IN DEVELOPMENT
// =========================================================
// NOTE::  Modify the GameSessionData for development perpose only.
if (($_SERVER[APP_ENV_KEY] ?? AppEnvironment::PRODUCTION->value) === AppEnvironment::DEVELOPMENT->value) {   

    // GET /api/v1/debug/session/{sessionId}: Retrieves the full session data for debugging.
    $router->get('/api/v1/debug/session/{sessionId}', [ GameController::class, GameController::METHOD_GET_GAME_SESSION_DATA],
    [AuthorizationMiddleware::class . ':' . UserRole::ADMIN->value. ':' . UserRole::DEVELOPER->value,]);
    
    // PUT /api/v1/debug/{sessionId}/session: Updates/modifies the game session state manually.
    $router->put('/api/v1/debug/{sessionId}/session', [GameController::class, GameController::METHOD_UPDATE_GAME_SESSION_DATA ],
    [AuthorizationMiddleware::class . ':' . UserRole::ADMIN->value. ':' . UserRole::DEVELOPER->value,]);
}

return $router;