import { expect, logInAsAdmin, test } from '../../fixtures/pages';
import type { TestDb } from '../../fixtures/db';

/**
 * Visual regression (checklist UI-18): full-page screenshots compared with approved ones.
 *
 * Functional tests check what is on a page; screenshots catch how it looks (a broken layout,
 * a missing style). Data and dates are fixed, so only a real change of the page makes a
 * difference. Baselines live in `specs/__screenshots__/` and are made in the Playwright
 * container; after an intended design change update them with
 * `make test-ui ARGS=--update-snapshots` and review the new images in the PR.
 */

/** 2026-11-25 08:30:00 UTC: a fixed date, so the dates on screenshots never change */
const FIXED_TIME = 1795595400;

for (const colorScheme of ['light', 'dark'] as const) {
  test.describe(`Screenshots, ${colorScheme} theme`, () => {
    test.use({ colorScheme });

    test(`UI-18 feed looks as approved (${colorScheme}) @ui`, async ({ page, db }) => {
      await seed(db);

      await page.goto('/');

      await expect(page).toHaveScreenshot(`feed-${colorScheme}.png`, { fullPage: true });
    });

    test(`UI-18 login page looks as approved (${colorScheme}) @ui`, async ({ page }) => {
      await page.goto('/admin/auth/login');

      await expect(page).toHaveScreenshot(`login-${colorScheme}.png`, { fullPage: true });
    });

    test(`UI-18 admin list looks as approved (${colorScheme}) @ui`, async ({ page, db }) => {
      await seed(db);
      await logInAsAdmin(page);
      // Wait for the final badge state, so the screenshot does not catch "Connecting…"
      await expect(page.getByTestId('realtime-status')).toHaveAttribute('data-state', 'live');

      await expect(page).toHaveScreenshot(`admin-list-${colorScheme}.png`, { fullPage: true });
    });
  });
}

/**
 * Inserts the same three comments with fixed dates for every screenshot.
 *
 * @param db Test DB helper
 * @returns Resolves when the rows are inserted
 */
async function seed(db: TestDb): Promise<void> {
  await db.insertComments([
    { author: 'Anna', text: 'The first comment, short.', createdAt: FIXED_TIME - 7200 },
    {
      author: 'Борис',
      text: 'Второй комментарий, на русском.\nВо второй строке 😀',
      createdAt: FIXED_TIME - 3600,
    },
    {
      author: 'Carol',
      text: 'A longer third comment that wraps onto the next line to show how long text looks in a card.',
      createdAt: FIXED_TIME,
    },
  ]);
}
