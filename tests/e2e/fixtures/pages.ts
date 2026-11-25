import type { Page } from '@playwright/test';
import { AdminCommentsPage } from '../pages/AdminCommentsPage';
import { EditCommentPage } from '../pages/EditCommentPage';
import { FeedPage } from '../pages/FeedPage';
import { LoginPage } from '../pages/LoginPage';
import { admin, test as base } from './test';

/**
 * `test` with Page Objects as fixtures. Specs import `test` and `expect` from here.
 *
 * - `feedPage`, `loginPage`, `editPage`: Page Objects bound to the test's tab;
 * - `adminPage`: the admin comment list, already logged in through the real login form.
 */
interface PageFixtures {
  feedPage: FeedPage;
  loginPage: LoginPage;
  editPage: EditCommentPage;
  adminPage: AdminCommentsPage;
}

export const test = base.extend<PageFixtures>({
  feedPage: async ({ page }, use) => {
    await use(new FeedPage(page));
  },

  loginPage: async ({ page }, use) => {
    await use(new LoginPage(page));
  },

  editPage: async ({ page }, use) => {
    await use(new EditCommentPage(page));
  },

  adminPage: async ({ page }, use) => {
    await logInAsAdmin(page);
    await use(new AdminCommentsPage(page));
  },
});

export { expect, uniqueText } from './test';

/**
 * Logs in through the login form with the credentials from `.env`; ends on the comment list.
 *
 * @param page Browser tab (its context keeps the session cookie)
 * @returns Resolves when the admin list is open
 */
export async function logInAsAdmin(page: Page): Promise<void> {
  const loginPage = new LoginPage(page);
  await loginPage.goto();
  await loginPage.logIn(admin.username, admin.password);
  await page.getByTestId('admin-comment-list').waitFor();
}
