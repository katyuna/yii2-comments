<?php

declare(strict_types=1);

namespace app\tests\Smoke;

use app\tests\Support\SmokeTester;
use PDO;

/**
 * Smoke checks of the running test stand (checklist S-01 … S-06).
 *
 * They answer one question fast: is the deployed app usable at all? Every request goes
 * through nginx and php-fpm like a real visitor's, so a broken container, config or
 * migration shows up here before the longer suites run.
 *
 * @group smoke
 */
final class AppIsAliveCest
{
    /**
     * S-01: the home page answers 200 with the comment form and the feed.
     *
     * @flowStep 1
     * @flowStep 4
     * @param SmokeTester $I Actor
     * @return void
     */
    public function homePageShowsFormAndFeed(SmokeTester $I): void
    {
        $I->amOnPage('/');

        $I->seeResponseCodeIs(200);
        $I->seeElement('[data-testid="comment-form"]');
        $I->seeElement('[data-testid="comment-feed"]');
    }

    /**
     * S-02: a guest posts a comment and sees it first in the feed after the redirect.
     *
     * @flowStep 1
     * @flowStep 2
     * @flowStep 3
     * @flowStep 4
     * @param SmokeTester $I Actor
     * @return void
     */
    public function guestCanPostAComment(SmokeTester $I): void
    {
        $text = 'Smoke test ' . bin2hex(random_bytes(4));
        $I->amOnPage('/');

        $I->submitForm('[data-testid="comment-form"]', [
            'CommentForm[author]' => 'Smoke',
            'CommentForm[text]' => $text,
        ]);

        $I->seeResponseCodeIs(200);
        // Strict CSS locator: a plain "[...]" string would be read as a regex by grabTextFrom()
        $firstText = $I->grabTextFrom(['css' => '[data-testid="comment-item-text"]']);
        $I->assertSame($text, $firstText, 'The new comment must be first');
        // The guest posted on "/", so the redirect must bring them back to the same address
        $I->seeCurrentUrlEquals('/');
    }

    /**
     * S-03: the admin area is closed to guests and the login form opens.
     *
     * @flowStep 6
     * @param SmokeTester $I Actor
     * @return void
     */
    public function adminAreaRedirectsGuestToLogin(SmokeTester $I): void
    {
        $I->stopFollowingRedirects();
        $I->amOnPage('/admin/comment/index');
        $I->seeResponseCodeIs(302);

        $I->startFollowingRedirects();
        $I->amOnPage('/admin/comment/index');
        $I->seeCurrentUrlEquals('/admin/auth/login');
        $I->seeElement('[data-testid="login-form"]');
    }

    /**
     * S-04: the admin from ADMIN_USERNAME / ADMIN_PASSWORD (.env) logs in and sees the list.
     *
     * This also proves that the migration created the admin and php-fpm sees .env.
     *
     * @flowStep 6
     * @param SmokeTester $I Actor
     * @return void
     */
    public function adminFromEnvCanLogIn(SmokeTester $I): void
    {
        $I->amOnPage('/admin/auth/login');

        $I->submitForm('[data-testid="login-form"]', [
            'LoginForm[username]' => (string) getenv('ADMIN_USERNAME'),
            'LoginForm[password]' => (string) getenv('ADMIN_PASSWORD'),
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeElement('[data-testid="admin-comment-list"]');
        $I->seeElement('[data-testid="nav-logout"]');
    }

    /**
     * S-05: WebSocket connections pass through nginx to Centrifugo (101 Switching Protocols),
     * and the Centrifugo HTTP API accepts the app's API key.
     *
     * @flowStep 5
     * @flowStep 7
     * @param SmokeTester $I Actor
     * @return void
     */
    public function webSocketAndCentrifugoApiAreUp(SmokeTester $I): void
    {
        $status = $I->grabWebSocketHandshakeStatus('http://nginx-test');
        $I->assertStringContainsString('101', $status, "Expected 101 Switching Protocols, got: {$status}");

        $api = $I->callCentrifugoApi('info');
        $I->assertSame(200, $api['status']);
        $I->assertArrayNotHasKey('error', $api['body'], 'The API key must be accepted');
        $I->assertNotEmpty($api['body']['result']['nodes'] ?? [], 'Centrifugo reports its nodes');
    }

    /**
     * S-06: migrations are applied in both databases: the comment table exists and
     * the admin user is there.
     *
     * @flowStep 3
     * @param SmokeTester $I Actor
     * @return void
     */
    public function migrationsAreAppliedInBothDatabases(SmokeTester $I): void
    {
        foreach ([getenv('DB_NAME') ?: 'app', getenv('DB_TEST_NAME') ?: 'app_test'] as $database) {
            $pdo = $I->connectToDatabase($database);

            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $I->assertContains('comment', $tables, "Table comment is missing in {$database}");
            $I->assertContains('user', $tables, "Table user is missing in {$database}");

            $admin = $pdo->prepare('SELECT COUNT(*) FROM user WHERE username = ?');
            $admin->execute([(string) getenv('ADMIN_USERNAME')]);
            $I->assertSame(1, (int) $admin->fetchColumn(), "The admin user is missing in {$database}");
        }
    }
}
