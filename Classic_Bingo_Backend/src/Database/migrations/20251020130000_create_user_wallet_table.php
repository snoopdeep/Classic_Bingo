<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserWalletTable extends AbstractMigration
{
    /**
     * Creates the user_wallet table for financial data (coins, dice).
     * The `id` is the auto-increment primary key, and `user_id` is a unique foreign key.
     */
    public function change(): void
    {
        // Phinx defaults to 'id' auto-incrementing primary key if 'id' => true or omitted
        $table = $this->table('user_wallet');

        $table->addColumn('user_id', 'string', ['limit' => 36, 'null' => false])
              ->addColumn('bingo_coins', 'integer', ['limit' => 10, 'signed' => false, 'default' => 500])
              ->addColumn('dice', 'integer', ['limit' => 10, 'signed' => false, 'default' => 0])
              ->addColumn('updated_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP', 
                  'update' => 'CURRENT_TIMESTAMP' 
              ])
              ->addIndex(['user_id'], ['unique' => true]) // Enforce one wallet per user
              // Correct Foreign Key constraint
              ->addForeignKey('user_id', 'users', 'user_id', [
                  'delete'=> 'CASCADE', 
                  'update'=> 'CASCADE'
              ]);

        $table->create();
    }
}
