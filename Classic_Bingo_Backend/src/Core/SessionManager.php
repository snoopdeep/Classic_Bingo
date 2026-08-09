<?php 

namespace App\Core;

use App\Constants\ErrorResponseKeys;
use App\Utils\UUIDGenerator;
use App\Utils\Logger;
use App\Resources\GameSessionData;
use App\Services\CacheService;
use App\Handlers\AppException;
use App\Factories\SessionFactory;
use App\Constants\UserDataKeys;
use App\Enums\ErrorCode;
use App\Constants\GameEntitiesConstants;
use PDO;
use Exception;

/**
 * SessionManager is responsible for coordinating the creation, persistence, 
 * and retrieval of active game sessions, leveraging both local and remote caches.
 */
class SessionManager{

    /**
     * @var PDO The database connection instance (reserved for persistence logic).
     */
    private PDO $db; 
    /**
     * @var CacheService The remote cache service instance (e.g., Redis).
     */
    private CacheService $cache; 
    /**
     * @var SessionFactory The factory to handle complex session object creation.
     */
    private SessionFactory $sessionFactory; 
    
    /**
    * @var array<string, GameSessionData> Local cache that persists only during a single HTTP request.
     */ 
    private static array $localCache = [];

    /**
     * @var bool Flag to enable/disable the local cache.
     */
    private static bool $localCacheEnabled = true;

    // RealTime Multiplayer game constants 

    /**
     * @var string The key for the Redis set used for the matchmaking queue.
     */
    private const QUEUE_KEY = 'multiplayer:queue';

    /**
     * @var int The Time-To-Live for the matchmaking queue key (1 hour).
     */
    private const QUEUE_TTL = 3600; // 1 hour [for testing]
    
    /**
     * SessionManager constructor.
     * * @param PDO $database The database connection instance.
     * @param CacheService $cacheService The remote cache service instance.
     * @param SessionFactory $sessionFactory The factory responsible for building GameSessionData.
     */
     public function __construct(PDO $database, CacheService $cacheService, SessionFactory $sessionFactory) {
        $this->db = $database;
        $this->cache = $cacheService;
        $this->sessionFactory = $sessionFactory;
    }

    /**
     * Creates a new game session.
     * Delegates the session object configuration and building logic to the SessionFactory.
     * * @param array $requestData Client data needed for configuration (e.g., mode, cards).
     * @param string $authenticatedUserId The UUID of the user initiating the session.
     * @return array{0: string, 1: GameSessionData} The newly created session ID and object.
     * @throws Exception If session creation or caching fails.
     */
    public function createSession(array $requestData, string $authenticatedUserId): array {

        // Step 1: Generate session ID
        $sessionId = UUIDGenerator::generate();
    
        // Step 2: Delegate session building to factory
        /** @var GameSessionData $sessionData */
        $sessionData = $this->sessionFactory->createSession(
            $sessionId, 
            $requestData, 
            $authenticatedUserId
        );

    try{  
        // Step 3: Save to cache
        $this->saveSession( $sessionData, $sessionId);

    }catch (Exception $e) {
            Logger::error("Session creation failed", [
                UserDataKeys::USER_ID => $authenticatedUserId,
                GameEntitiesConstants::ERROR => $e->getMessage()
            ]);
            throw $e;
        }

    // Step 5: return sessionData
    return [$sessionId, $sessionData];
}

/**
 * Retrieves session data, prioritizing local cache for performance.
 * * @param string $sessionId The UUID of the session to retrieve.
 * @return GameSessionData|null The session object, or null if not found.
 * @throws AppException If the underlying cache retrieval operation fails.
 */
public function getSession(string $sessionId): ?GameSessionData {

    $key = "session:{$sessionId}";

    // Check local cache first
    if (isset(self::$localCache[$sessionId])) {
        return self::$localCache[$sessionId];
    }
    
    // Check Redis cache
    try {
        $jsonData = $this->cache->get($key);
    } catch (Exception $e) {
        // Log the infrastructure failure (e.g., Redis connection issue).
        Logger::error("Failed to retrieve session data from cache", [
            'key' => $key,
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
        ]);
        // Throw a generalized infrastructure error.
        throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, ['reason' => 'Cache retrieval failed'], $e);
    }
    // If Redis returned null (key not found), return null.
    if ($jsonData === null) {
        return null;
    }
    
    // Deserialize the JSON data into a GameSessionData object.
    /** @var GameSessionData $sessionData */
    $sessionData = GameSessionData::fromJson($jsonData);
    // Store the retrieved data in the local cache...
    self::$localCache[$sessionId] = $sessionData;
    
    return $sessionData;
}

/**
     * A simple public method for saving the session state to the remote cache.
     *
     * @param GameSessionData $sessionData The session object to save.
     * @param string $sessionId The UUID of the session.
     * @return void
     * @throws Exception If the underlying cache operation fails.
     */
