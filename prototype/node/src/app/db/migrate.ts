import { loadConfig } from '../config.ts'
import { openDatabase, removeDatabaseFile } from './database.ts'
import { migrateToLatest } from './migrator.ts'

const config = loadConfig(process.env)

if (process.argv.includes('--fresh')) {
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
