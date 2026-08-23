import type { FastifyInstance } from 'fastify'
import { buildApp, type AppDependencies } from '../app.ts'
import { fixedClock, type Clock } from '../clock.ts'
import type { AppConfig } from '../config.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'

/** Frozen so payout periods and link expiries read the same whatever day it is. */
export const TEST_INSTANT = new Date('2026-08-24T12:00:00.000Z')

export const TEST_CONFIG: AppConfig = {
  host: '127.0.0.1',
  port: 0,
  databaseFile: IN_MEMORY_DATABASE,
  cookieSecret: 'test-cookie-secret-long-enough',
  logLevel: 'silent',
}

export type TestApp = {
  app: FastifyInstance
  db: AppDatabase
  clock: Clock
  close(): Promise<void>
}

/**
 * Builds the whole application over a migrated in-memory database, ready for
 * `app.inject`. Pass `t.after(close)` so the database goes with the test.
 */
export async function buildTestApp(overrides: Partial<AppDependencies> = {}): Promise<TestApp> {
  const db = overrides.db ?? openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const clock = overrides.clock ?? fixedClock(TEST_INSTANT)
  const app = buildApp({ db, clock, config: overrides.config ?? TEST_CONFIG })
  await app.ready()

  return {
    app,
    db,
    clock,
    close: async () => {
      await app.close()
      await db.destroy()
    },
  }
}
