import { expect, test } from '../../fixtures/roles';

/**
 * Regression tests in the browser (checklist R-05, real-time part).
 */
test.describe('Regression', () => {
  /**
   * R-05: Cyrillic and emoji are not garbled on the way through the WebSocket.
   *
   * The live row is built from the JSON that PHP sends to Centrifugo (json_encode with
   * JSON_UNESCAPED_UNICODE), so it is a different path from the server-rendered feed,
   * which the functional test R-05 covers.
   *
   * @flowStep 5
   * @flowStep 8
   */
  test('R-05 cyrillic and emoji arrive in the live admin row unchanged @e2e @regression', async ({
    guest,
    admin,
  }) => {
    await admin.expectLive();
    const author = 'Ёжик 🦔';
    const text = `Привет, мир! 😀👍🏽 «кавычки» ${Date.now()}`;

    await guest.postComment(author, text);

    const row = admin.row(text);
    await expect(row).toBeVisible();
    await expect(row.getByTestId('admin-comment-author')).toHaveText(author);
    await expect(guest.item(text).getByTestId('comment-item-author')).toHaveText(author);
  });
});
