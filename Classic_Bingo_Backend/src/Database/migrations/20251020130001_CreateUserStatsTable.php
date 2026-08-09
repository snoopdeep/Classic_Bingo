<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserStatsTable extends AbstractMigration
{
    /**
     * Creates the user_stats table for aggregated performance metrics.
     * The primary key is `id`, which is also the foreign key to users.user_id.
     */
    public function change(): void
    {
        // Set user_id as the primary key
        $table = $this->table('user_stat', [
            'id' => false,
            'primary_key' => ['id']
        ]);

        $table->addColumn('id', 'string', ['limit' => 36, 'null' => false]) // PK and FK
              ->addColumn('total_games', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              ->addColumn('total_wins', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              ->addColumn('total_losses', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              ->addColumn('current_win_streak', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              ->addColumn('best_win_streak', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              // total_coins_won is omitted as per your request for a single coin balance
              ->addColumn('stats_updated_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP', 
                  'update' => 'CURRENT_TIMESTAMP' 
              ])
              // Foreign Key constraint
              ->addForeignKey('id', 'users', 'user_id', [
                  'delete'=> 'CASCADE', 
                  'update'=> 'CASCADE'
              ]);

        $table->create();
    }
}
