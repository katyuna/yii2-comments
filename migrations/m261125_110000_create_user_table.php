<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the `user` table for admin accounts.
 */
class m261125_110000_create_user_table extends Migration
{
    /**
     * Creates the table.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(64)->notNull()->unique(),
            // Only the hash is stored, never the password itself
            'password_hash' => $this->string(255)->notNull(),
            // Secret for the "remember me" cookie
            'auth_key' => $this->string(32)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }

    /**
     * Drops the table.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->dropTable('{{%user}}');
    }
}
