import { expect, test, uniqueText } from '../../fixtures/pages';

/**
 * UI of the guest comment form (checklist UI-01 … UI-06).
 *
 * Each test opens the public page in Chromium on an empty `app_test` DB (the `db` fixture
 * empties it before every test). Elements are found by `data-testid`; role and label
 * locators are used only where the test checks what a person or a screen reader sees.
 *
 * @flowStep 1
 */
test.describe('Comment form', () => {
  test.beforeEach(async ({ feedPage }) => {
    await feedPage.goto();
  });

  /**
   * UI-01: every part of the form and the feed is on the page and labelled.
   */
  test('UI-01 home page shows the labelled form, the menu and the feed @ui', async ({ page, feedPage }) => {
    await expect(page.getByRole('heading', { name: 'Leave a comment' })).toBeVisible();
    // Accessible name = the visible label linked to the field: what a screen reader announces
    await expect(feedPage.author).toHaveAccessibleName('Your name');
    await expect(feedPage.text).toHaveAccessibleName('Comment');
    await expect(feedPage.submit).toHaveText('Post comment');
    await expect(page.getByTestId('nav-comments')).toHaveText('Comments');
    await expect(page.getByTestId('nav-admin')).toHaveText('Admin');
    await expect(page.getByRole('heading', { name: /Latest comments/ })).toBeVisible();
    await expect(feedPage.feed).toBeAttached();
  });

  /**
   * UI-02: required fields are marked for assistive technology, and a click on a label
   * focuses its own field.
   */
  test('UI-02 required fields are marked and labels focus their fields @ui', async ({ page, feedPage }) => {
    await expect(feedPage.author).toHaveAttribute('aria-required', 'true');
    await expect(feedPage.text).toHaveAttribute('aria-required', 'true');

    await page.getByText('Your name', { exact: true }).click();
    await expect(feedPage.author).toBeFocused();

    await page.getByText('Comment', { exact: true }).click();
    await expect(feedPage.text).toBeFocused();
  });

  /**
   * UI-03: an empty submit highlights both fields with an error under each;
   * typing a value removes the highlight.
   *
   * @flowStep 2
   */
  test('UI-03 empty submit highlights fields and the highlight goes away after input @ui', async ({
    feedPage,
  }) => {
    await feedPage.submit.click();

    await expect(feedPage.author).toHaveClass(/is-invalid/);
    await expect(feedPage.text).toHaveClass(/is-invalid/);
    await expect(feedPage.authorError).toHaveText('Your name cannot be blank.');
    await expect(feedPage.textError).toHaveText('Comment cannot be blank.');

    await feedPage.author.fill('Anna');
    await feedPage.author.blur();

    await expect(feedPage.author).not.toHaveClass(/is-invalid/);
    await expect(feedPage.authorError).toBeEmpty();
    await expect(feedPage.text).toHaveClass(/is-invalid/);
  });

  /**
   * UI-04: a person never sees or reaches the honeypot field, so they are never taken for a bot.
   *
   * @flowStep 2
   */
  test('UI-04 honeypot field is invisible and skipped by Tab @ui', async ({ page, feedPage }) => {
    await expect(feedPage.website).toBeHidden();
    await expect(feedPage.website).toHaveAttribute('tabindex', '-1');
    await expect(feedPage.website).toHaveAttribute('autocomplete', 'off');

    // Keyboard order: name → comment → button, the hidden field is not in between
    await feedPage.author.focus();
    await page.keyboard.press('Tab');
    await expect(feedPage.text).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(feedPage.submit).toBeFocused();
  });

  /**
   * UI-05: an impatient double click sends one request: the button is disabled while
   * the form is being sent, and the feed gets one comment.
   *
   * The POST is held by the test (page.route) until the button state is checked,
   * so the check does not depend on network speed.
   * Regression: two POSTs on a double click were fixed in fix/disable-submit-while-sending.
   *
   * @flowStep 3
   */
  test('UI-05 double click on Post comment sends one comment @ui @regression', async ({
    page,
    feedPage,
    db,
  }) => {
    const text = uniqueText('UI-05');
    let postRequests = 0;
    let releasePost: () => void = () => undefined;
    const postHeld = new Promise<void>((resolve) => (releasePost = resolve));
    await page.route('**/*', async (route) => {
      if (route.request().method() === 'POST') {
        postRequests++;
        await postHeld;
      }
      await route.continue();
    });

    await feedPage.author.fill('Anna');
    await feedPage.text.fill(text);
    await feedPage.submit.dblclick();

    await expect(feedPage.submit).toBeDisabled();
    releasePost();
    await expect(feedPage.item(text)).toBeVisible();
    expect(postRequests, 'Only one POST may be sent').toBe(1);
    expect(await db.comments()).toHaveLength(1);
  });

  /**
   * UI-06: after a successful post the form is empty, a success message is shown,
   * the comment is first in the feed and the counter went up by one.
   *
   * @flowStep 4
   */
  test('UI-06 successful post clears the form, thanks the guest and updates the feed @ui', async ({
    feedPage,
    db,
  }) => {
    await db.insertComments([{ author: 'Bob', text: 'An older comment' }]);
    await feedPage.goto();
    await expect(feedPage.count).toHaveText('1');
    const text = uniqueText('UI-06');

    await feedPage.postComment('Anna', text);

    await expect(feedPage.successFlash).toContainText('Your comment has been published');
    await expect(feedPage.author).toHaveValue('');
    await expect(feedPage.text).toHaveValue('');
    await expect(feedPage.itemTexts.first()).toHaveText(text);
    await expect(feedPage.count).toHaveText('2');
  });
});
