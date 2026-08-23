import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { openDatabase } from './database.ts'
import { seedAdmins } from './seed-admins.ts'

const config = loadConfig(process.env)
const db = openDatabase(config.databaseFile)

try {
  console.log(`seeded ${await seedAdmins({ db, clock: systemClock })} admins`)
} finally {
  await db.destroy()
}
