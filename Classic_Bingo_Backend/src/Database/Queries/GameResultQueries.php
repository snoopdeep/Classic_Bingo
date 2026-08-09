<?php 

namespace App\Database\Queries; 

final class GameResultQueries{

    private function __construct(){}

    // Inserts the final outcome of a game session
    // Params: session_id, user_id, session_type, result, coins_won, dice_earned, game_duration_seconds
    public const INSERT_GAME_RESULT = 'INSERT INTO game_result (session_id, user_id, session_type, result, coins_won, dice_earned, game_duration_seconds) VALUES (?, ?, ?, ?, ?, ?, ?)';

    // Query for a user's match history dashboard view
    public const GET_RECENT_RESULTS_BY_USER_ID = 'SELECT session_id, session_type, result, coins_won, created_at FROM game_result WHERE user_id = ? ORDER BY created_at DESC LIMIT 10';
}