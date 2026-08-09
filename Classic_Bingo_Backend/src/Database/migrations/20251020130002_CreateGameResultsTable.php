<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGameResultsTable extends AbstractMigration
{
    /**
     * Creates the game_results table to persist final game outcomes.
     */
    public function change(): void
    {
        // Phinx defaults to 'id' auto-incrementing primary key
        $table = $this->table('game_result');

        $table->addColumn('session_id', 'string', ['limit' => 36, 'null' => false]) // No FK as sessions are cache-based
              ->addColumn('user_id', 'string', ['limit' => 36, 'null' => false])
              ->addColumn('session_type', 'enum', [
                  'values' => ['pvp', 'vs_ai', 'solo', 'practice', 'tournament'],
                  'null' => false
              ])
              ->addColumn('result', 'enum', [
                  'values' => ['win', 'loss', 'tie'],
                  'null' => false
              ])
              ->addColumn('coins_won', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              ->addColumn('dice_earned', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              ->addColumn('game_duration_seconds', 'integer', ['limit' => 5, 'signed' => false, 'null' => true])
              ->addColumn('created_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP'
              ])
              // Foreign Key constraint
              ->addForeignKey('user_id', 'users', 'user_id', [
                  'delete'=> 'CASCADE', 
                  'update'=> 'CASCADE'
              ])
              // Optional: Index on user_id for quick lookup of match history
              ->addIndex(['user_id']);

        $table->create();
    }
}
