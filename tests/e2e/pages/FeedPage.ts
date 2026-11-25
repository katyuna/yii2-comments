import { expect, type Locator, type Page } from '@playwright/test';

/**
 * Page Object of the public page: the guest comment form and the comment feed.
 *
 * Specs talk to the page through these locators and actions, so a markup change is fixed
 * here, in one place. All locators use `data-testid`.
 */
export class FeedPage {
  readonly form: Locator;
  readonly author: Locator;
  readonly text: Locator;
  /** Honeypot: hidden from people, must stay empty */
  readonly website: Locator;
  readonly submit: Locator;
  readonly authorError: Locator;
  readonly textError: Locator;
  readonly feed: Locator;
  readonly items: Locator;
  readonly itemTexts: Locator;
  readonly count: Locator;
  readonly empty: Locator;
  readonly pager: Locator;
  readonly successFlash: Locator;

  /**
   * @param page Browser tab
   */
  constructor(readonly page: Page) {
    this.form = page.getByTestId('comment-form');
    this.author = page.getByTestId('comment-form-author');
    this.text = page.getByTestId('comment-form-text');
    this.website = page.getByTestId('comment-form-website');
    this.submit = page.getByTestId('comment-form-submit');
    this.authorError = page.getByTestId('comment-form-author-error');
    this.textError = page.getByTestId('comment-form-text-error');
    this.feed = page.getByTestId('comment-feed');
    this.items = page.getByTestId('comment-item');
    this.itemTexts = page.getByTestId('comment-item-text');
    this.count = page.getByTestId('comment-feed-count');
    this.empty = page.getByTestId('comment-feed-empty');
    this.pager = page.getByTestId('comment-feed-pager');
    this.successFlash = page.getByTestId('flash-success');
  }

  /**
   * Opens the feed (optionally a given page of it).
   *
   * @param pageNumber Pagination page, 1-based
   * @returns Resolves when the page is loaded
   */
  async goto(pageNumber = 1): Promise<void> {
    await this.page.goto(pageNumber === 1 ? '/' : `/?page=${pageNumber}`);
  }

  /**
   * Fills in the form and submits it, then waits for the page that follows (redirect or errors).
   *
   * @param author Name
   * @param text Comment text
   * @returns Resolves after the next page has loaded
   */
  async postComment(author: string, text: string): Promise<void> {
    await this.author.fill(author);
    await this.text.fill(text);
    // yiiActiveForm sends the form only after client validation, a moment after the click.
    // waitForLoadState() would see the current, already loaded page and return before the POST
    // left the browser, so wait for the next document instead (UI-14 lost its comment in CI).
    const nextPage = this.page.waitForEvent('domcontentloaded');
    await this.submit.click();
    await nextPage;
  }

  /**
   * Card of the comment whose text matches.
   *
   * @param text Exact comment text
   * @returns Locator of the card
   */
  item(text: string): Locator {
    return this.items.filter({
      has: this.page.getByTestId('comment-item-text').getByText(text, { exact: true }),
    });
  }

  /**
   * Asserts the page has no horizontal scrollbar (content fits the viewport width).
   *
   * @returns Resolves when the check passed
   */
  async expectNoHorizontalScroll(): Promise<void> {
    await expectNoHorizontalScroll(this.page);
  }
}

/**
 * Asserts that the document is not wider than the viewport.
 *
 * @param page Browser tab
 * @returns Resolves when the check passed
 */
export async function expectNoHorizontalScroll(page: Page): Promise<void> {
  // expect.poll retries the check, so late layout shifts (fonts, images) are taken into account
  await expect
    .poll(
      () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth),
      {
        message: 'The page must not scroll horizontally',
      },
    )
    .toBeLessThanOrEqual(0);
}
