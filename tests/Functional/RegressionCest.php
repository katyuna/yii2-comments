<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Comment;
use app\tests\Support\FunctionalTester;
use Yii;

/**
 * Regression tests for bugs found earlier, in-process part (checklist R-01, R-05).
 *
 * @group regression
 */
final class RegressionCest
{
    /**
     * R-01: the login page renders.
     *
     * Bug: an unclosed comment in the Yii2 template's LoginForm.php made PHP fail to parse
     * the class, so /admin/auth/login gave a fatal error.
     *
     * @flowStep 6
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function loginPageOpensWithoutFatalError(FunctionalTester $I): void
    {
        $I->amOnRoute('/admin/auth/login');

        $I->seeResponseCodeIs(200);
        $I->seeElement('[data-testid="login-form"]');
        $I->seeElement('[data-testid="login-username"]');
        $I->seeElement('[data-testid="login-password"]');
    }

    /**
     * R-05: Cyrillic and emoji survive the whole way: form → MySQL → feed, byte for byte.
     *
     * Emoji are 4-byte UTF-8 characters; a utf8 (3-byte) column or connection turns them
     * into "????" or rejects the insert.
     *
     * @flowStep 3
     * @flowStep 4
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function cyrillicAndEmojiAreStoredAndShownUnchanged(FunctionalTester $I): void
    {
        Comment::deleteAll();
        $author = 'Ёжик 🦔';
        $text = "Привет, мир! 😀👍🏽\nВторая строка — «кавычки» и 𝔘𝔫𝔦𝔠𝔬𝔡𝔢";

        $I->amOnRoute('/site/index');
        $I->submitForm('[data-testid="comment-form"]', [
            'CommentForm[author]' => $author,
            'CommentForm[text]' => $text,
        ]);

        $I->seeRecord(Comment::class, ['author' => $author, 'text' => $text]);
        $I->see($author, '[data-testid="comment-item-author"]');
        $I->seeInSource(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $charsets = Yii::$app->db->createCommand(
            'SELECT column_name, character_set_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND character_set_name IS NOT NULL',
            // tableName() is "{{%comment}}"; the raw name is needed for information_schema
            [':table' => Yii::$app->db->schema->getRawTableName(Comment::tableName())],
        )->queryAll(\PDO::FETCH_KEY_PAIR);
        $I->assertSame(['utf8mb4'], array_values(array_unique($charsets)), 'All text columns must be utf8mb4');
    }
}
