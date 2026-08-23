import { fileURLToPath } from 'node:url'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { openDatabase } from './database.ts'
import { seedAdmins } from './seed-admins.ts'
import { seedDemoData } from './seed-demo-data.ts'

/** Seeds admins and demo data into the configured database. Importable so a
 * test can run it against a temp database without the process ever starting. */
export async function main(_argv: readonly string[], env: NodeJS.ProcessEnv): Promise<void> {
  const config = loadConfig(env)
  const db = openDatabase(config.databaseFile)

  try {
    console.log(`seeded ${await seedAdmins({ db, clock: systemClock })} admins`)

    const summary = await seedDemoData({ db, clock: systemClock })
    console.log(
      summary === null
        ? 'demo data already seeded, skipping.'
        : `seeded ${summary.sellerCount} sellers, ${summary.listingCount} listings, ${summary.customerCount} customers, ${summary.orderCount} orders, ${summary.pageViewRowCount} page-view rows, ${summary.conversationCount} conversations, ${summary.messageCount} messages, ${summary.faqCount} listing FAQ.`,
    )
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
