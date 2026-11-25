<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\Comment;
use app\tests\Support\FunctionalTester;

/**
 * Admin comment list, edit and delete over HTTP (checklist F-12 … F-18).
 *
 * Every test logs in through the real login form first.
 *
 * @flowStep 9
 */
final class AdminCrudCest
{
    /**
     * Empties the comment table and logs the admin in.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function _before(FunctionalTester $I): void
    {
        Comment::deleteAll();
        $I->amLoggedInAsAdminViaForm();
    }

    /**
     * F-12: the admin list shows every comment, newest first, each with Edit and Delete.
     *
     * @flowStep 6
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function listShowsAllCommentsNewestFirstWithActions(FunctionalTester $I): void
    {
        $now = time();
        $I->haveComment('Oldest', 'first', $now - 200);
        $I->haveComment('Newest', 'third', $now);
        $I->haveComment('Middle', 'second', $now - 100);

        $I->amOnRoute('/admin/comment/index');

        $I->assertSame(['third', 'second', 'first'], $I->grabMultiple('[data-testid="admin-comment-text"]'));
        $I->seeNumberOfElements('[data-testid="admin-comment-row"]', 3);
        $I->seeNumberOfElements('[data-testid="admin-comment-edit"]', 3);
        $I->seeNumberOfElements('[data-testid="admin-comment-delete"]', 3);
        $I->see('3', '[data-testid="admin-comment-count"]');
    }

    /**
     * F-13: a valid edit is saved and the public feed shows the new text.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function validEditIsSavedAndVisibleInFeed(FunctionalTester $I): void
    {
        $id = $I->haveComment('Anna', 'Original text');

        $I->amOnRoute('/admin/comment/update', ['id' => $id]);
        $I->submitForm('[data-testid="admin-comment-form"]', [
            'Comment[author]' => 'Anna K.',
            'Comment[text]' => 'Edited text',
        ]);

        $I->seeCurrentUrlMatches('~r=admin%2Fcomment%2Findex~');
        $I->see("Comment #{$id} has been updated.");
        $I->seeRecord(Comment::class, ['id' => $id, 'author' => 'Anna K.', 'text' => 'Edited text']);

        $I->amOnRoute('/site/index');
        $I->see('Edited text', '[data-testid="comment-item-text"]');
        $I->dontSee('Original text');
    }

    /**
     * F-14: the edit form keeps the same rules as the guest form; invalid data changes nothing.
     *
     * @dataProvider invalidEdits
     * @param FunctionalTester $I Actor
     * @param \Codeception\Example<string, string> $example Invalid text and the expected error
     * @return void
     */
    public function invalidEditShowsErrorAndChangesNothing(FunctionalTester $I, \Codeception\Example $example): void
    {
        $id = $I->haveComment('Anna', 'Original text');

        $I->amOnRoute('/admin/comment/update', ['id' => $id]);
        $I->submitForm('[data-testid="admin-comment-form"]', [
            'Comment[author]' => 'Anna',
            'Comment[text]' => $example['text'],
        ]);

        $I->seeElement('[data-testid="admin-comment-form"]');
        $I->see($example['error'], '[data-testid="admin-comment-form-text-error"]');
        $I->seeRecord(Comment::class, ['id' => $id, 'text' => 'Original text']);
    }

    /**
     * Texts the edit form must refuse.
     *
     * @return array<string, array{text: string, error: string}>
     */
    protected function invalidEdits(): array
    {
        // Data providers run before the Yii app (and its autoloader) exists, so no Comment::TEXT_MAX_LENGTH here
        return [
            'empty' => ['text' => '   ', 'error' => 'cannot be blank'],
            'too long (1001 characters)' => ['text' => str_repeat('a', 1001), 'error' => 'at most 1,000 characters'],
        ];
    }

    /**
     * F-15: Delete (a POST form) removes the comment from the DB, the admin list and the feed.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function deleteRemovesCommentEverywhere(FunctionalTester $I): void
    {
        $id = $I->haveComment('Anna', 'To be deleted');
        $I->amOnRoute('/admin/comment/index');

        // The only row's Delete button submits its POST form with the CSRF token
        $I->click('[data-testid="admin-comment-delete"]');

        $I->see("Comment #{$id} has been deleted.");
        $I->dontSeeRecord(Comment::class, ['id' => $id]);
        $I->dontSeeElement('[data-testid="admin-comment-row"]');
        $I->amOnRoute('/site/index');
        $I->dontSee('To be deleted');
    }

    /**
     * F-16: Delete by GET (a link, a prefetching browser) is refused with 405.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function deleteByGetIsNotAllowed(FunctionalTester $I): void
    {
        $id = $I->haveComment('Anna', 'Still here');

        $I->amOnRoute('/admin/comment/delete', ['id' => $id]);

        $I->seeResponseCodeIs(405);
        $I->seeRecord(Comment::class, ['id' => $id]);
    }

    /**
     * F-17: edit and delete of a missing comment answer 404.
     *
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function missingCommentGives404(FunctionalTester $I): void
    {
        $I->amOnRoute('/admin/comment/update', ['id' => 999999]);
        $I->seeResponseCodeIs(404);

        $I->sendPostToRoute('admin/comment/delete', ['id' => 999999]);
        $I->seeResponseCodeIs(404);
    }

    /**
     * F-18: HTML in the name and text is escaped in the admin list and in the edit form.
     *
     * The admin is the most valuable XSS target: their session can moderate everything.
     *
     * @flowStep 8
     * @param FunctionalTester $I Actor
     * @return void
     */
    public function htmlIsEscapedInAdminListAndEditForm(FunctionalTester $I): void
    {
        $id = $I->haveComment('<img src=x onerror=alert(1)>', '<script>alert(1)</script>');

        $I->amOnRoute('/admin/comment/index');
        $I->dontSeeInSource('<script>alert(1)</script>');
        $I->dontSeeInSource('<img src=x onerror=alert(1)>');
        $I->see('<script>alert(1)</script>', '[data-testid="admin-comment-text"]');

        $I->amOnRoute('/admin/comment/update', ['id' => $id]);
        $I->dontSeeInSource('<script>alert(1)</script>');
        $I->seeInField('[data-testid="admin-comment-form-text"]', '<script>alert(1)</script>');
        $I->seeInField('[data-testid="admin-comment-form-author"]', '<img src=x onerror=alert(1)>');
    }
}
