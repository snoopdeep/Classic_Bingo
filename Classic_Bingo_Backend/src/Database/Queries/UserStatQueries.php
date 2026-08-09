<?php 

namespace App\Database\Queries; 

final class UserStatQueries{

    private function __construct(){}

    // Retrieves stats needed for win streak calculation
    public const GET_STAT_BY_USER_ID = 'SELECT total_wins, total_losses, current_win_streak, best_win_streak FROM user_stat WHERE id = ?';

    // Updates all calculated stats after a game completes
    // Params: total_wins, total_losses, current_win_streak, best_win_streak, user_id
    public const UPDATE_STAT = 'UPDATE user_stat SET total_games = total_games + 1, total_wins = ?, total_losses = ?, current_win_streak = ?, best_win_streak = ? WHERE id = ?';
}