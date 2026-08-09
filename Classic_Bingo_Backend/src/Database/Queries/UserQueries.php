<?php 

namespace App\Database\Queries; 


final class UserQueries{

    private function __construct(){}

    public const FIND_PROFILE_BY_ID = 'SELECT user_id, user_name, avatar_id, role, created_at FROM users WHERE user_id = ?';
    public const FIND_BY_USERNAME = 'SELECT user_id FROM users WHERE user_name = ?';
    const FIND_COMPLETE_PROFILE_BY_ID = "
    SELECT 
        u.user_id,
        u.user_name,
        u.avatar_id,
        u.role,
        u.created_at,
        w.bingo_coins,
        w.dice,
        s.total_games,
        s.total_wins,
        s.total_losses,
        s.current_win_streak,
        s.best_win_streak
    FROM users u
    LEFT JOIN user_wallet w ON u.user_id = w.user_id
    LEFT JOIN user_stat s ON u.user_id = s.id 
    WHERE u.user_id = ?
";
    
    // 1. Creates the user identity record
    public const CREATE_USER = 'INSERT INTO users (user_id, user_name, avatar_id, refresh_token) VALUES (?, ?, ?, ?)';
    
    // 2. Creates the initial wallet record (FIXED: Added this back to resolve the PHP0414 error)
    public const CREATE_WALLET = 'INSERT INTO user_wallet (user_id) VALUES (?)'; 
    
    // 3. Creates the initial stats record
    public const CREATE_STAT = 'INSERT INTO user_stat (id) VALUES (?)';

    // --- AUTH/TOKEN QUERIES ---
    public const FIND_FOR_AUTH = 'SELECT refresh_token, role FROM users WHERE user_id = ?';
    public const UPDATE_REFRESH_TOKEN = 'UPDATE users SET refresh_token = ? WHERE user_id = ?';
    public const CLEAR_REFRESH_TOKEN = 'UPDATE users SET refresh_token = NULL WHERE user_id = ?';

}
