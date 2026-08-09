<?php

// ============================================================================
// FILE: App/Models/UserStat.php
// ============================================================================
namespace App\Models;

use PDOException;
use App\Database\Queries\UserStatQueries;
use App\Database\Database;
use App\Enums\ErrorCode;
use App\Handlers\AppException;

/**
 * UserStat Model - Data Mapper for the 'user_stat' table.
 */
class UserStat {
    private Database $dbAccess;

    public function __construct(Database $dbAccess) {
        $this->dbAccess = $dbAccess;
    }

    /**
     * Retrieves the current statistics for a user.
     *
     * @param string $userId The UUID of the user.
     * @return array<string, mixed>|null The stats data or null if not found.
     * @throws AppException
     */
    public function getStat(string $userId): ?array {
        try {
            $result = $this->dbAccess->fetchOne(UserStatQueries::GET_STAT_BY_USER_ID, [$userId]);
            return $result ?: null;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }

    /**
     * Updates all aggregated statistics for a user in a single operation.
     * The total_games increment is handled in the SQL query.
     *
     * @param string $userId The ID of the user.
     * @param int $totalWins The new calculated total wins.
     * @param int $totalLosses The new calculated total losses.
     * @param int $currentWinStreak The new calculated current win streak.
     * @param int $bestWinStreak The new calculated best win streak.
     * @return bool True on successful update.
     * @throws AppException
     */
    public function updateStat(
        string $userId, 
        int $totalWins, 
        int $totalLosses, 
        int $currentWinStreak, 
        int $bestWinStreak
    ): bool {
        try {
            $affectedRows = $this->dbAccess->execute(
                UserStatQueries::UPDATE_STAT,
                [
                    $totalWins, 
                    $totalLosses, 
                    $currentWinStreak, 
                    $bestWinStreak, 
                    $userId
                ]
            );
            return $affectedRows === 1;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }
}