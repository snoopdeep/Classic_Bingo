<?php

namespace App\Config;

use App\Constants\DbConfigKeys;
use Webmozart\Assert\Assert;
use InvalidArgumentException; 

/**
 * CacheConfig
 * * Strongly-typed Data Transfer Object (DTO) for Redis cache connection settings.
 * It validates required properties and formats the configuration for the Predis client.
 */
final class CacheConfig
{
    /**
     * @var string The connection scheme (e.g., 'tcp').
     */
    public readonly string $scheme;

    /**
     * @var string The Redis host address.
     */
    public readonly string $host;

    /**
     * @var int The Redis port number.
     */
    public readonly int $port;

    /**
     * @var string|null The Redis password, or null if not required.
     */
    public readonly ?string $password;

    /**
     * @var int The Redis database index (0-15).
     */
    public readonly int $database;

    /**
     * @var int The connection timeout in seconds.
     */
    public readonly int $timeout;

    /**
     * CacheConfig constructor.
     *
     * Validates and maps the raw configuration array to the typed properties.
     *
     * @param array<string, mixed> $config The configuration array, typically from environment variables.
     * @throws InvalidArgumentException If validation fails (e.g., host is missing or port is invalid).
     */
    public function __construct(array $config) {
        // Validation for required keys
        Assert::keyExists($config, DbConfigKeys::HOST);
        Assert::notEmpty($config[DbConfigKeys::HOST], 'Redis host cannot be empty.');
        
        Assert::keyExists($config, DbConfigKeys::PORT);
        Assert::integerish($config[DbConfigKeys::PORT], 'Redis port must be a valid integer.');

        // Assignment with defaults where necessary
        $this->scheme = $config[DbConfigKeys::SCHEME] ?? DbConfigKeys::TCP;
        $this->host = $config[DbConfigKeys::HOST];
        $this->port = (int) $config[DbConfigKeys::PORT];
        $this->password = !empty($config[DbConfigKeys::PASSWORD]) ? $config[DbConfigKeys::PASSWORD] : null;
        $this->database = (int) ($config[DbConfigKeys::DATABASE] ?? 0);
        $this->timeout = (int) ($config[DbConfigKeys::TIMEOUT] ?? 5);
    }

    /**
     * Converts the DTO properties into an array format suitable for the Predis client constructor.
     *
     * @return array<string, mixed> The Predis connection configuration array.
     */
    public function toArray(): array
    {
        $config = [
            DbConfigKeys::SCHEME => $this->scheme,
            DbConfigKeys::HOST => $this->host,
            DbConfigKeys::PORT => $this->port,
            DbConfigKeys::DATABASE => $this->database,
            DbConfigKeys::TIMEOUT => $this->timeout,
        ];

        // Only include password if it is set
        if ($this->password !== null) {
            $config[DbConfigKeys::PASSWORD] = $this->password;
        }

        return $config;
    }
}