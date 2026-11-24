<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the `comment` table for comments posted by guests.
 */
class m261125_100000_create_comment_table extends Migration
{
    /**
     * Creates the table and its indexes.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->createTable('{{%comment}}', [
            'id' => $this->primaryKey(),
            'author' => $this->string(64)->notNull(),
            'text' => $this->text()->notNull(),
            // Kept for the per-IP rate limit; 45 chars fit an IPv6 address
            'ip' => $this->string(45)->notNull()->defaultValue(''),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->createIndex('idx-comment-created_at', '{{%comment}}', 'created_at');
        $this->createIndex('idx-comment-ip-created_at', '{{%comment}}', ['ip', 'created_at']);
    }

    /**
     * Drops the table.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->dropTable('{{%comment}}');
    }
}
