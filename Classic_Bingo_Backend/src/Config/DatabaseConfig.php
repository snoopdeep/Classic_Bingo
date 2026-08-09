<?php

namespace App\Config;

use App\Constants\DbConfigKeys;
use Webmozart\Assert\Assert;

/**
 * A strongly-typed DTO for database connection settings.
 *
 * This class ensures that all required database credentials and parameters
 * are present and correctly typed when the application initializes.
 */
final class DatabaseConfig
{
    /**
     * @var string The database server hostname or IP address.
     */
    public readonly string $host;

    /**
     * @var int The port number for the database connection.
     */
    public readonly int $port;

    /**
     * @var string The name of the database.
     */
    public readonly string $dbname;

    /**
     * @var string The username for the database connection.
     */
    public readonly string $user;

    /**
     * @var string The password for the database connection.
     */
    public readonly string $password;

    /**
     * @var string The character set for the database connection.
     */
    public readonly string $charset;
    private const UTF_CHAR = 'utf8mb4';

    /**
     * Validates and maps the raw database configuration array.
     *
     * @param array<string, mixed> $config The configuration array, typically from `Config::get('database')`.
     * @throws \InvalidArgumentException If validation fails.
     * @return void
     */
    public function __construct(array $config) {
        Assert::keyExists($config, DbConfigKeys::HOST);
        Assert::notEmpty($config[DbConfigKeys::HOST], 'Database host cannot be empty.');
        
        Assert::keyExists($config, DbConfigKeys::PORT);
        Assert::integerish($config[DbConfigKeys::PORT], 'Database port must be a valid integer.');

        Assert::keyExists($config, DbConfigKeys::DBNAME);
        Assert::notEmpty($config[DbConfigKeys::DBNAME], 'Database name cannot be empty.');
        
        Assert::keyExists($config, DbConfigKeys::USER);
        Assert::notEmpty($config[DbConfigKeys::USER], 'Database user cannot be empty.');

        Assert::keyExists($config, DbConfigKeys::PASSWORD);
        Assert::string($config[DbConfigKeys::PASSWORD], 'Database password must be a string.');

        $this->host = $config[DbConfigKeys::HOST];
        $this->port = (int) $config[DbConfigKeys::PORT];
        $this->dbname = $config[DbConfigKeys::DBNAME];
        $this->user = $config[DbConfigKeys::USER];
        $this->password = $config[DbConfigKeys::PASSWORD];
        $this->charset = $config[DbConfigKeys::CHARSET] ?? self::UTF_CHAR;
    }
}