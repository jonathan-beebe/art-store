import { sql } from 'kysely'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'

// Built once per test process and shared by every `applySchemaTemplate` call;
// concurrent first calls await the same build rather than migrating twice.
// Never cached to disk, so an edited migration takes effect on the next run.
let templateStatements: Promise<readonly string[]> | null = null

/**
 * Brings a fresh in-memory database to the same state `migrateToLatest` would
 * leave it in, without running the migrator itself — replaying a dumped
 * statement list is far cheaper than the ~750 migrator runs a suite would
 * otherwise pay for, one per fixture. `migrator.test.ts` and its neighbors
 * still exercise the real migrator on their own databases; this template only
 * shortcuts the fixtures' path.
 */
export async function applySchemaTemplate(db: AppDatabase): Promise<void> {
  templateStatements ??= dumpMigratedTemplate()

  for (const statement of await templateStatements) {
    await sql.raw(statement).execute(db)
  }
}

async function dumpMigratedTemplate(): Promise<readonly string[]> {
  const template = openDatabase(IN_MEMORY_DATABASE)

  try {
    await migrateToLatest(template)

    // A migration that only issues a PRAGMA (the write-ahead-logging one)
    // leaves no `sqlite_master` row, and `PRAGMA journal_mode = WAL` is a
    // no-op on `:memory:` regardless — so DDL plus the bookkeeping rows below
    // is everything the migrator produces on an in-memory database.
    const ddl = await sql<{ sql: string }>`select sql from sqlite_master where sql is not null`.execute(
      template,
    )
    const applied = await sql<{ name: string; timestamp: string }>`select name, timestamp from kysely_migration order by name`.execute(
      template,
    )
    // CamelCasePlugin camelizes result keys, so the `is_locked` column comes
    // back as `isLocked` here despite the query naming the SQLite column.
    const lock = await sql<{ id: string; isLocked: number }>`select id, is_locked from kysely_migration_lock`.execute(
      template,
    )

    return [
      // A NULL sql row is an auto-index a UNIQUE constraint created; the
      // CREATE TABLE statements below recreate those on replay.
      ...ddl.rows.map((row) => row.sql),
      // `app/plugins/health.ts` asks `pendingMigrations` of the fixture
      // database, so the bookkeeping tables must already show every
      // migration applied or health would report migrations pending.
      ...applied.rows.map(
        (row) => `insert into kysely_migration (name, timestamp) values (${quoted(row.name)}, ${quoted(row.timestamp)})`,
      ),
      ...lock.rows.map((row) => `insert into kysely_migration_lock (id, is_locked) values (${quoted(row.id)}, ${row.isLocked})`),
    ]
  } finally {
    await template.destroy()
  }
}

function quoted(value: string): string {
  return `'${value.replaceAll("'", "''")}'`
}
