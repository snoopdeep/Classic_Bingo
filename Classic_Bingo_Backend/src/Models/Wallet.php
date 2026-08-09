<?php

// ============================================================================
// FILE: App/Models/Wallet.php
// ============================================================================
namespace App\Models;

use PDOException;
use App\Database\Queries\WalletQueries;
use App\Database\Database;
use App\Enums\ErrorCode;
use App\Handlers\AppException;

/**
 * Wallet Model - Data Mapper for the 'user_wallet' table.
 */
class Wallet {
    private Database $dbAccess;

    public function __construct(Database $dbAccess) {
        $this->dbAccess = $dbAccess;
    }

    /**
     * Fetches the current coin and dice balance for a user.
     *
     * @param string $userId The UUID of the user.
     * @return array<string, mixed>|null The balance data or null if not found.
     * @throws AppException
     */
    public function getBalance(string $userId): ?array {
        try {
            $result = $this->dbAccess->fetchOne(WalletQueries::GET_BALANCE_BY_USER_ID, [$userId]);
            return $result ?: null;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }

    /**
     * Deducts the entry cost from the user's wallet.
     * The SQL query ensures the deduction only occurs if the user has sufficient funds.
     *
     * @param string $userId The ID of the user.
     * @param int $entryCost The amount of coins to deduct.
     * @return bool True if deduction was successful, false if insufficient coins.
     * @throws AppException
     */
    public function deductEntryCost(string $userId, int $entryCost): bool {
        try {
            $affectedRows = $this->dbAccess->execute(
                WalletQueries::DEDUCT_ENTRY_COST,
                [$entryCost, $userId, $entryCost]
            );
            return $affectedRows === 1;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }

    /**
     * Updates the user's balance and dice count after a game is completed.
     *
     * @param string $userId The ID of the user.
     * @param int $coinsPayout The net coin amount to add.
     * @param int $diceEarned The number of dice earned.
     * @return bool True on successful update.
     * @throws AppException
     */
    public function updateBalanceAfterGame(string $userId, int $coinsPayout, int $diceEarned): bool {
        try {
            $affectedRows = $this->dbAccess->execute(
                WalletQueries::UPDATE_BALANCE_AFTER_GAME,
                [$coinsPayout, $diceEarned, $userId]
            );
            return $affectedRows === 1;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }
}