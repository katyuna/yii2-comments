import type { Page } from '@playwright/test';
import { EditCommentPage } from '../../pages/EditCommentPage';
import { expect, test, uniqueText } from '../../fixtures/roles';

/**
 * The whole life of a comment across two browsers (checklist E-01, E-04, E-05, E-06, E-10).
 *
 * `guest` and `admin` are separate browser contexts (see fixtures/roles.ts). Tests tagged
 * `@cross-browser` also run in Firefox and WebKit (projects e2e-firefox, e2e-webkit) — E-10.
 */
test.describe('Comment flow', () => {
  /**
   * E-01: one comment from the form to moderation: the admin is live, the guest posts,
   * the guest sees it in the feed, the admin gets the row without a reload, edits it,
   * and deletes it; the guest's feed follows each change.
   *
   * @flowStep 1
   * @flowStep 2
   * @flowStep 3
   * @flowStep 4
   * @flowStep 5
   * @flowStep 6
   * @flowStep 7
   * @flowStep 8
   * @flowStep 9
   */
  test('E-01 comment goes from the guest form to admin moderation @e2e @cross-browser', async ({
    guest,
    admin,
  }) => {
    const text = uniqueText('E-01');
    await admin.expectLive();
    await markPageAsNotReloaded(admin.page);

    // Guest posts (steps 1-4)
    await guest.postComment('Guest E-01', text);
    await expect(guest.successFlash).toBeVisible();
    await expect(guest.item(text)).toBeVisible();

    // Admin gets it live (steps 5-8)
    const row = admin.row(text);
    await expect(row).toBeVisible();
    await expect(row.getByTestId('admin-comment-author')).toHaveText('Guest E-01');
    await expectPageWasNotReloaded(admin.page);

    // Admin edits (step 9)
    const edited = `${text} (edited)`;
    await row.getByTestId('admin-comment-edit').click();
    const editPage = new EditCommentPage(admin.page);
    await editPage.text.fill(edited);
    await editPage.save.click();
    await expect(admin.row(edited)).toBeVisible();
    await guest.page.reload();
    await expect(guest.item(edited)).toBeVisible();

    // Admin deletes (step 9)
    admin.page.once('dialog', (dialog) => void dialog.accept());
    await admin.row(edited).getByTestId('admin-comment-delete').click();
    await expect(admin.successFlash).toContainText('has been deleted');
    await expect(admin.row(edited)).toHaveCount(0);
    await guest.page.reload();
    await expect(guest.item(edited)).toHaveCount(0);
  });

  /**
   * E-04: Edit and Delete work in a row that arrived over WebSocket (built by JavaScript,
   * with the id and CSRF token filled in by the script).
   *
   * @flowStep 8
   * @flowStep 9
   */
  test('E-04 edit and delete work in a row that arrived live @e2e', async ({ guest, admin }) => {
    await admin.expectLive();
    const first = uniqueText('E-04 edit');
    const second = uniqueText('E-04 delete');

    await guest.postComment('Guest', first);
    await expect(admin.row(first)).toBeVisible();
    await admin.row(first).getByTestId('admin-comment-edit').click();
    const editPage = new EditCommentPage(admin.page);
    await expect(editPage.text).toHaveValue(first);
    await editPage.text.fill(`${first} (edited)`);
    await editPage.save.click();
    await expect(admin.row(`${first} (edited)`)).toBeVisible();

    // Back on the list (a fresh page): a new live row must be deletable without a reload
    await admin.expectLive();
    await guest.postComment('Guest', second);
    await expect(admin.row(second)).toBeVisible();
    admin.page.once('dialog', (dialog) => void dialog.accept());
    await admin.row(second).getByTestId('admin-comment-delete').click();

    await expect(admin.successFlash).toContainText('has been deleted');
    await expect(admin.row(second)).toHaveCount(0);
  });

  /**
   * E-05: three comments posted one after another all reach the admin, newest first,
   * each exactly once.
   *
   * @flowStep 8
   */
  test('E-05 several comments arrive in order without loss or duplicates @e2e', async ({ guest, admin }) => {
    await admin.expectLive();
    const texts = [uniqueText('E-05 one'), uniqueText('E-05 two'), uniqueText('E-05 three')];

    for (const text of texts) {
      await guest.postComment('Guest', text);
      await expect(guest.item(text)).toBeVisible();
    }

    await expect(admin.rowTexts).toHaveText([...texts].reverse());
    await expect(admin.count).toHaveText('3');
  });

  /**
   * E-06: an empty form is stopped in the browser: errors under the fields, no request
   * is sent, nothing reaches the feed or the admin.
   *
   * @flowStep 2
   */
  test('E-06 empty form shows errors in the browser and sends nothing @e2e', async ({ guest, admin, db }) => {
    await admin.expectLive();
    const posts: string[] = [];
    guest.page.on('request', (request) => {
      if (request.method() === 'POST') {
        posts.push(request.url());
      }
    });

    await guest.submit.click();

    await expect(guest.authorError).toHaveText('Your name cannot be blank.');
    await expect(guest.textError).toHaveText('Comment cannot be blank.');
    await expect(guest.items).toHaveCount(0);
    expect(posts, 'Client-side validation must stop the request').toEqual([]);
    expect(await db.comments()).toEqual([]);
    await expect(admin.rows).toHaveCount(0);
  });
});

/**
 * Leaves a marker in the page's JavaScript; a reload would wipe it.
 *
 * @param page Admin tab
 * @returns Resolves when the marker is set
 */
async function markPageAsNotReloaded(page: Page): Promise<void> {
  await page.evaluate(() => {
    (window as unknown as { __notReloaded: boolean }).__notReloaded = true;
  });
}

/**
 * Asserts the marker from markPageAsNotReloaded() is still there.
 *
 * @param page Admin tab
 * @returns Resolves when the check passed
 */
async function expectPageWasNotReloaded(page: Page): Promise<void> {
  const marker = await page.evaluate(() => (window as unknown as { __notReloaded?: boolean }).__notReloaded);
  expect(marker, 'The admin page must not reload').toBe(true);
}
