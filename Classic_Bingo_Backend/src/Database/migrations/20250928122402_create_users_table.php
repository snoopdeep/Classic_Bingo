<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    /**
     * The `change` method is used for reversible migrations.
     * Phinx automatically knows how to reverse these actions. || it will use drop 
     */
    public function change(): void
    {
        // Get the table object
        $table = $this->table('users', [
            'id' => false,                      // Disable the default auto-incrementing 'id' column
            'primary_key' => ['user_id']        // Set our own primary key
        ]);

        // Define the table columns
        $table->addColumn('user_id', 'string', ['limit' => 36, 'null' => false])
              ->addColumn('user_name', 'string', ['limit' => 16])
              ->addColumn('avatar_id', 'enum', [
                  'values' => ['avatar_01', 'avatar_02', 'avatar_03', 'avatar_04', 'avatar_05'],
                  'default' => 'avatar_01'
              ])
              ->addColumn('role', 'enum', [
                  'values' => ['user', 'admin'],
                  'default' => 'user'
              ])
              ->addColumn('refresh_token', 'string', [
                  'limit' => 255, 
                  'null' => true,    
                  'default' => null
              ])
            //   ->addColumn('api_secret_key', 'string', ['limit' => 64, 'null' => false])
              
              ->addColumn('created_at', 'timestamp', [
                  'default' => 'CURRENT_TIMESTAMP'
              ])
              ->addColumn('last_login', 'timestamp', [
                  'null' => true,
                  'update' => 'CURRENT_TIMESTAMP' 
              ])
              ->addIndex(['user_name'], ['unique' => true]); // Add a unique index

        // Create the table
        $table->create();
    }
}