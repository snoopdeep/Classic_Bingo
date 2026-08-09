<?php

namespace App\Contexts;

use App\Controllers\AuthController;
use App\Core\Container;
use App\Core\Config;
use App\Config\JwtConfig;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Database\Database; 
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\AuthorizationService;
use PDO;

/**
 * AuthContext
 * * The Service Provider responsible for defining and binding the dependencies 
 * required for the authentication and authorization domains to the DI container.
 */
class AuthContext {
    /**
     * Binds authentication and authorization services to the container.
     * 
     * @param Container $container The application's DI container.
     * @return void
     */
    public static function bind(Container $container): void {

        // --- CONFIGURATION DTOs ---


        /**
         * Register the strongly-typed JWT configuration DTO as a singleton.
         * Resolves the raw config data and validates it upon first access.
         */
        $container->singleton(JwtConfig::class, function ($c) {
            $rawJwtConfig = $c->resolve(Config::class)->get('jwt');
            return new JwtConfig($rawJwtConfig);
        });

        // --- MODELS ---

        /**
         * User Model: Bound transiently, ensuring a fresh User Model instance 
         * is created for every resolution to prevent accidental state bleed.
         */
        $container->bind(User::class, function ($c) {
            // Inject the Database wrapper instead of the raw PDO instance
            return new User($c->resolve(Database::class)); // Inject Database wrapper
        });

        // --- SERVICES ---

        /**
         * Authorization Service: Bound as a singleton, as it is stateless and 
         * only performs helper logic.
         */
        $container->singleton(AuthorizationService::class, function() {
            return new AuthorizationService();
        });

        /**
         * Authentication Service: Bound as a singleton. 
         */
        $container->singleton(AuthenticationService::class, function ($c) {
            return new AuthenticationService(
                // Resolves the transient User model dependency
                $c->resolve(User::class),
                // Resolves the singleton JwtConfig dependency
                $c->resolve(JwtConfig::class)
                
            );
        });

        // --- CONTROLLERS ---

        /**
         * Auth Controller: Bound transiently.
         */
        $container->bind(AuthController::class, function ($c) {
            return new AuthController($c->resolve(AuthenticationService::class));
        });

        // --- MIDDLEWARE ---

        /**
         * Authentication Middleware: Bound transiently, created per-request.
         */
        $container->bind(AuthenticationMiddleware::class, fn ($c) => new AuthenticationMiddleware($c->resolve(JwtConfig::class)));

        /**
         * Authorization Middleware: Bound transiently, created per-request.
         */
        $container->bind(AuthorizationMiddleware::class, function ($c) {
            return new AuthorizationMiddleware($c->resolve(AuthorizationService::class));
        });
    }
}