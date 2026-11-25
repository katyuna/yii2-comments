import AxeBuilder from '@axe-core/playwright';
import type { Locator, Page } from '@playwright/test';
import { admin } from '../../fixtures/test';
import { expect, logInAsAdmin, test, uniqueText } from '../../fixtures/pages';

/**
 * Accessibility (checklist UI-17): keyboard-only use, visible focus, and an automatic
 * WCAG 2.1 A/AA scan with axe-core on the main pages.
 *
 * @flowStep 1
 * @flowStep 6
 */
test.describe('Accessibility', () => {
  /**
   * UI-17: a guest can reach the form, fill it in and post with the keyboard only,
   * and the focused field is visibly highlighted.
   */
  test('UI-17 guest can post a comment with the keyboard only @ui', async ({ page, feedPage }) => {
    const text = uniqueText('UI-17');
    await feedPage.goto();

    await tabUntilFocused(page, feedPage.author);
    await expectVisibleFocus(feedPage.author);
    await page.keyboard.type('Keyboard user');
    await page.keyboard.press('Tab');
    await expect(feedPage.text).toBeFocused();
    await page.keyboard.type(text);
    await page.keyboard.press('Tab');
    await expect(feedPage.submit).toBeFocused();
    await page.keyboard.press('Enter');

    await expect(feedPage.item(text)).toBeVisible();
  });

  /**
   * UI-17: the admin can log in with the keyboard only.
   */
  test('UI-17 admin can log in with the keyboard only @ui', async ({ page, loginPage }) => {
    await loginPage.goto();

    // The username field has autofocus
    await expect(loginPage.username).toBeFocused();
    await page.keyboard.type(admin.username);
    await page.keyboard.press('Tab');
    await expect(loginPage.password).toBeFocused();
    await expectVisibleFocus(loginPage.password);
    await page.keyboard.type(admin.password);
    await page.keyboard.press('Enter');

    await expect(page.getByTestId('admin-comment-list')).toBeVisible();
  });

  /**
   * UI-17: axe-core finds no serious or critical WCAG 2.1 A/AA violations on the
   * feed, the login page and the admin list.
   */
  test('UI-17 main pages have no serious accessibility violations @ui', async ({ page, feedPage, db }) => {
    await db.insertComments([{ author: 'Anna', text: 'A comment for the scan' }]);

    await feedPage.goto();
    await expectNoSeriousViolations(page, 'feed');

    await page.goto('/admin/auth/login');
    await expectNoSeriousViolations(page, 'login');

    await logInAsAdmin(page);
    await expectNoSeriousViolations(page, 'admin list');
  });
});

/**
 * Presses Tab until the element has focus, like a keyboard user moving through the page.
 *
 * @param page Browser tab
 * @param target Element to reach
 * @returns Resolves when the element is focused
 */
async function tabUntilFocused(page: Page, target: Locator): Promise<void> {
  for (let i = 0; i < 20; i++) {
    await page.keyboard.press('Tab');
    if (await target.evaluate((el) => el === document.activeElement)) {
      return;
    }
  }
  throw new Error('The element cannot be reached with Tab');
}

/**
 * Asserts the focused element has a visible focus indicator (Bootstrap draws it as a box-shadow
 * or an outline).
 *
 * @param element Focused element
 * @returns Resolves when the check passed
 */
async function expectVisibleFocus(element: Locator): Promise<void> {
  await expect(element).toBeFocused();
  const indicator = await element.evaluate((el) => {
    const style = getComputedStyle(el);
    return { boxShadow: style.boxShadow, outline: style.outlineStyle };
  });
  expect(
    indicator.boxShadow !== 'none' || indicator.outline !== 'none',
    `Focus must be visible, got ${JSON.stringify(indicator)}`,
  ).toBe(true);
}

/**
 * Runs axe-core with the WCAG 2.1 A and AA rules and fails on serious or critical issues.
 *
 * @param page Browser tab with the page to scan
 * @param name Page name for the error message
 * @returns Resolves when the scan passed
 */
async function expectNoSeriousViolations(page: Page, name: string): Promise<void> {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const serious = results.violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
    .map((v) => `${v.id} (${v.impact}): ${v.help} — ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);

  expect(serious, `Accessibility violations on the ${name} page`).toEqual([]);
}
