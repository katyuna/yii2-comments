<?php

declare(strict_types=1);

namespace app\tests\Unit\models;

use app\models\User;
use Codeception\Test\Unit;

/**
 * Admin account: password check and storage (checklist U-13).
 *
 * @flowStep 6
 */
final class UserTest extends Unit
{
    private const PASSWORD = 'correct horse battery staple';

    /**
     * U-13: the right password is accepted, a wrong one is not.
     *
     * @return void
     */
    public function testValidatePasswordAcceptsOnlyTheRightPassword(): void
    {
        $user = $this->createUser();

        $this->assertTrue($user->validatePassword(self::PASSWORD));
        $this->assertFalse($user->validatePassword('wrong password'));
        // An empty password is not checked here: Yii's Security throws on it by contract,
        // and LoginForm never gets that far ("required" rule, see LoginFormTest)
    }

    /**
     * U-13: the DB keeps a bcrypt hash, never the password itself.
     *
     * @return void
     */
    public function testDatabaseStoresHashNotPassword(): void
    {
        $user = $this->createUser();

        $stored = User::findOne($user->id);

        $this->assertNotNull($stored);
        $this->assertNotSame(self::PASSWORD, $stored->password_hash);
        $this->assertStringNotContainsString(self::PASSWORD, $stored->password_hash);
        // $2y$ is bcrypt, the default of Yii::$app->security->generatePasswordHash()
        $this->assertStringStartsWith('$2y$', $stored->password_hash);
        $this->assertTrue($stored->validatePassword(self::PASSWORD), 'The hash must still check the password');
    }

    /**
     * Saves a user the same way the admin migration does.
     *
     * @return User
     */
    private function createUser(): User
    {
        $user = new User();
        $user->username = 'unit-test-admin';
        $user->setPassword(self::PASSWORD);
        $user->generateAuthKey();
        $this->assertTrue($user->save(), 'Test user must be saved');

        return $user;
    }
}
