<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * The admin login form.
 */
class LoginForm extends Model
{
    private const REMEMBER_ME_SECONDS = 3600 * 24 * 30;

    // Not typed: a crafted request may send an array, which must fail validation, not crash
    /** @var mixed */
    public $username = '';
    /** @var mixed */
    public $password = '';
    /** @var mixed */
    public $rememberMe = false;

    private ?User $user = null;
    private bool $userLoaded = false;

    /**
     * Validation rules.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            ['username', 'trim'],
            [['username', 'password'], 'required'],
            [['username', 'password'], 'string', 'max' => 255],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Human-readable attribute names for the form.
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'username' => 'Username',
            'password' => 'Password',
            'rememberMe' => 'Remember me',
        ];
    }

    /**
     * Inline validator: checks the username and password pair.
     *
     * @param string $attribute Attribute being validated
     * @return void
     */
    public function validatePassword(string $attribute): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $user = $this->getUser();

        // Same message for a wrong username and a wrong password, so usernames cannot be guessed
        if ($user === null || !$user->validatePassword((string) $this->password)) {
            $this->addError($attribute, 'Incorrect username or password.');
        }
    }

    /**
     * Logs the user in if the form is valid.
     *
     * @return bool Whether the user is logged in
     */
    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $user = $this->getUser();

        return $user !== null
            && Yii::$app->user->login($user, $this->rememberMe ? self::REMEMBER_ME_SECONDS : 0);
    }

    /**
     * Finds the user by the entered username (once per form).
     *
     * @return User|null
     */
    private function getUser(): ?User
    {
        if (!$this->userLoaded) {
            $this->user = User::findByUsername((string) $this->username);
            $this->userLoaded = true;
        }

        return $this->user;
    }
}
