import type { Locator, Page } from '@playwright/test';

/**
 * Page Object of the admin "Edit comment" form.
 */
export class EditCommentPage {
  readonly form: Locator;
  readonly author: Locator;
  readonly text: Locator;
  readonly textError: Locator;
  readonly save: Locator;
  readonly cancel: Locator;

  /**
   * @param page Browser tab
   */
  constructor(readonly page: Page) {
    this.form = page.getByTestId('admin-comment-form');
    this.author = page.getByTestId('admin-comment-form-author');
    this.text = page.getByTestId('admin-comment-form-text');
    this.textError = page.getByTestId('admin-comment-form-text-error');
    this.save = page.getByTestId('admin-comment-form-submit');
    this.cancel = page.getByTestId('admin-comment-form-cancel');
  }

  /**
   * Opens the edit form of a comment.
   *
   * @param id Comment id
   * @returns Resolves when the page is loaded
   */
  async goto(id: number): Promise<void> {
    await this.page.goto(`/admin/comment/update?id=${id}`);
  }
}
