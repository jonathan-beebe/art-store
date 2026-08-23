import type { Kysely } from 'kysely'

/**
 * Page views rolled up rather than logged per hit: one row per (site, route
 * pattern, day), incremented by a response hook. The pattern is the route's
 * (`/art/:slug`), so a thousand listing pages share one row.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('page_view_counts')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('site', 'text', (column) => column.notNull())
    .addColumn('path_pattern', 'text', (column) => column.notNull())
    .addColumn('day', 'text', (column) => column.notNull())
    .addColumn('count', 'integer', (column) => column.notNull().defaultTo(0))
    .execute()

  await db.schema
    .createIndex('page_view_counts_site_path_pattern_day_index')
    .on('page_view_counts')
    .columns(['site', 'path_pattern', 'day'])
    .unique()
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('page_view_counts').execute()
}
