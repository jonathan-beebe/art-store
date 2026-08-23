import { parseArgs } from 'node:util'
import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { loadConfig } from '../config.ts'
import { createCliLogger } from '../logging.ts'
import { openDatabase, removeDatabaseFile } from './database.ts'
import { migrateToLatest } from './migrator.ts'

/**
 * Applies every pending migration, optionally after deleting the database
 * file first (`--fresh`). A failure is logged and leaves `process.exitCode`
 * at 1 rather than crashing with a raw stack trace. Importable, with an
 * injectable `logger`, so a test can run it against a temp database without
 * the process ever starting.
 */
export async function main(
  argv: readonly string[],
  env: NodeJS.ProcessEnv,
  logger?: pino.Logger,
): Promise<void> {
  const config = loadConfig(env)
  const log = logger ?? createCliLogger(config)

  const { values } = parseArgs({
    args: argv.slice(2),
    options: { fresh: { type: 'boolean', default: false } },
    strict: true,
  })

  try {
    if (values.fresh) {
      await removeDatabaseFile(config.databaseFile)
      log.info({ event: 'migrate.removed', databaseFile: config.databaseFile }, 'removed the database file')
    }

    const db = openDatabase(config.databaseFile)

    try {
      const applied = await migrateToLatest(db)

      for (const migration of applied) {
        log.info(
          { event: 'migrate.applied', migration: migration.migrationName, status: migration.status },
          `${migration.status} ${migration.migrationName}`,
        )
      }

      log.info(
        { event: 'migrate.run', databaseFile: config.databaseFile, count: applied.length },
        `${config.databaseFile} is up to date (${applied.length} applied)`,
      )
    } finally {
      await db.destroy()
    }
  } catch (error) {
    log.error({ err: error }, 'the migration run failed')
    process.exitCode = 1
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