public function saveSession(GameSessionData $sessionData, string $sessionId,): void {

    try {
        // 1. Save main session data (also updates local cache)
        $this->saveSessionToCache($sessionId, $sessionData);
        
        // If PvP session with join code, save reverse lookup
        if (!empty($sessionData->joinCode)) {
            $lookupKey = "joincode:{$sessionData->joinCode}";
            // Set TTL to match session TTL (1800 seconds)
            $this->cache->set($lookupKey, $sessionId, 1800); 
        }
    } catch (Exception $e) {
        Logger::error("Failed to save session", [
            GameEntitiesConstants::SESSION_ID => $sessionId,
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
     * Deletes a game session from both the local and the remote cache (Redis).
     *
     * @param string $sessionId The ID of the session to delete.
     * @return int The number of keys deleted (usually 1).
     * @throws AppException If the underlying cache operation fails.
     */
    public function deleteSession(string $sessionId): int {
        try {
            // 1. Delete from remote cache
            $deletedCount = $this->cache->delete("session:{$sessionId}");

            // 2. Delete from local cache
            if (isset(self::$localCache[$sessionId])) {
                unset(self::$localCache[$sessionId]);
            }
            
            Logger::info("Session deleted successfully.", ['sessionId' => $sessionId, 'deleted_keys' => $deletedCount]);
            return $deletedCount;

        } catch (AppException $e) {
            Logger::error("Failed to delete session from cache.", ['sessionId' => $sessionId, 'error' => $e->getMessage()]);
            // Re-throw the application error
            throw $e;
        }
    }

/**
 * Finds the session ID associated with a PvP join code.
 *
 * @param string $joinCode The 4-character join code.
 * @return string|null The UUID of the session, or null if not found or on error.
 */
public function findSessionByJoinCode(string $joinCode): ?string {
    $lookupKey = "joincode:{$joinCode}";
    try {
        $sessionId = $this->cache->get($lookupKey);
        return $sessionId ?: null;
    } catch (Exception $e) {
        Logger::error("Failed to find session by join code", [
            GameEntitiesConstants::JOIN_CODE => $joinCode,
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Adds a session to the multiplayer matchmaking queue (a Redis Set).
 *
 * @param string $sessionId The ID of the session to add.
 * @return void
 * @throws Exception If the underlying cache operation fails.
 */
public function addToQueue(string $sessionId): void {
    try {
        $this->cache->sadd(self::QUEUE_KEY, $sessionId);
        $this->cache->expire(self::QUEUE_KEY, self::QUEUE_TTL);
        
        Logger::info("Session added to queue", ['sessionId' => $sessionId]);
    } catch (Exception $e) {
        Logger::error("Failed to add session to queue", [
            GameEntitiesConstants::SESSION_ID => $sessionId,
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * Removes a session from the matchmaking queue (a Redis Set).
 *
 * @param string $sessionId The ID of the session to remove.
 * @return void
 * @throws Exception If the underlying cache operation fails (though typically suppressed).
 */
public function removeFromQueue(string $sessionId): void {
    try {
        // Execute the Redis SREM (Set REMove) command.
        $this->cache->srem(self::QUEUE_KEY, $sessionId);
        Logger::info("Session removed from queue", ['sessionId' => $sessionId]);
    } catch (Exception $e) {
        Logger::error("Failed to remove session from queue", [
            GameEntitiesConstants::SESSION_ID => $sessionId,
            ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
        ]);
        // Note: No re-throw, allowing the calling function (e.g., findAvailableSession) to continue.
    }
}

/**
 * Finds an available multiplayer session from the queue, performing validity checks 
 * and cleaning up stale entries.
 *
 * @return string|null The ID of an available session, or null if none is found or on error.
 */
public function findAvailableSession(): ?string {
    // Attempt to retrieve all session IDs from the queue set in Redis.
    try {
        $activeSessions = $this->cache->smembers(self::QUEUE_KEY);
    } catch (Exception $e) {
        // Log the failure to retrieve the set of session IDs.
        Logger::error("Failed to retrieve active session IDs from queue", ['error' => $e->getMessage()]);
        return null;
    }

    // Iterate over each session ID retrieved from the queue.
    foreach ($activeSessions as $sessionId) {
        // Check session details and validity, which itself involves a cache/data call.
        try {
            /** @var GameSessionData|null $sessionData */
            $sessionData = $this->getSession($sessionId);

            if (!$sessionData) {
                // Stale session, remove from queue
                $this->removeFromQueue($sessionId); 
                continue;
            }
            
            // Check if session is valid for joining: (Not active, grace time > now, has space)
            if (!$sessionData->isActive && 
                $sessionData->graceEndTime > time() &&
                count($sessionData->participants) < $sessionData->maxPlayers) {
                return $sessionId; // Found an available session
            }
            
            // Clean up expired or full sessions from the queue:
            if ($sessionData->graceEndTime <= time() || 
                count($sessionData->participants) >= $sessionData->maxPlayers) {
                $this->removeFromQueue($sessionId);
            }
        } catch (Exception $e) {
            // Log error for a specific session processing failure but continue to the next session.
            Logger::error("Failed to process session ID in queue loop", [
                GameEntitiesConstants::SESSION_ID => $sessionId, 
                ErrorResponseKeys::ERROR_MESSAGE => $e->getMessage()
            ]);
        }
    }
    return null;
}
    
/**
 * Serializes the session object to JSON and saves it to the remote cache.
 *
 * @param string $sessionId The ID of the session.
 * @param GameSessionData $sessionData The object state to persist.
 * @return void
 * @throws Exception If the underlying cache operation fails.
 */
private function saveSessionToCache(string $sessionId, GameSessionData $sessionData): void {

        $jsonData = $sessionData->toJson();
        // Set TTL to 30 minutes (1800 seconds)
        $this->cache->set("session:{$sessionId}", $jsonData, 1800); // 15 min TTL => 60 * 60 * 1/4
        
        // Also update local cache
        self::$localCache[$sessionId] = $sessionData;
    }   

}