// Allure 3 report settings for `make test-report`.
// One report for every suite: Codeception (unit, functional, smoke) and Playwright (UI, E2E)
// both write results to tests/allure/results (mounted at /allure in the playwright container).
import { defineConfig } from 'allure';

export default defineConfig({
  name: 'Yii2 Comments — all test suites',
  output: '/allure/report',
  plugins: {
    awesome: {
      options: {
        // One self-contained index.html: opens with a double click, no web server needed
        singleFile: true,
        // Interface language of the report; "ru" is also supported
        reportLanguage: 'en',
        // Tree in the report: suite → file/class → test
        groupBy: ['parentSuite', 'suite', 'subSuite'],
      },
    },
  },
});
