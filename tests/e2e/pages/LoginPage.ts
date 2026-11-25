import type { Locator, Page } from '@playwright/test';

/**
 * Page Object of the admin login page.
 */
export class LoginPage {
  readonly form: Locator;
  readonly username: Locator;
  readonly password: Locator;
  readonly rememberMe: Locator;
  readonly submit: Locator;
  readonly usernameError: Locator;
  readonly passwordError: Locator;

  /**
   * @param page Browser tab
   */
  constructor(readonly page: Page) {
    this.form = page.getByTestId('login-form');
    this.username = page.getByTestId('login-username');
    this.password = page.getByTestId('login-password');
    this.rememberMe = page.getByTestId('login-remember');
    this.submit = page.getByTestId('login-submit');
    this.usernameError = page.getByTestId('login-username-error');
    this.passwordError = page.getByTestId('login-password-error');
  }

  /**
   * Opens the login page.
   *
   * @returns Resolves when the page is loaded
   */
  async goto(): Promise<void> {
    await this.page.goto('/admin/auth/login');
  }

  /**
   * Fills in the form and submits it.
   *
   * @param username Username
   * @param password Password
   * @returns Resolves after the next page has loaded
   */
  async logIn(username: string, password: string): Promise<void> {
    await this.username.fill(username);
    await this.password.fill(password);
    await this.submit.click();
    await this.page.waitForLoadState('domcontentloaded');
  }
}
