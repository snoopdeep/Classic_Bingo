<?php

namespace App\Contexts;

use App\Core\Container;
use App\Core\Config;
use App\Config\AppConfig;
use App\Config\DatabaseConfig;
use App\Config\CacheConfig; 
use App\Config\GameConfig;
use App\Database\Database;
use App\Middleware\GlobalHmacMiddleware;
use App\Utils\Router;
use App\Core\Request;
use PDO;

/**
 * Service provider for core application contexts.
 *
 */
class CoreContext
{
    // CONSTANTS 

    /**
     * @var string The key for database configuration lookup.
     */
    private const DATABASE = 'database';

    /**
     * @var string The key for general application configuration lookup.
     */
    private const APP = 'app';

    /**
     * @var string The key for cache configuration lookup.
     */
    private const CACHE = 'cache';

    /**
     * @var string The key for game configuration lookup.
     */
    private const GAME = 'game';

    /**
     * Binds core application services to the container.
     * 
     * @param Container $container The application's DI container.
     * @return void
     */
    public static function bind(Container $container): void {

        // --- CONFIGURATION DTOs (All Singletons) ---

        /**
         * Main Config service: Loads all config files once.
         */
        $container->singleton(Config::class, function () {
            return new Config(__DIR__ . '/../../config');
        });

        /**
         * Database Config DTO: Validates and holds database connection parameters.
         */
        $container->singleton(DatabaseConfig::class, function ($c) {
            /** @var array<string, mixed> $rawDbConfig */
            $rawDbConfig = $c->resolve(Config::class)->get(self::DATABASE);
            return new DatabaseConfig($rawDbConfig);
        });

        /**
         * App Config DTO: Validates and holds general application parameters (e.g., global secret).
         */
        $container->singleton(AppConfig::class, function($c){
            /** @var array<string, mixed> $rawAppConfig */
            $rawAppConfig = $c->resolve(Config::class)->get(self::APP);
            return new AppConfig($rawAppConfig);
        });

        /**
         * Cache Config DTO: Validates and holds cache connection parameters (e.g., Redis).
         */
        $container->singleton(CacheConfig::class, function($c) {
            /** @var array<string, mixed> $rawCacheConfig */
            $rawCacheConfig = $c->resolve(Config::class)->get(self::CACHE);
            return new CacheConfig($rawCacheConfig);
        });

        /**
         * Game Config DTO: Holds game-specific static configuration (e.g., winning patterns, pricing).
         */
        $container->singleton(GameConfig::class, function($c) {
            /** @var array<string, mixed> $rawGameConfig */
            $rawGameConfig = $c->resolve(Config::class)->get(self::GAME);
            return new GameConfig($rawGameConfig);
        });

        // --- CORE SERVICES (SINGLETONS) ---

         /**
         * Database Wrapper: Registered as a singleton, ensuring the connection logic 
         * (and connection instance) is managed in one place.
         */
        $container->singleton(Database::class, function ($c) {
            /** @var DatabaseConfig $dbConfig */
            $dbConfig = $c->resolve(DatabaseConfig::class);
            // Uses a static factory/instance getter for thread safety
            return Database::getInstance($dbConfig);
        });

        
        /**
         * PDO Connection: The raw PDO object is also registered as a singleton, 
         * reusing the single connection from the Database wrapper.
         */
        $container->singleton(PDO::class, function ($c) {
            // Resolves the Database singleton and extracts the connection
            /** @var Database $db */
            return $c->resolve(Database::class)->getConnection();
        });
        
        /**
         * Router: The router is a singleton as it holds the persistent state of all registered routes.
         */
        $container->singleton(Router::class, fn() => new Router());

        // The Request object is a singleton FOR THE DURATION of a single HTTP request.
        $container->singleton(Request::class, fn() => new Request());

        // --- MIDDLEWARE ---

        /**
         * GlobalHmacMiddleware: Bound transiently (per-request), injecting the global secret.
         */
        $container->bind(GlobalHmacMiddleware::class, function($c){
            return new GlobalHmacMiddleware($c->resolve(AppConfig::class)->secret);
        });
    }
}