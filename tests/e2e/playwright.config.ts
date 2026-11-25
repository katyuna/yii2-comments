import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for the UI and E2E suites.
 *
 * Tests run in the `playwright` container against the test stand (`nginx-test`, DB `app_test`),
 * see docker-compose.yml. Run them with `make test-ui` and `make test-e2e`.
 *
 * Projects:
 * - `ui`            — one page at a time: layout, states, accessibility, screenshots (Chromium);
 * - `e2e-chromium`  — full user flows, guest and admin in two browser contexts;
 * - `e2e-firefox`,
 *   `e2e-webkit`    — the same flows tagged `@cross-browser`, to catch browser differences.
 */
export default defineConfig({
  testDir: './specs',
  // Every test resets the shared app_test DB (see fixtures/test.ts), so tests must not overlap.
  // Scale with sharding (`--shard=1/3`): each shard gets its own test stand in CI.
  fullyParallel: false,
  workers: 1,
  // A forgotten `test.only` must not silently skip the rest of the suite in CI
  forbidOnly: !!process.env.CI,
  // No retries: a flaky test is a bug to fix, not to hide
  retries: 0,
  timeout: 30_000,
  expect: {
    timeout: 10_000,
    toHaveScreenshot: {
      // Font anti-aliasing differs by a few pixels between runs
      maxDiffPixelRatio: 0.01,
      animations: 'disabled',
    },
  },
  // Baselines are made in the Linux container, so they are the same for everyone and for CI
  snapshotPathTemplate: '{testDir}/__screenshots__/{testFilePath}/{arg}{ext}',
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ['junit', { outputFile: 'test-results/junit.xml' }],
    // Results for the combined Allure report (make test-report), next to the Codeception ones
    ['allure-playwright', { resultsDir: process.env.ALLURE_RESULTS_DIR ?? 'allure-results' }],
  ],
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8081',
    // Selectors: data-testid only (getByTestId), as CLAUDE.md requires
    testIdAttribute: 'data-testid',
    // Artifacts are kept only for failed tests: trace (time-travel debugger), screenshot, video
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    // Same date format and texts on every machine
    locale: 'en-US',
    timezoneId: 'UTC',
  },
  projects: [
    {
      name: 'ui',
      testDir: './specs/ui',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'e2e-chromium',
      testDir: './specs/e2e',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'e2e-firefox',
      testDir: './specs/e2e',
      grep: /@cross-browser/,
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'e2e-webkit',
      testDir: './specs/e2e',
      grep: /@cross-browser/,
      use: { ...devices['Desktop Safari'] },
    },
  ],
});
