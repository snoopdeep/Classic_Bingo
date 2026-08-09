<?php

namespace App\Constants;

/**
 * Maps database keys (left) to API/client-side response keys (right)
 * for the extended user profile endpoint.
 */
final class UserProfileKeys {
    // Database Keys (Snake Case)           // API Response Keys (Camel Case)
    public const BINGO_COINS = 'bingo_coins';
    public const BINGO_COINS_API = 'bingoCoins';
    
    public const DICE = 'dice';
    public const DICE_API = 'dice'; // Key is the same
    
    public const TOTAL_GAMES = 'total_games';
    public const TOTAL_GAMES_API = 'totalGames';
    
    public const TOTAL_WINS = 'total_wins';
    public const TOTAL_WINS_API = 'totalWins';
    
    public const TOTAL_LOSSES = 'total_losses';
    public const TOTAL_LOSSES_API = 'totalLosses';
    
    public const TOTAL_DRAWS_API = 'totalDraws'; // Derived, no direct DB key
    
    public const CURRENT_WIN_STREAK = 'current_win_streak';
    public const CURRENT_WIN_STREAK_API = 'currentWinStreak';
    
    public const BEST_WIN_STREAK = 'best_win_streak';
    public const BEST_WIN_STREAK_API = 'bestWinStreak';
}