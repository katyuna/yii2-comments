import { expect, type Locator, type Page } from '@playwright/test';

/** States of the real-time badge, as set in `data-state` by admin-realtime.js */
export type RealtimeState = 'connecting' | 'live' | 'offline';

/**
 * Page Object of the admin comment list with the live (WebSocket) updates.
 */
export class AdminCommentsPage {
  readonly list: Locator;
  readonly rows: Locator;
  readonly rowTexts: Locator;
  readonly count: Locator;
  readonly empty: Locator;
  /** Badge top right: Connecting… / Live / Offline */
  readonly realtimeStatus: Locator;
  readonly successFlash: Locator;
  readonly logout: Locator;

  /**
   * @param page Browser tab
   */
  constructor(readonly page: Page) {
    this.list = page.getByTestId('admin-comment-list');
    this.rows = page.getByTestId('admin-comment-row');
    this.rowTexts = page.getByTestId('admin-comment-text');
    this.count = page.getByTestId('admin-comment-count');
    this.empty = page.getByTestId('admin-comment-list-empty');
    this.realtimeStatus = page.getByTestId('realtime-status');
    this.successFlash = page.getByTestId('flash-success');
    this.logout = page.getByTestId('nav-logout');
  }

  /**
   * Opens the comment list.
   *
   * @returns Resolves when the page is loaded
   */
  async goto(): Promise<void> {
    await this.page.goto('/admin/comment/index');
  }

  /**
   * Row of the comment whose text matches.
   *
   * @param text Exact comment text
   * @returns Locator of the table row
   */
  row(text: string): Locator {
    return this.rows.filter({
      has: this.page.getByTestId('admin-comment-text').getByText(text, { exact: true }),
    });
  }

  /**
   * Waits until the page is subscribed to the admin channel.
   *
   * This is the synchronisation point for real-time tests: after it, a new comment
   * is delivered without any sleep.
   *
   * @returns Resolves when the badge says Live
   */
  async expectLive(): Promise<void> {
    await this.expectRealtimeState('live');
  }

  /**
   * Asserts the state of the real-time badge.
   *
   * @param state Expected state
   * @returns Resolves when the badge is in that state
   */
  async expectRealtimeState(state: RealtimeState): Promise<void> {
    await expect(this.realtimeStatus).toHaveAttribute('data-state', state);
  }
}
