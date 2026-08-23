import { rm } from 'node:fs/promises'
import SQLite from 'better-sqlite3'
import { CamelCasePlugin, Kysely, SqliteDialect } from 'kysely'
import type { Database } from './schema.ts'

export type AppDatabase = Kysely<Database>

export const IN_MEMORY_DATABASE = ':memory:'

export function openDatabase(file: string): AppDatabase {
  const sqlite = new SQLite(file)

  // Foreign keys are per-connection in SQLite and off by default.
  sqlite.pragma('foreign_keys = ON')

  return new Kysely<Database>({
    dialect: new SqliteDialect({ database: sqlite }),
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
