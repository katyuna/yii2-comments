<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Comment;
use app\tests\Support\FunctionalTester;
use app\tests\Support\Helper\Functional;

/**
 * Admin login, logout and access control over HTTP (checklist F-09, F-10, F-11, F-19).
 *
 * @flowStep 6
 */
final class AdminAccessCest
{
    /** The login page URL (test app URLs look like /index-test.php?r=admin%2Fauth%2Flogin) */
    private const LOGIN_URL = '~r=admin%2Fauth%2Flogin~';

    private const TOKEN_URL = '/index-test.php?r=admin%2Frealtime%2Ftoken';

    /**
     * F-09: a wrong password keeps the guest on the login page with an error, and gives no session.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function wrongPasswordDoesNotLogIn(FunctionalTester $I): void
    {
        // Arrange
        $I->haveAdmin();
        $I->amOnRoute('/admin/auth/login');

        // Act
        $I->submitForm('[data-testid="login-form"]', [
            'LoginForm[username]' => Functional::ADMIN_USERNAME,
            'LoginForm[password]' => 'wrong password',
        ]);

        // Assert
        $I->seeResponseCodeIs(200);
        $I->seeElement('[data-testid="login-form"]');
        $I->see('Incorrect username or password.', '[data-testid="login-password-error"]');
        $I->dontSeeElement('[data-testid="nav-logout"]');

        $I->amOnRoute('/admin/comment/index');
        $I->seeCurrentUrlMatches(self::LOGIN_URL);
    }

    /**
     * F-10: after logout the session is over and the admin area asks to log in again.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function logoutEndsTheSession(FunctionalTester $I): void
    {
        $I->amLoggedInAsAdminViaForm();
        $I->seeElement('[data-testid="admin-comment-list"]');

        // The logout link is turned into a POST form by yii.js; here the POST is sent directly
        $I->sendPostToRoute('admin/auth/logout');

        $I->amOnRoute('/admin/comment/index');
        $I->seeCurrentUrlMatches(self::LOGIN_URL);
        $I->dontSeeElement('[data-testid="nav-logout"]');
    }

    /**
     * F-11: a guest cannot edit or delete with a direct POST, even with a valid CSRF token.
     *
     * @flowStep 9
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function guestCannotModerateWithDirectRequests(FunctionalTester $I): void
    {
        $id = $I->haveComment('Anna', 'Original text');

        // Redirects are followed: a guest must end up on the login form
        $I->sendPostToRoute('admin/comment/update', ['id' => $id], ['Comment' => ['text' => 'Hacked']]);
        $I->seeCurrentUrlMatches(self::LOGIN_URL);
        $I->seeElement('[data-testid="login-form"]');

        $I->sendPostToRoute('admin/comment/delete', ['id' => $id]);
        $I->seeCurrentUrlMatches(self::LOGIN_URL);

        $I->seeRecord(Comment::class, ['id' => $id, 'text' => 'Original text']);
    }

    /**
     * F-19: the Centrifugo token endpoint answers 403 to a guest and a signed JWT to the admin.
     *
     * The browser calls it with X-Requested-With, so a guest gets 403 instead of a redirect
     * and the WebSocket client stops reconnecting.
     *
     * @flowStep 7
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function realtimeTokenOnlyForAdmin(FunctionalTester $I): void
    {
        $I->sendAjaxGetRequest(self::TOKEN_URL);
        $I->seeResponseCodeIs(403);

        $I->amLoggedInAsAdminViaForm();
        $I->sendAjaxGetRequest(self::TOKEN_URL);

        $I->seeResponseCodeIs(200);
        $body = json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR);
        $I->assertIsString($body['token'] ?? null);
        $I->assertCount(3, explode('.', $body['token']), 'The token must be a JWT (header.claims.signature)');
    }
}
