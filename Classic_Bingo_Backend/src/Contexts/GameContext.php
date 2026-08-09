<?php

namespace App\Contexts;

use App\Core\Container;
use App\Core\SessionManager;
use App\Controllers\GameController;
use App\Resources\GameSessionData;
use App\Config\GameConfig;
use App\Services\GameService;
use App\Services\CacheService;
use App\Services\PricingCalculator;
use App\Services\ParticipantManager;
use App\Factories\SessionFactory;
use App\Config\CacheConfig;
use App\Utils\BingoGenerator;
use App\Services\AIPlayer;
use App\Database\Database;   
use App\Models\Wallet;      
use App\Models\UserStat;    
use App\Models\GameResult; 
use PDO;

/**
 * GameContext
 * * The Service Provider responsible for defining and binding the dependencies 
 * required for the core game domain (sessions, controllers, services, utilities, and models).
 */
class GameContext {

    /**
     * Binds game-related services and components to the container.
     * * @param Container $container The application's DI container.
     * @return void
     */
    public static function bind(Container $container): void {

        // --- MODELS (Transient Bindings) ---


        /**
         * Wallet Model: Transient binding, injecting the Database wrapper.
         */
        $container->bind(Wallet::class, fn($c) => new Wallet($c->resolve(Database::class))); 

        /**
         * UserStat Model: Transient binding, injecting the Database wrapper.
         */
        $container->bind(UserStat::class, fn($c) => new UserStat($c->resolve(Database::class)));

        /**
         * GameResult Model: Transient binding, injecting the Database wrapper.
         */
        $container->bind(GameResult::class, fn($c) => new GameResult($c->resolve(Database::class)));

        // --- UTILITIES & CALCULATORS (Transient or Singleton based on state) ---


        /**
         * CacheService: Singleton binding, injecting the CacheConfig DTO.
         */
        $container->singleton(CacheService::class, function($c) {
            return new CacheService(
                $c->resolve(CacheConfig::class)
            );
        });

         /**
         * PricingCalculator: Transient binding, injecting the GameConfig DTO.
         */
        $container->bind(PricingCalculator::class, function($c) {
            return new PricingCalculator($c->resolve(GameConfig::class));
            });

         /**
         * ParticipantManager: Transient binding (stateless but simple binding).
         */
        $container->bind(ParticipantManager::class, function() {
                return new ParticipantManager();
            });  
            
        /**
         * BingoGenerator: Transient binding, injecting the GameConfig DTO.
         */
        $container->bind(BingoGenerator::class, fn($c)=>
            new BingoGenerator(
                $c->resolve(GameConfig::class),
            )
        );    

        /**
         * AIPlayer: Singleton binding, injecting the BingoGenerator utility.
         */
        $container->singleton(AIPlayer::class, function ($c) {
            return new AIPlayer(
                $c->resolve(BingoGenerator::class)
            );
        });

        // --- FACTORIES ---

        /**
         * SessionFactory: Transient binding, injecting generator/manager/calculator utilities.
         */
        $container->bind(SessionFactory::class, function($c) {
        return new SessionFactory(
            $c->resolve(BingoGenerator::class),
            $c->resolve(ParticipantManager::class),
            $c->resolve(PricingCalculator::class)
        );
     });
        
     // --- MANAGERS ---

        /**
         * SessionManager: Singleton binding, managing cache and factory for session persistence.
         */
        $container->singleton(SessionManager::class, function($c) {
            return new SessionManager(
                $c->resolve(PDO::class),
                $c->resolve(CacheService::class),
                $c->resolve(SessionFactory::class)
            );
        });

        // --- SERVICES ---

        /**
         * GameService: Singleton binding
         */
        $container->singleton(GameService::class, function($c) {
            return new GameService(
                $c->resolve(SessionManager::class),
                $c->resolve(BingoGenerator::class),
                $c->resolve(AIPlayer::class),
                $c->resolve(Wallet::class),
                $c->resolve(UserStat::class),
                $c->resolve(GameResult::class),
                $c->resolve(Database::class) 
            );
        });

      // --- CONTROLLERS --- 
        
        /**
         * GameController: Transient binding (per request), injecting the GameService singleton.
         */
        $container->bind(GameController::class, function($c) {
            return new GameController(
                $c->resolve(GameService::class)
            );
        });

    
    }  
}