<?php

namespace App\Database;

use PDO;
use PDOException;
use App\Utils\Logger;
use App\Constants\DbConfigKeys;
use App\Config\DatabaseConfig;
use InvalidArgumentException;

class Database {
    private static ?self $instance = null;
    public ?PDO $connection = null;

    /** @var string The sprintf format for the MySQL DSN. */
    private const DSN_FORMAT = 'mysql:host=%s;port=%d;dbname=%s;charset=%s';

    /** @var string The error message for a missing configuration key. */
    private const MISSING_KEY_ERROR = 'Database configuration is missing required key: \'%s\'';

    private function __construct(string $dsn, ?string $user, ?string $password) { 
        try {
            $this->connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // turn on to throw PDO exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

        } catch (PDOException $e) {
            Logger::error("Database Connection Failed: " . $e->getMessage());
            // re-throe the exception to be caught by the global exceptionHandler
            throw $e;
        }
    }

    /**
     * The main static method that acts as the entry point.
     * It requires a validated DatabaseConfig DTO.
     */
    public static function getInstance(DatabaseConfig $config): self {
        if (self::$instance === null) {
            
            // 1: get the connectionDetails
            $connectionDetails = self::prepareConnectionDetails($config);

            // 2: Pass the config to the private constructor
            self::$instance = new self(
                $connectionDetails[DbConfigKeys::DSN],
                $connectionDetails[DbConfigKeys::USER],
                $connectionDetails[DbConfigKeys::PASSWORD]
            );
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

     /**
     * Starts a database transaction.
     * @return bool
     */
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }

    /**
     * Commits the current transaction.
     * @return bool
     */
    public function commit(): bool {
        return $this->connection->commit();
    }

     /**
     * Rolls back the current transaction.
     * @return bool
     */
    public function rollback(): bool {
        return $this->connection->rollback();
    }

    /**
     * Executes a simple query for INSERT, UPDATE, or DELETE and returns the number of affected rows.
     * @param string $sql The SQL query.
     * @param array $params The parameters for the query.
     * @return int The number of rows affected.
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

     /**
     * Fetches a single row.
     * @param string $sql The SQL query.
     * @param array $params The parameters for the query.
     * @return array|false The fetched row or false if no rows.
     */
    public function fetchOne(string $sql, array $params = []): array|false {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * A private helper that takes the DTO and prepares the primitive
     * values needed by the constructor.
     */
    private static function prepareConnectionDetails(DatabaseConfig $config): array {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config->host,
            $config->port,
            $config->dbname,
            $config->charset
        );

        return [
            DbConfigKeys::DSN => $dsn,
            DbConfigKeys::USER => $config->user,
            DbConfigKeys::PASSWORD => $config->password,
        ];
    }
}
