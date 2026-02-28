<?php
namespace App\Utils;

use PDO;
use PDOException;

class Database {
    private static ?self $instance = null;
    public ?PDO $connection = null;


    private function __construct(array $config) {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        try {
            $this->connection = new PDO($dsn, $config['user'], $config['password']);

        } catch (PDOException $e) {
            //  log this error [later..]
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed.']);
            exit();
        }
    }

    /**
     * The static method that loads the config and creates the single instance.
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            // Load the configuration array from the file
            $dbConfig = require __DIR__ . '/../Config/database.php';
            // Pass the config to the private constructor
            self::$instance = new self($dbConfig);
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }
}