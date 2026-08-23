import { promises as fs } from 'node:fs'
import path from 'node:path'
import { FileMigrationProvider, Migrator, type MigrationResult } from 'kysely/migration'
import type { AppDatabase } from './database.ts'

const MIGRATIONS_DIRECTORY = path.join(import.meta.dirname, 'migrations')

/**
 * Applies every migration the database has not seen yet and returns them in the
 * order they ran. Throws on the first failure, leaving the migrations before it
 * applied — SQLite has no transactional DDL to roll them back with.
 *
 * Every file in `migrations/` is loaded as a migration, so nothing else may
 * live in that directory — sidecar tests included.
 */
export async function migrateToLatest(db: AppDatabase): Promise<readonly MigrationResult[]> {
  const migrator = new Migrator({
    db,
    provider: new FileMigrationProvider({ fs, path, migrationFolder: MIGRATIONS_DIRECTORY }),
  })

  const { error, results } = await migrator.migrateToLatest()
  if (error) throw error

  return results ?? []
}
