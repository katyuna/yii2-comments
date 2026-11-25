import { expectNoHorizontalScroll } from '../../pages/FeedPage';
import { expect, logInAsAdmin, test } from '../../fixtures/pages';

/**
 * Screen widths and the colour theme (checklist UI-19, UI-20).
 *
 * The menu button and the theme button are rendered by Bootstrap/the layout without a test id;
 * they are found by their accessible names, which are stable and are what a person hears.
 */

/** Phone, tablet, laptop */
for (const width of [375, 768, 1280]) {
  test.describe(`${width} px wide`, () => {
    test.use({ viewport: { width, height: 800 } });

    /**
     * UI-19: the form, the feed, the login page and the admin table fit the screen
     * without horizontal scrolling.
     *
     * @flowStep 1
     * @flowStep 4
     */
    test(`UI-19 pages fit ${width} px without horizontal scroll @ui`, async ({ page, feedPage, db }) => {
      await db.insertComments([{ author: 'Anna', text: 'A comment that has to fit on a small screen' }]);

      await feedPage.goto();
      await expectNoHorizontalScroll(page);

      await page.goto('/admin/auth/login');
      await expectNoHorizontalScroll(page);

      await logInAsAdmin(page);
      await expectNoHorizontalScroll(page);
    });
  });
}

test.describe('Menu on a phone (375 px)', () => {
  test.use({ viewport: { width: 375, height: 800 } });

  /**
   * UI-19: below 768 px (Bootstrap navbar-expand-md) the menu folds into a button that opens it.
   */
  test('UI-19 menu folds into a button and opens on click @ui', async ({ page, feedPage }) => {
    await feedPage.goto();
    const menuButton = page.getByRole('button', { name: 'Toggle navigation' });

    await expect(menuButton).toBeVisible();
    await expect(page.getByTestId('nav-admin')).toBeHidden();

    await menuButton.click();
    await expect(page.getByTestId('nav-admin')).toBeVisible();
  });
});

for (const width of [768, 1280]) {
  test.describe(`Menu at ${width} px`, () => {
    test.use({ viewport: { width, height: 800 } });

    /**
     * UI-19: from 768 px the menu items are shown directly, without the button.
     */
    test(`UI-19 menu stays open at ${width} px @ui`, async ({ page, feedPage }) => {
      await feedPage.goto();

      await expect(page.getByRole('button', { name: 'Toggle navigation' })).toBeHidden();
      await expect(page.getByTestId('nav-admin')).toBeVisible();
    });
  });
}

test.describe('Colour theme', () => {
  test.use({ colorScheme: 'light' });

  /**
   * UI-20: the theme button switches to dark mode, and the choice survives a reload.
   */
  test('UI-20 dark mode switch works and is remembered after reload @ui', async ({ page, feedPage }) => {
    await feedPage.goto();
    const html = page.locator('html');
    await expect(html).toHaveAttribute('data-bs-theme', 'light');

    await page.getByRole('button', { name: 'Switch to dark mode' }).click();

    await expect(html).toHaveAttribute('data-bs-theme', 'dark');
    await expect(page.getByRole('button', { name: 'Switch to light mode' })).toBeVisible();

    await page.reload();
    await expect(html).toHaveAttribute('data-bs-theme', 'dark');
  });
});
