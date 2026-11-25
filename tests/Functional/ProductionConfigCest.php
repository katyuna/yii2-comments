<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\tests\Support\FunctionalTester;

/**
 * Debug tools are not part of a non-dev web config (checklist F-20).
 *
 * The debug panel shows the whole config, SQL queries and request data; Gii can write files.
 * config/web.php adds them only when YII_ENV is "dev". Tests run with YII_ENV "test",
 * so loading the real web config here shows what any non-dev environment (prod) gets.
 */
final class ProductionConfigCest
{
    /**
     * F-20: outside dev the web app has no debug and gii modules, so /debug and /gii give 404.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function debugAndGiiAreOffOutsideDev(FunctionalTester $I): void
    {
        $I->assertFalse(YII_ENV_DEV, 'This check must run outside the dev environment');

        $config = require codecept_root_dir('config/web.php');

        $I->assertArrayNotHasKey('debug', $config['modules']);
        $I->assertArrayNotHasKey('gii', $config['modules']);
        $I->assertNotContains('debug', $config['bootstrap']);
        $I->assertNotContains('gii', $config['bootstrap']);
    }

    /**
     * F-20: the debug routes really answer 404 in the test app (no module to handle them).
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function debugRoutesAreNotFound(FunctionalTester $I): void
    {
        $I->amOnPage('/index-test.php?r=debug%2Fdefault%2Findex');
        $I->seeResponseCodeIs(404);

        $I->amOnPage('/index-test.php?r=gii');
        $I->seeResponseCodeIs(404);
    }
}
