<?php

declare(strict_types=1);

namespace app\tests\Smoke;

use app\tests\Support\SmokeTester;

/**
 * Regression tests that need the real containers (checklist R-02, R-03, R-06).
 *
 * These bugs lived in the environment (php-fpm, file permissions, test isolation),
 * not in PHP classes, so only real HTTP through nginx can catch them again.
 * Run with `make test-regress` (they are not part of `make test-smoke`).
 *
 * @group regression
 */
final class EnvironmentRegressionCest
{
    /**
     * R-02: php-fpm passes .env variables to PHP: the admin from ADMIN_* logs in, and
     * the WebSocket token (signed with CENTRIFUGO_TOKEN_SECRET) is issued.
     *
     * Bug: php-fpm hides environment variables by default (clear_env = yes), so getenv()
     * returned false inside the web app; fixed with clear_env = no (docker/php/zz-env.conf).
     *
     * @flowStep 6
     * @flowStep 7
     * @param SmokeTester $I Actor
     * @return void
     */
    public function phpFpmSeesEnvVariables(SmokeTester $I): void
    {
        $I->amOnPage('/admin/auth/login');
        $I->submitForm('[data-testid="login-form"]', [
            'LoginForm[username]' => (string) getenv('ADMIN_USERNAME'),
            'LoginForm[password]' => (string) getenv('ADMIN_PASSWORD'),
        ]);
        $I->seeElement('[data-testid="admin-comment-list"]');

        $I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest');
        $I->amOnPage('/admin/realtime/token');

        $I->seeResponseCodeIs(200);
        $token = json_decode($I->grabPageSource(), true)['token'] ?? '';
        $I->assertCount(3, explode('.', (string) $token), 'php-fpm must see CENTRIFUGO_TOKEN_SECRET to sign a JWT');
    }

    /**
     * R-03: after a console command the debug panel of the dev app still opens.
     *
     * Bug: the debug module was also enabled in the console config; console commands run
     * as root and created runtime/debug owned by root, so php-fpm could not write there
     * and /debug answered 404.
     *
     * @param SmokeTester $I Actor
     * @return void
     */
    public function debugPanelOpensAfterConsoleCommands(SmokeTester $I): void
    {
        // Root cause: the console app must not load the debug module at all.
        // (runtime/debug may already exist and be writable, which alone would hide the bug)
        $consoleConfig = require codecept_root_dir('config/console.php');
        $I->assertArrayNotHasKey('debug', $consoleConfig['modules'] ?? []);
        $I->assertNotContains('debug', $consoleConfig['bootstrap'] ?? []);

        // Any console command used to trigger the bug; "help" changes nothing else
        exec('php ' . escapeshellarg(codecept_root_dir('yii')) . ' help 2>&1', $output, $exitCode);
        $I->assertSame(0, $exitCode, implode("\n", $output));

        // The dev app (container "nginx", YII_ENV=dev), not the test stand.
        // One page view makes php-fpm write to runtime/debug (the write the bug broke);
        // without it a fresh clone has no debug data and the panel answers 500.
        $I->assertSame(200, $I->grabHttpStatus('http://nginx/', 30));
        // The panel lists every logged request, so on a busy dev app it can take a while
        $I->assertSame(200, $I->grabHttpStatus('http://nginx/debug/default/index', 30));
    }

    /**
     * R-06: on the test stand one IP may post more than the default limit (5/min), so tests
     * that post comments do not fail at random.
     *
     * Bug: in Docker every browser reaches the app from the same gateway IP, so all tests
     * shared one rate limit; the test stand now raises COMMENT_RATE_LIMIT_PER_MINUTE.
     *
     * @flowStep 2
     * @param SmokeTester $I Actor
     * @return void
     */
    public function testStandDoesNotRateLimitTests(SmokeTester $I): void
    {
        $tag = bin2hex(random_bytes(4));

        for ($i = 1; $i <= 7; $i++) {
            $I->amOnPage('/');
            $I->submitForm('[data-testid="comment-form"]', [
                'CommentForm[author]' => 'Regression',
                'CommentForm[text]' => "R-06 {$tag} #{$i}",
            ]);
            $I->dontSee('You are posting too often');
        }

        $I->see("R-06 {$tag} #7", '[data-testid="comment-item-text"]');
    }
}
