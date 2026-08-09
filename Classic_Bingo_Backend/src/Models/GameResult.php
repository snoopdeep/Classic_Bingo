<?php
// ============================================================================
// FILE: App/Models/GameResult.php
// ============================================================================
namespace App\Models;

use PDOException;
use App\Database\Queries\GameResultQueries;
use App\Database\Database;
use App\Enums\ErrorCode;
use App\Handlers\AppException;

/**
 * GameResult Model - Data Mapper for the 'game_result' table.
 */
class GameResult {
    private Database $dbAccess;

    public function __construct(Database $dbAccess) {
        $this->dbAccess = $dbAccess;
    }

    /**
     * Inserts a record of a completed game session's outcome.
     *
     * @param string $sessionId The ID of the completed session.
     * @param string $userId The ID of the user whose result is being recorded.
     * @param string $sessionType The type of game ('pvp', 'vs_ai', etc.).
     * @param string $result The outcome ('win', 'loss', 'tie').
     * @param int $coinsWon The net coin amount won/lost.
     * @param int $diceEarned The number of dice earned.
     * @param int $duration The game duration in seconds.
     * @return bool True on successful insert.
     * @throws AppException
     */
    public function insertResult(
        string $sessionId, 
        string $userId, 
        string $sessionType, 
        string $result, 
        int $coinsWon, 
        int $diceEarned, 
        int $duration
    ): bool {
        try {
            $affectedRows = $this->dbAccess->execute(
                GameResultQueries::INSERT_GAME_RESULT,
                [
                    $sessionId,
                    $userId,
                    $sessionType,
                    $result,
                    $coinsWon,
                    $diceEarned,
                    $duration
                ]
            );
            return $affectedRows === 1;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }
}