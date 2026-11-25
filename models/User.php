<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Admin account. Implements IdentityInterface, so Yii::$app->user can log it in.
 *
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string $auth_key
 * @property int $created_at
 * @property int $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    /**
     * Returns the DB table name.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    /**
     * Fills created_at / updated_at automatically on save.
     *
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * Finds a user by ID (used to restore the login from the session).
     *
     * @param int|string $id User ID
     * @return self|null
     */
    public static function findIdentity($id): ?self
    {
        return static::findOne(['id' => $id]);
    }

    /**
     * API tokens are not used in this app.
     *
     * @param mixed $token Access token
     * @param mixed $type Token type
     * @return self|null Always null
     */
    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        return null;
    }

    /**
     * Finds a user by username.
     *
     * @param string $username Username
     * @return self|null
     */
    public static function findByUsername(string $username): ?self
    {
        return static::findOne(['username' => $username]);
    }

    /**
     * Returns the user ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Returns the key that protects the "remember me" cookie.
     *
     * @return string
     */
    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * Checks the key from the "remember me" cookie.
     *
     * @param mixed $authKey Key from the cookie
     * @return bool
     */
    public function validateAuthKey($authKey): bool
    {
        return is_string($authKey) && hash_equals($this->auth_key, $authKey);
    }

    /**
     * Checks a password against the stored hash.
     *
     * @param string $password Plain password from the login form
     * @return bool
     */
    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Stores the hash of a new password.
     *
     * @param string $password Plain password
     * @return void
     */
    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates a new "remember me" key; this also logs the user out of other devices.
     *
     * @return void
     */
    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString(32);
    }
}
