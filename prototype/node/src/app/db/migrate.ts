import { parseArgs } from 'node:util'
import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { loadConfig } from '../config.ts'
import { createCliLogger } from '../logging.ts'
import { logLine, logStep, tellStory } from '../log-story.ts'
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
    await tellStory(
      log,
      {
        event: 'migrate.run',
        will: {
          msg: `migrating ${config.databaseFile}`,
          data: { database_file: config.databaseFile, fresh: values.fresh },
        },
        ended: (count) => ({
          phase: 'did',
          msg: `${config.databaseFile} is up to date (${count} applied)`,
          data: { database_file: config.databaseFile, count },
        }),
      },
      async () => {
        if (values.fresh) {
          await removeDatabaseFile(config.databaseFile)
          logStep(log, 'migrate.run', {
            msg: 'removing the database file',
            data: { database_file: config.databaseFile },
          })
        }

        return applyMigrations(log, config.databaseFile)
      },
    )
  } catch {
    // tellStory already wrote the `failed` line; the exit code is what is left.
    process.exitCode = 1
  }
}

/** How many migrations ran, with one line naming each. */
async function applyMigrations(log: pino.Logger, databaseFile: string): Promise<number> {
  const db = openDatabase(databaseFile)

  try {
    const applied = await migrateToLatest(db)

    for (const migration of applied) {
      logLine(log, 'info', 'migrate.apply', 'did', {
        msg: `${migration.status} ${migration.migrationName}`,
        data: { migration: migration.migrationName, status: migration.status },
      })
    }

    return applied.length
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
