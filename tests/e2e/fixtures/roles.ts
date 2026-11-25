import { AdminCommentsPage } from '../pages/AdminCommentsPage';
import { FeedPage } from '../pages/FeedPage';
import { logInAsAdmin, test as base } from './pages';

/**
 * `test` for E2E flows with two people at once, each in an own browser context
 * (separate cookies and session, like two different browsers):
 *
 * - `guest`: a visitor on the public page, not logged in;
 * - `admin`: the admin, logged in, on the comment list.
 *
 * The real-time tests need exactly this: the guest acts in one context and the admin's
 * page changes in the other, without a reload.
 */
interface RoleFixtures {
  guest: FeedPage;
  admin: AdminCommentsPage;
}

export const test = base.extend<RoleFixtures>({
  guest: async ({ browser }, use) => {
    const context = await browser.newContext();
    const guest = new FeedPage(await context.newPage());
    await guest.goto();
    await use(guest);
    await context.close();
  },

  admin: async ({ browser }, use) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await logInAsAdmin(page);
    await use(new AdminCommentsPage(page));
    await context.close();
  },
});

export { expect, uniqueText } from './pages';
