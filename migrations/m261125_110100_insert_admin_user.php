<?php

declare(strict_types=1);

use yii\base\InvalidConfigException;
use yii\db\Migration;

/**
 * Creates the admin user from the ADMIN_USERNAME / ADMIN_PASSWORD env variables.
 *
 * To change the password later, edit .env and run `make admin-sync`.
 */
class m261125_110100_insert_admin_user extends Migration
{
    /**
     * Inserts the admin user with a hashed password.
     *
     * @return void
     * @throws InvalidConfigException If the env variables are not set
     */
    public function safeUp(): void
    {
        $username = (string) getenv('ADMIN_USERNAME');
        $password = (string) getenv('ADMIN_PASSWORD');

        if ($username === '' || $password === '') {
            throw new InvalidConfigException('Set ADMIN_USERNAME and ADMIN_PASSWORD in .env before running migrations.');
        }

        $security = Yii::$app->security;
        $now = time();

        $this->insert('{{%user}}', [
            'username' => $username,
            'password_hash' => $security->generatePasswordHash($password),
            'auth_key' => $security->generateRandomString(32),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Deletes the admin user.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->delete('{{%user}}', ['username' => (string) getenv('ADMIN_USERNAME')]);
    }
}
