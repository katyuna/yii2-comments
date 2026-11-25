import mysql from 'mysql2/promise';

/**
 * Direct access to the test database (`app_test`) for test setup.
 *
 * Tests use it only to prepare data (empty the table, insert rows with fixed dates) and to
 * check what was stored. Everything a user does still goes through the browser.
 */

/** A comment row as stored in the `comment` table. */
export interface CommentRow {
  id: number;
  author: string;
  text: string;
  ip: string;
  created_at: number;
  updated_at: number;
}

/** Data for a comment inserted directly into the DB. */
export interface NewComment {
  author: string;
  text: string;
  /** Unix time; fixed values make dates on screenshots stable */
  createdAt?: number;
  ip?: string;
}

/** Helper bound to one connection pool. */
export class TestDb {
  /**
   * @param pool mysql2 connection pool to `app_test`
   */
  constructor(private readonly pool: mysql.Pool) {}

  /**
   * Creates a pool from the same variables the app uses (`.env`).
   *
   * @returns Pool connected to the test DB
   */
  static createPool(): mysql.Pool {
    return mysql.createPool({
      host: process.env.DB_HOST ?? 'mysql',
      database: process.env.DB_TEST_NAME ?? 'app_test',
      user: process.env.DB_USER ?? 'app',
      password: process.env.DB_PASSWORD ?? '',
      // utf8mb4: emoji and other 4-byte characters must survive the round trip
      charset: 'utf8mb4',
      connectionLimit: 2,
    });
  }

  /**
   * Deletes all comments, so each test starts from an empty feed. The admin user stays.
   *
   * @returns Resolves when the table is empty
   */
  async resetComments(): Promise<void> {
    // TRUNCATE also resets AUTO_INCREMENT, so ids are predictable inside a test
    await this.pool.query('TRUNCATE TABLE comment');
  }

  /**
   * Inserts comments in the given order (the last one is the newest if no dates are given).
   *
   * @param comments Comments to insert
   * @returns Ids of the inserted rows, in the same order
   */
  async insertComments(comments: NewComment[]): Promise<number[]> {
    const ids: number[] = [];
    const now = Math.floor(Date.now() / 1000);

    for (const [index, comment] of comments.entries()) {
      // Without an explicit date, rows get increasing times one second apart
      const createdAt = comment.createdAt ?? now - comments.length + index;
      const [result] = await this.pool.execute<mysql.ResultSetHeader>(
        'INSERT INTO comment (author, text, ip, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
        [comment.author, comment.text, comment.ip ?? '127.0.0.1', createdAt, createdAt],
      );
      ids.push(result.insertId);
    }

    return ids;
  }

  /**
   * Returns all comments, newest first, the same order the feed uses.
   *
   * @returns Stored rows
   */
  async comments(): Promise<CommentRow[]> {
    const [rows] = await this.pool.query<(CommentRow & mysql.RowDataPacket)[]>(
      'SELECT * FROM comment ORDER BY created_at DESC, id DESC',
    );

    return rows;
  }
}
