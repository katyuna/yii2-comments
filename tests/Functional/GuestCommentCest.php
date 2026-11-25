<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Comment;
use app\tests\Support\FunctionalTester;
use app\tests\Support\RecordingPublisher;
use Yii;

/**
 * The guest comment form and the public feed over HTTP (checklist F-01 … F-08).
 *
 * Requests are handled by the Yii app inside the test process (Codeception Yii2 module),
 * with CSRF validation on. Each test runs in a DB transaction that is rolled back.
 */
final class GuestCommentCest
{
    private const FORM = '[data-testid="comment-form"]';

    /**
     * Starts each test with an empty feed and a fresh publisher fake.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function _before(FunctionalTester $I): void
    {
        Comment::deleteAll();
        RecordingPublisher::reset();
    }

    /**
     * F-01: a valid comment is saved, the guest is redirected back (Post/Redirect/Get),
     * and reloading the page does not post it again.
     *
     * @flowStep 3
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function validCommentIsSavedAndRedirectPreventsDuplicate(FunctionalTester $I): void
    {
        // Arrange
        $I->amOnRoute('/site/index');
        $I->stopFollowingRedirects();

        // Act
        $I->submitForm(self::FORM, [
            'CommentForm[author]' => 'Anna',
            'CommentForm[text]' => 'Hello from a functional test',
        ]);

        // Assert: 302 back to the feed, one row in app_test
        $I->seeResponseCodeIsRedirection();
        $I->seeRecord(Comment::class, ['author' => 'Anna', 'text' => 'Hello from a functional test']);

        $I->followRedirect();
        $I->seeResponseCodeIs(200);
        // The page after the redirect is a GET: reloading it repeats the GET, not the POST
        $I->amOnRoute('/site/index');
        $I->assertSame(1, $I->grabCommentCount(), 'Reloading the page must not create a duplicate');
    }

    /**
     * F-02: an empty form shows an error under each field and saves nothing.
     *
     * @flowStep 2
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function emptyFormShowsErrorsAndSavesNothing(FunctionalTester $I): void
    {
        $I->amOnRoute('/site/index');

        $I->submitForm(self::FORM, [
            'CommentForm[author]' => '',
            'CommentForm[text]' => '',
        ]);

        $I->seeResponseCodeIs(200);
        $I->see('cannot be blank', '[data-testid="comment-form-author-error"]');
        $I->see('cannot be blank', '[data-testid="comment-form-text-error"]');
        $I->assertSame(0, $I->grabCommentCount());
    }

    /**
     * F-02: what the guest typed stays in the form after a validation error.
     *
     * @flowStep 2
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function enteredValuesStayInTheFormAfterAnError(FunctionalTester $I): void
    {
        $I->amOnRoute('/site/index');

        $I->submitForm(self::FORM, [
            'CommentForm[author]' => 'Anna',
            'CommentForm[text]' => '',
        ]);

        $I->seeInField('[data-testid="comment-form-author"]', 'Anna');
        $I->see('cannot be blank', '[data-testid="comment-form-text-error"]');
    }

    /**
     * F-03: a POST without a CSRF token, or with a wrong one, is rejected with 400.
     *
     * @flowStep 2
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function postWithoutValidCsrfTokenIsRejected(FunctionalTester $I): void
    {
        $comment = ['CommentForm' => ['author' => 'Anna', 'text' => 'No token']];

        $I->sendPostToRoute('site/index', [], $comment, withCsrf: false);
        $I->seeResponseCodeIs(400);

        $I->sendPostToRoute('site/index', [], $comment + ['_csrf' => 'forged-token'], withCsrf: false);
        $I->seeResponseCodeIs(400);

        $I->assertSame(0, $I->grabCommentCount());
    }

    /**
     * F-04: a filled honeypot field saves nothing, and the answer does not tell the bot why.
     *
     * If the page names the hidden field, a bot can learn to leave it empty.
     * Regression: the bug was fixed in fix/honeypot-silent-reject.
     *
     * @group regression
     * @flowStep 2
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function honeypotRejectsSilently(FunctionalTester $I): void
    {
        $I->amOnRoute('/site/index');

        $I->submitForm(self::FORM, [
            'CommentForm[author]' => 'Bot',
            'CommentForm[text]' => 'Buy cheap pills',
            'CommentForm[website]' => 'https://spam.example',
        ]);

        $I->assertSame(0, $I->grabCommentCount(), 'A bot comment must not be saved');
        $I->dontSeeInSource('looks like spam');
        $I->dontSeeElement('[data-testid="comment-form-website"].is-invalid');
    }

    /**
     * F-05: one more POST than the per-minute limit is refused with a message.
     *
     * @flowStep 2
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function postsOverTheRateLimitAreRefused(FunctionalTester $I): void
    {
        $limit = (int) Yii::$app->params['commentRateLimitPerMinute'];

        for ($i = 1; $i <= $limit + 1; $i++) {
            $I->amOnRoute('/site/index');
            $I->submitForm(self::FORM, [
                'CommentForm[author]' => 'Anna',
                'CommentForm[text]' => "Comment {$i}",
            ]);
        }

        $I->see('You are posting too often', '[data-testid="comment-form-text-error"]');
        $I->assertSame($limit, $I->grabCommentCount(), 'Only the allowed number of comments is saved');
    }

    /**
     * F-06: HTML in a comment is shown as text; no script tag reaches the page.
     *
     * @flowStep 4
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function htmlInCommentIsShownAsText(FunctionalTester $I): void
    {
        $I->haveComment('<b>Mallory</b>', '<script>alert(1)</script> and <i>italic</i>');

        $I->amOnRoute('/site/index');

        $I->dontSeeInSource('<script>alert(1)</script>');
        $I->dontSeeInSource('<i>italic</i>');
        $I->dontSeeInSource('<b>Mallory</b>');
        $I->seeInSource('&lt;script&gt;alert(1)&lt;/script&gt;');
        $I->see('<script>alert(1)</script> and <i>italic</i>', '[data-testid="comment-item-text"]');
        $I->see('<b>Mallory</b>', '[data-testid="comment-item-author"]');
    }

    /**
     * F-07: the feed shows newest first, the counter matches, every card has author, date and text.
     *
     * @flowStep 4
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function feedShowsCompleteCardsNewestFirst(FunctionalTester $I): void
    {
        $now = time();
        $I->haveComment('Oldest', 'first', $now - 300);
        $I->haveComment('Newest', 'third', $now);
        $I->haveComment('Middle', 'second', $now - 100);

        $I->amOnRoute('/site/index');

        $I->assertSame(['third', 'second', 'first'], $I->grabMultiple('[data-testid="comment-item-text"]'));
        $I->assertSame(['Newest', 'Middle', 'Oldest'], $I->grabMultiple('[data-testid="comment-item-author"]'));
        $I->see('3', '[data-testid="comment-feed-count"]');
        $I->seeNumberOfElements('[data-testid="comment-item"]', 3);
        $I->seeNumberOfElements('[data-testid="comment-item-date"]', 3);
        foreach ($I->grabMultiple('[data-testid="comment-item-date"]') as $date) {
            $I->assertNotSame('', trim($date), 'Every card shows a date');
        }
    }

    /**
     * F-08: more comments than one page holds → a pager; page 2 shows the rest,
     * nothing is lost or repeated.
     *
     * @flowStep 4
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function paginationNeitherLosesNorRepeatsComments(FunctionalTester $I): void
    {
        $total = 25;
        $now = time();
        for ($i = 1; $i <= $total; $i++) {
            $I->haveComment('Author', "Comment {$i}", $now - $i);
        }

        $I->amOnRoute('/site/index');
        $I->seeElement('[data-testid="comment-feed-pager"]');
        $firstPage = $I->grabMultiple('[data-testid="comment-item-text"]');

        $I->amOnRoute('/site/index', ['page' => 2]);
        $secondPage = $I->grabMultiple('[data-testid="comment-item-text"]');

        $I->assertNotEmpty($secondPage, 'Page 2 must show the rest');
        $I->assertSame([], array_intersect($firstPage, $secondPage), 'No comment may appear on both pages');
        $I->assertCount($total, array_merge($firstPage, $secondPage), 'No comment may be lost');
        $I->assertSame('Comment 1', $firstPage[0], 'The newest comment opens page 1');
    }
}
