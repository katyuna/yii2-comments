import { test as base, expect } from '@playwright/test';
import type { Pool } from 'mysql2/promise';
import { TestDb } from './db';

/**
 * The `test` object every spec imports instead of `@playwright/test`.
 *
 * It adds fixtures (Playwright's dependency injection for tests):
 * - `db`: helper for the `app_test` database. It is an *auto* fixture: before every test
 *   it empties the comment table, so tests do not depend on each other or on their order.
 *
 * Page Objects are added on top of this in `fixtures/pages.ts`.
 */

/** Fixtures created once per test. */
export interface TestFixtures {
  db: TestDb;
}

/** Fixtures shared by all tests of one worker process. */
interface WorkerFixtures {
  dbPool: Pool;
}

export const test = base.extend<TestFixtures, WorkerFixtures>({
  // One pool per worker: opening a DB connection for every test would be slow
  dbPool: [
    // Playwright requires object destructuring here even when no other fixture is used
    // eslint-disable-next-line no-empty-pattern
    async ({}, use) => {
      const pool = TestDb.createPool();
      await use(pool);
      await pool.end();
    },
    { scope: 'worker' },
  ],

  db: [
    async ({ dbPool }, use) => {
      const db = new TestDb(dbPool);
      await db.resetComments();
      await use(db);
    },
    { auto: true },
  ],
});

export { expect };

/** Admin credentials from `.env` (the test stand creates the admin from the same variables). */
export const admin = {
  username: process.env.ADMIN_USERNAME ?? 'admin',
  password: process.env.ADMIN_PASSWORD ?? '',
};

/**
 * Makes a text that no other test uses, so a test finds exactly its own comment.
 *
 * @param prefix Readable part, e.g. the test ID
 * @returns Unique text
 */
export function uniqueText(prefix: string): string {
  return `${prefix} ${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}
