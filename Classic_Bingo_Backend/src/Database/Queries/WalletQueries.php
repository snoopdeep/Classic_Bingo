<?php 

namespace App\Database\Queries; 

final class WalletQueries{

    private function __construct(){}

    // Query to retrieve the current coin and dice balance
    public const GET_BALANCE_BY_USER_ID = 'SELECT bingo_coins, dice FROM user_wallet WHERE user_id = ?';

    // Query to create a new wallet entry upon user signup
    // It uses the default values defined in the migration (500 coins, 0 dice)
    public const CREATE_WALLET = 'INSERT INTO user_wallet (user_id) VALUES (?)';

    // Query to deduct the entry cost when a game starts
    public const DEDUCT_ENTRY_COST = 'UPDATE user_wallet SET bingo_coins = bingo_coins - ? WHERE user_id = ? AND bingo_coins >= ?';

    // Query to update the wallet with final game results (payouts)
    // NOTE: This adds net winnings and earned dice.
    public const UPDATE_BALANCE_AFTER_GAME = 'UPDATE user_wallet SET bingo_coins = bingo_coins + ?, dice = dice + ? WHERE user_id = ?';
}
