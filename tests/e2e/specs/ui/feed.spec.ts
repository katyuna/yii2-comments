import { expectNoHorizontalScroll } from '../../pages/FeedPage';
import { expect, logInAsAdmin, test } from '../../fixtures/pages';

/**
 * UI of the public comment feed (checklist UI-07 … UI-10).
 *
 * Comments are inserted straight into `app_test` (the `db` fixture), so every test controls
 * exactly what the feed contains.
 *
 * @flowStep 4
 */
test.describe('Comment feed', () => {
  /**
   * UI-07: a card shows the name, a readable date and the text with its line breaks.
   */
  test('UI-07 comment card shows name, readable date and keeps line breaks @ui', async ({ feedPage, db }) => {
    // 2026-11-25 08:30:00 UTC
    await db.insertComments([{ author: 'Anna', text: 'First line\nSecond line', createdAt: 1795595400 }]);

    await feedPage.goto();

    const card = feedPage.items.first();
    await expect(card.getByTestId('comment-item-author')).toHaveText('Anna');
    await expect(card.getByTestId('comment-item-date')).toHaveText('Nov 25, 2026, 8:30:00 AM');
    // innerText follows CSS: with white-space: pre-line the line break stays a line break
    const shownText = await card
      .getByTestId('comment-item-text')
      .evaluate((el) => (el as HTMLElement).innerText);
    expect(shownText).toBe('First line\nSecond line');
  });

  /**
   * UI-08: a 64-character name and a 1000-character word wrap instead of widening the page,
   * in the feed and in the admin list.
   *
   * @flowStep 8
   */
  test('UI-08 very long name and word wrap without horizontal scroll @ui', async ({ page, feedPage, db }) => {
    await db.insertComments([{ author: 'N'.repeat(64), text: 'W'.repeat(1000) }]);

    await feedPage.goto();
    await expect(feedPage.items).toHaveCount(1);
    await expectNoHorizontalScroll(page);

    await logInAsAdmin(page);
    await expect(page.getByTestId('admin-comment-row')).toHaveCount(1);
    await expectNoHorizontalScroll(page);
  });

  /**
   * UI-09: with no comments the feed and the admin list say so instead of showing an empty block.
   */
  test('UI-09 empty feed and empty admin list show a message @ui', async ({ page, feedPage }) => {
    await feedPage.goto();

    await expect(feedPage.empty).toHaveText('No comments yet. Be the first!');
    await expect(feedPage.count).toHaveText('0');
    await expect(feedPage.items).toHaveCount(0);
    // LinkPager leaves an empty <nav> in the markup; the requirement is that no pager is shown
    await expect(feedPage.pager).toBeHidden();
    await expect(feedPage.pager.getByRole('link')).toHaveCount(0);

    await logInAsAdmin(page);
    await expect(page.getByTestId('admin-comment-list-empty')).toHaveText('No comments yet.');
    await expect(page.getByTestId('admin-comment-count')).toHaveText('0');
  });

  /**
   * UI-10: the pager marks the current page; "previous" is inactive on the first page
   * and "next" on the last one.
   */
  test('UI-10 pager marks the current page and disables the arrows at the ends @ui', async ({
    feedPage,
    db,
  }) => {
    // Page size of the feed is 20: 25 comments give two pages
    await db.insertComments(
      Array.from({ length: 25 }, (_, i) => ({ author: 'Anna', text: `Comment ${i + 1}` })),
    );
    const items = feedPage.pager.getByRole('listitem');

    await feedPage.goto(1);
    await expect(items).toHaveCount(4); // « 1 2 »
    await expect(items.nth(1)).toHaveAttribute('aria-current', 'page');
    await expect(items.first()).toHaveClass(/disabled/);
    await expect(items.last()).not.toHaveClass(/disabled/);

    await feedPage.goto(2);
    await expect(items.nth(2)).toHaveAttribute('aria-current', 'page');
    await expect(items.first()).not.toHaveClass(/disabled/);
    await expect(items.last()).toHaveClass(/disabled/);
    await expect(feedPage.items).toHaveCount(5);
  });
});
