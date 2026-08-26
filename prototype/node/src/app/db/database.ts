import { rm } from 'node:fs/promises'
import type { DatabaseSync } from 'node:sqlite'
import { CamelCasePlugin, Kysely } from 'kysely'
import { NodeSqliteDialect } from './node-sqlite-dialect.ts'
import type { LogsDatabase } from './logs-schema.ts'
import type { Database } from './schema.ts'

export type AppDatabase = Kysely<Database>

export type LogsDb = Kysely<LogsDatabase>

export const IN_MEMORY_DATABASE = ':memory:'

export function openDatabase(file: string): AppDatabase {
  return new Kysely<Database>({
    dialect: new NodeSqliteDialect(file),
    plugins: [new CamelCasePlugin()],
  })
}

/**
 * The admin reader over the log store's own open handle. One handle per
 * process — the batch writer prepares against the same one — so reads and
 * writes serialize instead of racing, and a test's `:memory:` store is
 * visible to both sides.
 */
export function openLogsDatabase(database: DatabaseSync): LogsDb {
  return new Kysely<LogsDatabase>({
    dialect: new NodeSqliteDialect(database),
    plugins: [new CamelCasePlugin()],
  })
}

/**
 * Deletes the database and the write-ahead log beside it, so the next migration
 * run rebuilds from nothing. An in-memory database has no files and is skipped.
 */
export async function removeDatabaseFile(file: string): Promise<void> {
  if (file === IN_MEMORY_DATABASE) return

  await Promise.all(
    [file, `${file}-shm`, `${file}-wal`].map((path) => rm(path, { force: true })),
  )
}
