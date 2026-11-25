import type { Page, WebSocketRoute } from '@playwright/test';
import { logInAsAdmin } from '../../fixtures/pages';
import { AdminCommentsPage } from '../../pages/AdminCommentsPage';
import { expect, test, uniqueText } from '../../fixtures/roles';

/**
 * The real-time channel to the admin list (checklist E-02, E-03, E-07, E-08, E-09).
 *
 * WebSocket outages are simulated with page.routeWebSocket: the admin's browser talks to
 * Centrifugo through the test, which can drop the connection and refuse reconnects.
 * A real server-side outage (PHP cannot reach Centrifugo) is covered by unit test U-10
 * and regression test R-04.
 */
test.describe('Real-time admin list', () => {
  /**
   * E-02: the badge top right switches to Live once the list is subscribed;
   * this state is the sync point that lets the other tests avoid sleeps.
   *
   * @flowStep 7
   */
  test('E-02 admin list shows Live after connecting @e2e @cross-browser', async ({ admin }) => {
    await admin.expectLive();
    await expect(admin.realtimeStatus).toHaveText('Live');
  });

  /**
   * E-03: HTML sent through the WebSocket is shown as text in the admin row; no script runs.
   * The row is built by JavaScript, so server-side Html::encode does not protect it.
   *
   * @flowStep 8
   */
  test('E-03 HTML in a live comment is shown as text and never runs @e2e', async ({ guest, admin }) => {
    const dialogs: string[] = [];
    admin.page.on('dialog', (dialog) => {
      dialogs.push(dialog.message());
      void dialog.dismiss();
    });
    await admin.expectLive();
    const payload = `<script>alert('xss-text')</script><img src=x onerror="alert('xss-img')"> ${uniqueText('E-03')}`;

    await guest.postComment(`<b onmouseover="alert('xss-name')">Mallory</b>`, payload);

    const row = admin.row(payload);
    await expect(row).toBeVisible();
    await expect(row.getByTestId('admin-comment-author')).toHaveText(
      `<b onmouseover="alert('xss-name')">Mallory</b>`,
    );
    await row.getByTestId('admin-comment-author').hover();
    // No element was created from the payload
    await expect(row.locator('script, img, b')).toHaveCount(0);
    expect(dialogs, 'No alert may open').toEqual([]);
  });

  /**
   * E-07: with the WebSocket down the guest still posts normally; the admin sees that
   * the list is not live, and the comment is there after a reload.
   *
   * @flowStep 3
   * @flowStep 5
   * @flowStep 7
   */
  test('E-07 guest posts while the WebSocket is down; admin sees it after reload @e2e', async ({
    page,
    guest,
  }) => {
    const outage = await routeWebSocketWithOutage(page);
    outage.down();
    await logInAsAdmin(page);
    const admin = new AdminCommentsPage(page);
    const text = uniqueText('E-07');

    await expect(admin.realtimeStatus).not.toHaveAttribute('data-state', 'live');
    await guest.postComment('Guest', text);
    await expect(guest.successFlash).toBeVisible();
    await expect(guest.item(text)).toBeVisible();
    await expect(admin.realtimeStatus).not.toHaveText('Live');

    await page.reload();
    await expect(admin.row(text)).toBeVisible();
  });

  /**
   * E-08: after the WebSocket comes back the badge is Live again without a reload,
   * and new comments arrive.
   *
   * @flowStep 7
   * @flowStep 8
   */
  test('E-08 client reconnects by itself and receives new comments @e2e', async ({ page, guest }) => {
    const outage = await routeWebSocketWithOutage(page);
    await logInAsAdmin(page);
    const admin = new AdminCommentsPage(page);
    await admin.expectLive();

    await outage.drop();
    await expect(admin.realtimeStatus).not.toHaveAttribute('data-state', 'live');
    outage.up();

    await admin.expectLive();
    const text = uniqueText('E-08');
    await guest.postComment('Guest', text);
    await expect(admin.row(text)).toBeVisible();
  });

  /**
   * E-09: after the admin logs out in one tab, the other tab stops receiving comments once
   * its WebSocket token expires (the token refresh is refused with 403).
   *
   * The test stand issues tokens for CENTRIFUGO_TOKEN_TTL seconds (5 by default), so the
   * test waits for the refresh instead of an hour.
   *
   * @flowStep 7
   */
  test('E-09 after logout the other tab stops receiving comments when the token expires @e2e', async ({
    admin,
    guest,
  }) => {
    const ttlSeconds = Number(process.env.CENTRIFUGO_TOKEN_TTL ?? 5);
    test.setTimeout((ttlSeconds + 60) * 1000);
    const otherTab = new AdminCommentsPage(await admin.page.context().newPage());
    await otherTab.goto();
    await otherTab.expectLive();

    // Log out in the first tab (yii.js sends the logout link as a POST)
    await admin.logout.click();
    await expect(admin.page.getByTestId('comment-form')).toBeVisible();

    // The other tab asks for a new token when the old one expires, and is refused
    const refresh = await otherTab.page.waitForResponse((r) => r.url().includes('/admin/realtime/token'), {
      timeout: (ttlSeconds + 30) * 1000,
    });
    expect(refresh.status()).toBe(403);
    await otherTab.expectRealtimeState('offline');

    const text = uniqueText('E-09');
    await guest.postComment('Guest', text);
    await expect(guest.item(text)).toBeVisible();
    // The client is disconnected (state offline), so nothing can be delivered any more
    await expect(otherTab.row(text)).toHaveCount(0);
  });
});

/** Control over the admin page's WebSocket, see routeWebSocketWithOutage(). */
interface WebSocketOutage {
  /** Refuse new connections (Centrifugo "down") */
  down(): void;
  /** Accept new connections again */
  up(): void;
  /** Close the current connection and refuse reconnects until up() */
  drop(): Promise<void>;
}

/**
 * Routes the page's WebSocket to Centrifugo through the test, so the test can cut it.
 *
 * @param page Admin tab, before it opens the list
 * @returns Switches for the simulated outage
 */
async function routeWebSocketWithOutage(page: Page): Promise<WebSocketOutage> {
  let online = true;
  let current: WebSocketRoute | undefined;

  await page.routeWebSocket('**/connection/websocket', (ws) => {
    if (!online) {
      void ws.close({ code: 1011, reason: 'simulated outage' });
      return;
    }
    current = ws;
    ws.connectToServer();
  });

  return {
    down: () => {
      online = false;
    },
    up: () => {
      online = true;
    },
    drop: async () => {
      online = false;
      await current?.close({ code: 1011, reason: 'simulated outage' });
    },
  };
}
