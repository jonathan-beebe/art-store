import { sql, type Kysely } from 'kysely'
import { RATE_LIMIT_NAMES } from '../../core/rate-limit/rate-limit-name.ts'

/**
 * One fixed-window counter per (name, key, window_start), `docs/alignment.md`
 * §3: a `preHandler` upserts the row for the window `now` falls in and reads
 * `count` back in the same statement, so the counter survives a process
 * restart the way an in-memory map never could.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('rate_limit_windows')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('name', 'text', (column) =>
      column.notNull().check(sql`name in (${sql.join(RATE_LIMIT_NAMES.map((name) => sql.lit(name)))})`),
    )
    .addColumn('key', 'text', (column) => column.notNull())
    .addColumn('window_start', 'text', (column) => column.notNull())
    .addColumn('count', 'integer', (column) => column.notNull().defaultTo(0))
    .execute()

  await db.schema
    .createIndex('rate_limit_windows_name_key_window_start_index')
    .on('rate_limit_windows')
    .columns(['name', 'key', 'window_start'])
    .unique()
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('rate_limit_windows').execute()
}
