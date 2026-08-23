import { sql, type Kysely } from 'kysely'

/**
 * The journal mode is stored in the database file, so it is set once here
 * rather than on every connection. A write-ahead log lets the storefront read
 * while the seller portal writes.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await sql`PRAGMA journal_mode = WAL`.execute(db)
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await sql`PRAGMA journal_mode = DELETE`.execute(db)
}
