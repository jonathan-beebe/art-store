import { promises as fs } from 'node:fs'
import path from 'node:path'
import { inspect } from 'node:util'
import {
  FileMigrationProvider,
  Migrator,
  NO_MIGRATIONS,
  type MigrationInfo,
  type MigrationResult,
} from 'kysely/migration'
import type { AppDatabase } from './database.ts'

const MIGRATIONS_DIRECTORY = path.join(import.meta.dirname, 'migrations')

/**
 * Every file in `migrations/` is loaded as a migration, so nothing else may
 * live in that directory — sidecar tests included.
 */
function buildMigrator(db: AppDatabase): Migrator {
  return new Migrator({
    db,
    provider: new FileMigrationProvider({ fs, path, migrationFolder: MIGRATIONS_DIRECTORY }),
  })
}

/**
 * Applies every migration the database has not seen yet and returns them in the
 * order they ran. Throws on the first failure, leaving the migrations before it
 * applied — SQLite has no transactional DDL to roll them back with.
 */
export async function migrateToLatest(db: AppDatabase): Promise<readonly MigrationResult[]> {
  const { error, results } = await buildMigrator(db).migrateToLatest()
  if (error !== undefined) throw migrationFailure(error)

  return results ?? []
}

/**
 * Runs every applied migration's `down()` in reverse order, leaving the
 * database as it was before any migration ran.
 */
export async function migrateDown(db: AppDatabase): Promise<readonly MigrationResult[]> {
  const { error, results } = await buildMigrator(db).migrateTo(NO_MIGRATIONS)
  if (error !== undefined) throw migrationFailure(error)

  return results ?? []
}

/** Migrations the database has not applied yet, in the order they would run. */
export async function pendingMigrations(db: AppDatabase): Promise<readonly MigrationInfo[]> {
  const migrations = await buildMigrator(db).getMigrations()

  return migrations.filter((migration) => migration.executedAt === undefined)
}

/**
 * Kysely reports a failed migration as an `unknown`, and a thrown non-Error
 * arrives at the caller with no stack to read.
 */
function migrationFailure(error: unknown): Error {
  return error instanceof Error ? error : new Error(inspect(error))
}
