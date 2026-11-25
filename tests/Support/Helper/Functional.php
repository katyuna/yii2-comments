<?php

declare(strict_types=1);

namespace app\tests\Support\Helper;

use app\models\Comment;
use app\models\User;
use Codeception\Module;
use Codeception\Module\Yii2;
use Yii;
use yii\helpers\Url;

/**
 * Shared steps for functional tests (a Codeception helper module).
 *
 * Every public method becomes an action of `$I` in the Cests:
 * - test data: comments with a chosen date, the admin account;
 * - logging in through the real login form;
 * - POST requests that have no form on the page (logout, crafted requests).
 */
final class Functional extends Module
{
    public const ADMIN_USERNAME = 'functional-admin';
    public const ADMIN_PASSWORD = 'functional-secret';

    /**
     * Inserts a comment directly into the DB.
     *
     * TimestampBehavior would set "now", so a direct insert is used to control the date.
     *
     * @param string $author Name
     * @param string $text Text
     * @param int|null $createdAt Unix time, "now" by default
     * @return int Id of the new comment
     */
    public function haveComment(string $author, string $text, ?int $createdAt = null): int
    {
        $createdAt ??= time();
        Yii::$app->db->createCommand()->insert(Comment::tableName(), [
            'author' => $author,
            'text' => $text,
            'ip' => '127.0.0.1',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->execute();

        return (int) Yii::$app->db->getLastInsertID();
    }

    /**
     * Number of comments in the DB.
     *
     * @return int
     */
    public function grabCommentCount(): int
    {
        return (int) Comment::find()->count();
    }

    /**
     * Creates the admin account the tests log in with.
     *
     * @return int Id of the admin
     */
    public function haveAdmin(): int
    {
        $user = new User();
        $user->username = self::ADMIN_USERNAME;
        $user->setPassword(self::ADMIN_PASSWORD);
        $user->generateAuthKey();
        $user->save(false);

        return $user->id;
    }

    /**
     * Creates the admin and logs in through the login form, like a person would.
     *
     * @return void
     */
    public function amLoggedInAsAdminViaForm(): void
    {
        $this->haveAdmin();
        $yii2 = $this->yii2();
        $yii2->amOnRoute('/admin/auth/login');
        $yii2->submitForm('[data-testid="login-form"]', [
            'LoginForm[username]' => self::ADMIN_USERNAME,
            'LoginForm[password]' => self::ADMIN_PASSWORD,
        ]);
    }

    /**
     * Sends a normal (non-AJAX) POST request to a Yii route, as a browser form would.
     *
     * @param string $route Route, e.g. "admin/comment/delete"
     * @param array<string, mixed> $query Query parameters, e.g. ['id' => 5]
     * @param array<string, mixed> $body POST body
     * @param bool $withCsrf Add a valid CSRF token, like a form rendered by Yii does
     * @return void
     */
    public function sendPostToRoute(string $route, array $query = [], array $body = [], bool $withCsrf = true): void
    {
        if ($withCsrf) {
            $body['_csrf'] = $this->grabCsrfToken();
        }

        $this->yii2()->_loadPage('POST', Url::to(array_merge(['/' . $route], $query)), $body);
    }

    /**
     * Opens the public page and returns the CSRF token from its meta tag.
     *
     * Opening a page also sets the CSRF cookie the token is checked against.
     *
     * @return string
     */
    public function grabCsrfToken(): string
    {
        $yii2 = $this->yii2();
        $yii2->amOnRoute('/site/index');

        return (string) $yii2->grabAttributeFrom('meta[name="csrf-token"]', 'content');
    }

    /**
     * Returns the Yii2 module that runs the requests.
     *
     * @return Yii2
     */
    private function yii2(): Yii2
    {
        /** @var Yii2 $module */
        $module = $this->getModule('Yii2');

        return $module;
    }
}
