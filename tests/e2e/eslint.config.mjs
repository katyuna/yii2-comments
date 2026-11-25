// ESLint for the TypeScript tests: recommended TS rules + eslint-plugin-playwright
// (catches missing `await`, `waitForTimeout`, focused tests and other Playwright mistakes).
import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import playwright from 'eslint-plugin-playwright';

export default tseslint.config(
  { ignores: ['node_modules/', 'playwright-report/', 'test-results/'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['specs/**/*.ts'],
    ...playwright.configs['flat/recommended'],
    rules: {
      ...playwright.configs['flat/recommended'].rules,
      // CLAUDE.md: no fixed timeouts, web-first assertions only
      'playwright/no-wait-for-timeout': 'error',
      'playwright/no-wait-for-selector': 'error',
      'playwright/prefer-web-first-assertions': 'error',
      'playwright/no-force-option': 'error',
      // Specs assert through Page Object helpers (expect* methods) as well as expect()
      'playwright/expect-expect': ['error', { assertFunctionPatterns: ['^expect'] }],
    },
  },
);
