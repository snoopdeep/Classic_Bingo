<?php

namespace App\Services;

use Predis\Client;
use Predis\Connection\ConnectionException;
use App\Config\CacheConfig;
use App\Utils\Logger;
use App\Constants\DbConfigKeys;
use App\Constants\ErrorResponseKeys;
use App\Handlers\AppException;
use App\Enums\ErrorCode;


/**
 * CacheService: A service layer wrapper around the Predis client, providing simplified methods 
 * for interacting with Redis.
 */
class CacheService {
    /**
     * @var Client|null The Predis client instance, initialized lazily.
     */
    private ?Client $client = null;
    /**
     * @var CacheConfig The configuration DTO for Redis connection details.
     */
    private CacheConfig $config;

 /**
 * CacheService constructor.
 * * Stores the configuration object. Connection to Redis is established lazily via getClient().
 *
 * @param CacheConfig $config The configuration object containing connection details.
 */
    public function __construct(CacheConfig $config) {
        $this->config = $config;
    }

   /**
     * Establishes a lazy connection to Redis and returns the client instance.
     * * This method ensures the connection is only attempted once and throws a controlled exception on connection failure.
     *
     * @return Client The connected Predis client instance.
     * @throws AppException If the Redis connection fails.
     */
    private function getClient(): Client {
        if ($this->client === null) {
            try {
                $this->client = new Client($this->config->toArray());
                $this->client->ping();
                // Logger::info("Redis connection established", [
                //     DbConfigKeys::HOST => $this->config->host,
                //     'port' => $this->config->port
                // ]);
            } catch (ConnectionException $e) {
                Logger::error("Redis connection failed", [
                    DbConfigKeys::HOST => $this->config->host,
                    DbConfigKeys::PORT => $this->config->port,
                    ErrorResponseKeys::ERROR => $e->getMessage()
                ]);
                throw new AppException(ErrorCode::INFRA_CACHE_CONNECTION_FAILED);
            }
        }
        return $this->client;
    }

/**
 * Retrieves a value from the cache by its key.
 *
 * @param string $key The key of the item to retrieve.
 * @return string|null The stored value as a string, or null if the key does not exist.
 * @throws AppException If the Redis connection fails during the operation.
 */
    public function get(string $key): ?string {
        return $this->getClient()->get($key);
    }

 /**
 * Stores a value in the cache, optionally setting a Time-To-Live (TTL).
 *
 * @param string $key The key to store the value under.
 * @param string $value The value to store.
 * @param int|null $ttl The Time-To-Live for the key in seconds (defaults to 3600).
 * @return void
 * @throws AppException If the Redis connection fails during the operation.
 */
    public function set(string $key, string $value, ?int $ttl = 3600): void {
        $this->getClient()->set($key, $value);
        if ($ttl) {
            $this->getClient()->expire($key, $ttl);
        }
    }

/**
 * Deletes one or more keys from the cache.
 *
 * @param string $key The key to delete.
 * @return int The number of keys that were removed (0 or 1).
 * @throws AppException If the Redis connection fails during the operation.
 */
    public function delete(string $key): int {
        return $this->getClient()->del([$key]);
    }

/**
 * Adds a member to a Redis Set.
 *
 * @param string $key The key of the Redis set.
 * @param string $member The member to add to the set.
 * @return void
 * @throws AppException If the Redis connection fails during the operation.
 */
public function sadd(string $key, string $member): void {
    $this->getClient()->sadd($key, [$member]);
}

/**
 * Removes a member from a Redis Set.
 *
 * @param string $key The key of the Redis set.
 * @param string $member The member to remove.
 * @return void
 * @throws AppException If the Redis connection fails during the operation.
 */
public function srem(string $key, string $member): void {
    // Get the client connection and execute the Redis SREM command.
    $this->getClient()->srem($key, $member);
}

/**
 * Retrieves all members of a Redis Set.
 *
 * @param string $key The key of the Redis set.
 * @return array<int, string> All members of the set.
 * @throws AppException If the Redis connection fails during the operation.
 */
public function smembers(string $key): array {
    // Get the client connection and execute the Redis SMEMBERS command.
    return $this->getClient()->smembers($key);
}

/**
 * Sets an expiration time (TTL) on a key.
 *
 * @param string $key The key to set the expiration on.
 * @param int $seconds The expiration time in seconds.
 * @return void
 * @throws AppException If the Redis connection fails during the operation.
 */
public function expire(string $key, int $seconds): void {
    $this->getClient()->expire($key, $seconds);
}
}