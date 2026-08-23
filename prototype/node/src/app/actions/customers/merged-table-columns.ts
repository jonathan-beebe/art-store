import { sql } from 'kysely'
import { REPOINTED_CUSTOMER_TABLES } from '../../core/customers/repointed-customer-tables.ts'
import type { AppDatabase } from '../../db/database.ts'

/** Column names by table, holding only the tables a merge touches. */
export type MergedTableColumns = ReadonlyMap<string, ReadonlySet<string>>

const MERGED_TABLES: readonly string[] = [
  ...REPOINTED_CUSTOMER_TABLES.map((entry) => entry.table),
  'favorites',
  'carts',
  'cart_items',
  'listings',
]

/**
 * What the schema actually holds right now. The commerce tables arrive on their
 * own schedule, so a merge asks rather than assumes and does the part of the
 * fold the database can support.
 */
export async function readMergedTableColumns(db: AppDatabase): Promise<MergedTableColumns> {
  const present = await sql<{ name: string }>`
    select name from sqlite_master where type = 'table' and name in (${sql.join(MERGED_TABLES)})
  `.execute(db)

  const columns = new Map<string, ReadonlySet<string>>()

  for (const table of present.rows) {
    const info = await sql<{ name: string }>`select name from pragma_table_info(${table.name})`.execute(
      db,
    )

    columns.set(table.name, new Set(info.rows.map((column) => column.name)))
  }

  return columns
}

export function hasColumns(
  schema: MergedTableColumns,
  table: string,
  ...columns: readonly string[]
): boolean {
  const known = schema.get(table)

  return known !== undefined && columns.every((column) => known.has(column))
}
