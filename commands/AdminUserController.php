<?php

declare(strict_types=1);

namespace app\commands;

use app\models\User;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console commands for the admin account.
 */
class AdminUserController extends Controller
{
    /**
     * Creates the admin from ADMIN_USERNAME / ADMIN_PASSWORD, or updates the password if the admin exists.
     *
     * Usage: php yii admin-user/sync (or `make admin-sync`)
     *
     * @return int Exit code
     */
    public function actionSync(): int
    {
        $username = (string) getenv('ADMIN_USERNAME');
        $password = (string) getenv('ADMIN_PASSWORD');

        if ($username === '' || $password === '') {
            $this->stderr("Set ADMIN_USERNAME and ADMIN_PASSWORD in .env first.\n");

            return ExitCode::CONFIG;
        }

        $user = User::findByUsername($username) ?? new User(['username' => $username]);
        $isNew = $user->isNewRecord;
        $user->setPassword($password);
        // A new key also ends "remember me" logins made with the old password
        $user->generateAuthKey();

        if (!$user->save()) {
            $this->stderr('Could not save the admin: ' . json_encode($user->getErrors()) . "\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(($isNew ? 'Created' : 'Updated') . " admin \"{$username}\".\n");

        return ExitCode::OK;
    }
}
