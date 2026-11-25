import type { Locator, WebSocketRoute } from '@playwright/test';
import { admin } from '../../fixtures/test';
import { AdminCommentsPage } from '../../pages/AdminCommentsPage';
import { FeedPage } from '../../pages/FeedPage';
import { expect, logInAsAdmin, test, uniqueText } from '../../fixtures/pages';

/**
 * UI of the admin area: login page, comment list, live badge, delete and edit
 * (checklist UI-11 … UI-16).
 */
test.describe('Admin area', () => {
  /**
   * UI-11: the login form has hidden password input, "Remember me" and a button; after a wrong
   * password the error is under the field, the password is cleared and the username is kept.
   *
   * @flowStep 6
   */
  test('UI-11 login form hides the password and keeps the username after an error @ui', async ({
    loginPage,
  }) => {
    await loginPage.goto();
    await expect(loginPage.username).toHaveAccessibleName('Username');
    await expect(loginPage.password).toHaveAccessibleName('Password');
    await expect(loginPage.password).toHaveAttribute('type', 'password');
    await expect(loginPage.rememberMe).toHaveAttribute('type', 'checkbox');
    await expect(loginPage.submit).toHaveText('Log in');

    await loginPage.logIn(admin.username, 'definitely-wrong');

    await expect(loginPage.passwordError).toHaveText('Incorrect username or password.');
    await expect(loginPage.password).toHaveValue('');
    await expect(loginPage.username).toHaveValue(admin.username);
  });

  /**
   * UI-12: the list shows name, text, date and actions; newest first; Edit and Delete
   * in every row; the menu has a logout item.
   *
   * @flowStep 9
   */
  test('UI-12 admin list shows all columns, newest first, with actions and logout @ui', async ({
    adminPage,
    db,
  }) => {
    await db.insertComments([
      { author: 'Old', text: 'older' },
      { author: 'New', text: 'newer' },
    ]);
    await adminPage.goto();

    await expect(adminPage.list.getByRole('columnheader')).toHaveText([
      'ID',
      'Name',
      'Comment',
      'Posted',
      'IP',
      'Actions',
    ]);
    await expect(adminPage.rowTexts).toHaveText(['newer', 'older']);
    for (const row of await adminPage.rows.all()) {
      await expect(row.getByTestId('admin-comment-edit')).toBeVisible();
      await expect(row.getByTestId('admin-comment-delete')).toBeVisible();
    }
    await expect(adminPage.logout).toContainText('Logout');
  });

  /**
   * UI-13: the badge says Live when connected, shows another text and colour when the
   * WebSocket drops, and says Live again after the client reconnects by itself.
   *
   * The WebSocket is routed through the test (page.routeWebSocket): the test decides
   * when the connection breaks and when reconnecting is allowed again.
   *
   * @flowStep 7
   */
  test('UI-13 live badge reflects connection loss and reconnect @ui', async ({ page }) => {
    let allowConnections = true;
    let current: WebSocketRoute | undefined;
    await page.routeWebSocket('**/connection/websocket', (ws) => {
      if (!allowConnections) {
        // Outage: refuse the reconnect attempt
        void ws.close({ code: 1011, reason: 'test outage' });
        return;
      }
      current = ws;
      ws.connectToServer();
    });
    // Logged in by hand: the WebSocket route must exist before the admin page opens
    await logInAsAdmin(page);
    const adminPage = new AdminCommentsPage(page);

    await adminPage.expectLive();
    await expect(adminPage.realtimeStatus).toHaveText('Live');
    await expect(adminPage.realtimeStatus).toHaveClass(/text-bg-success/);

    // Break the connection; reconnect attempts are refused until allowed
    allowConnections = false;
    await current?.close({ code: 1011, reason: 'test outage' });
    await expect(adminPage.realtimeStatus).not.toHaveAttribute('data-state', 'live');
    await expect(adminPage.realtimeStatus).not.toHaveText('Live');
    await expect(adminPage.realtimeStatus).not.toHaveClass(/text-bg-success/);

    allowConnections = true;
    await adminPage.expectLive();
    await expect(adminPage.realtimeStatus).toHaveText('Live');
  });

  /**
   * UI-14: a row added over WebSocket looks the same as the same row after a reload
   * (it is built by JavaScript, the reloaded one by PHP).
   *
   * @flowStep 8
   */
  test('UI-14 live row looks the same as the row after reload @ui', async ({ adminPage, browser }) => {
    await adminPage.expectLive();
    const text = uniqueText('UI-14');

    const guestContext = await browser.newContext();
    const guest = new FeedPage(await guestContext.newPage());
    await guest.goto();
    await guest.postComment('Live <b>guest</b>', text);
    await guestContext.close();

    const liveRow = adminPage.row(text);
    await expect(liveRow).toBeVisible();
    const live = await rowSnapshot(liveRow);

    await adminPage.page.reload();
    const reloaded = await rowSnapshot(adminPage.row(text));

    expect(live).toEqual(reloaded);
  });

  /**
   * UI-15: Delete asks for confirmation; Cancel keeps the comment, OK deletes it.
   *
   * @flowStep 9
   */
  test('UI-15 delete asks for confirmation @ui', async ({ page, adminPage, db }) => {
    await db.insertComments([{ author: 'Anna', text: 'Keep me or not' }]);
    await adminPage.goto();
    const row = adminPage.row('Keep me or not');

    const dialogs: string[] = [];
    page.once('dialog', (dialog) => {
      dialogs.push(dialog.message());
      void dialog.dismiss();
    });
    await row.getByTestId('admin-comment-delete').click();
    await expect(row).toBeVisible();
    expect(await db.comments()).toHaveLength(1);

    page.once('dialog', (dialog) => {
      dialogs.push(dialog.message());
      void dialog.accept();
    });
    await row.getByTestId('admin-comment-delete').click();
    await expect(adminPage.successFlash).toContainText('has been deleted');
    await expect(row).toHaveCount(0);

    expect(dialogs).toEqual(['Delete this comment?', 'Delete this comment?']);
  });

  /**
   * UI-16: the edit form is filled with the current values; Save shows a success message
   * and returns to the list; Cancel goes back without saving.
   *
   * @flowStep 9
   */
  test('UI-16 edit form is prefilled, saves with a message and can be cancelled @ui', async ({
    adminPage,
    editPage,
    db,
  }) => {
    const [id] = await db.insertComments([{ author: 'Anna', text: 'Before edit' }]);

    await editPage.goto(id);
    await expect(editPage.author).toHaveValue('Anna');
    await expect(editPage.text).toHaveValue('Before edit');

    await editPage.text.fill('Changed but cancelled');
    await editPage.cancel.click();
    await expect(adminPage.list).toBeVisible();
    await expect(adminPage.rowTexts).toHaveText(['Before edit']);

    await editPage.goto(id);
    await editPage.text.fill('After edit');
    await editPage.save.click();

    await expect(adminPage.successFlash).toContainText(`Comment #${id} has been updated.`);
    await expect(adminPage.rowTexts).toHaveText(['After edit']);
  });
});

/**
 * What a person sees in a table row: the text of every cell and the actions.
 *
 * @param row Row locator
 * @returns Cell texts and action links
 */
async function rowSnapshot(row: Locator): Promise<{
  cells: string[];
  editHref: string | null;
  deleteLabel: string;
}> {
  return {
    cells: await row.getByRole('cell').allInnerTexts(),
    editHref: await row.getByTestId('admin-comment-edit').getAttribute('href'),
    deleteLabel: await row.getByTestId('admin-comment-delete').innerText(),
  };
}
