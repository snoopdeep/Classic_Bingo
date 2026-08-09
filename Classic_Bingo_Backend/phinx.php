<?php

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from your .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/src/Database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/src/Database/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'],
            'name' => $_ENV['DB_DATABASE'],
            'user' => $_ENV['DB_USERNAME'],
            'pass' => $_ENV['DB_PASSWORD'],
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'charset' => 'utf8mb4',
        ],
        // You can add 'production' and 'testing' environments here later
    ],
    'version_order' => 'creation'
];