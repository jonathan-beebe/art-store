import { parseArgs } from 'node:util'
import { fileURLToPath } from 'node:url'
import { loadConfig } from '../config.ts'
import { openDatabase, removeDatabaseFile } from './database.ts'
import { migrateToLatest } from './migrator.ts'

/** Applies every pending migration, optionally after deleting the database
 * file first (`--fresh`). Importable so a test can run it against a temp
 * database without the process ever starting. */
export async function main(argv: readonly string[], env: NodeJS.ProcessEnv): Promise<void> {
  const config = loadConfig(env)

  const { values } = parseArgs({
    args: argv.slice(2),
    options: { fresh: { type: 'boolean', default: false } },
    strict: true,
  })

  if (values.fresh) {
    await removeDatabaseFile(config.databaseFile)
    console.log(`removed ${config.databaseFile}`)
  }

  const db = openDatabase(config.databaseFile)

  try {
    const applied = await migrateToLatest(db)

    for (const migration of applied) {
      console.log(`${migration.status} ${migration.migrationName}`)
    }

    console.log(`${config.databaseFile} is up to date (${applied.length} applied)`)
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
