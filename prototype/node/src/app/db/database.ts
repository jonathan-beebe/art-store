import { rm } from 'node:fs/promises'
import { CamelCasePlugin, Kysely } from 'kysely'
import { NodeSqliteDialect } from './node-sqlite-dialect.ts'
import type { Database } from './schema.ts'

export type AppDatabase = Kysely<Database>

export const IN_MEMORY_DATABASE = ':memory:'

export function openDatabase(file: string): AppDatabase {
  return new Kysely<Database>({
    dialect: new NodeSqliteDialect(file),
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
