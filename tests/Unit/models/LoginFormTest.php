<?php

declare(strict_types=1);

namespace app\tests\Unit\models;

use app\models\LoginForm;
use app\models\User;
use Codeception\Test\Unit;
use Yii;

/**
 * Admin login form (checklist U-14).
 *
 * @flowStep 6
 */
final class LoginFormTest extends Unit
{
    private const USERNAME = 'unit-test-admin';
    private const PASSWORD = 'correct horse battery staple';

    /**
     * Creates the admin the tests log in as.
     *
     * @return void
     */
    protected function _before(): void
    {
        $user = new User();
        $user->username = self::USERNAME;
        $user->setPassword(self::PASSWORD);
        $user->generateAuthKey();
        $this->assertTrue($user->save());
    }

    /**
     * Logs out after each test, so a login does not leak into the next one.
     *
     * @return void
     */
    protected function _after(): void
    {
        Yii::$app->user->logout();
    }

    /**
     * U-14: a wrong username and a wrong password give the same single message,
     * so an attacker cannot learn which usernames exist.
     *
     * @return void
     */
    public function testWrongUsernameAndWrongPasswordGiveTheSameError(): void
    {
        $wrongUsername = $this->form('no-such-user', self::PASSWORD);
        $wrongPassword = $this->form(self::USERNAME, 'wrong password');

        $this->assertFalse($wrongUsername->login());
        $this->assertFalse($wrongPassword->login());

        $this->assertSame($wrongUsername->getErrors(), $wrongPassword->getErrors());
        $this->assertSame(['password' => ['Incorrect username or password.']], $wrongPassword->getErrors());
        $this->assertTrue(Yii::$app->user->isGuest);
    }

    /**
     * U-14 (positive case): the right pair logs the admin in.
     *
     * @return void
     */
    public function testRightUsernameAndPasswordLogIn(): void
    {
        $form = $this->form(self::USERNAME, self::PASSWORD);

        $this->assertTrue($form->login());
        $this->assertFalse(Yii::$app->user->isGuest);
        $this->assertSame(self::USERNAME, Yii::$app->user->identity?->username);
    }

    /**
     * Builds a login form from request-like data.
     *
     * @param string $username Username
     * @param string $password Password
     * @return LoginForm
     */
    private function form(string $username, string $password): LoginForm
    {
        $form = new LoginForm();
        $form->load(['username' => $username, 'password' => $password], '');

        return $form;
    }
}
