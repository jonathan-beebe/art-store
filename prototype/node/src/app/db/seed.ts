import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { createCliLogger } from '../logging.ts'
import { openDatabase } from './database.ts'
import { seedAdmins } from './seed-admins.ts'
import { seedDemoData } from './seed-demo-data.ts'

/**
 * Seeds admins and demo data into the configured database. A failure is
 * logged and leaves `process.exitCode` at 1 rather than crashing with a raw
 * stack trace. Importable, with an injectable `logger`, so a test can run it
 * against a temp database without the process ever starting.
 */
export async function main(
  _argv: readonly string[],
  env: NodeJS.ProcessEnv,
  logger?: pino.Logger,
): Promise<void> {
  const config = loadConfig(env)
  const log = logger ?? createCliLogger(config)
  const db = openDatabase(config.databaseFile)

  try {
    const adminCount = await seedAdmins({ db, clock: systemClock })
    log.info({ event: 'seed.admins', count: adminCount }, `seeded ${adminCount} admins`)

    const summary = await seedDemoData({ db, clock: systemClock })
    log.info(
      { event: 'seed.demo_data', ...(summary ?? {}) },
      summary === null
        ? 'demo data already seeded, skipping'
        : `seeded ${summary.sellerCount} sellers, ${summary.listingCount} listings, ${summary.customerCount} customers, ${summary.orderCount} orders, ${summary.pageViewRowCount} page-view rows, ${summary.conversationCount} conversations, ${summary.messageCount} messages, ${summary.faqCount} listing FAQ`,
    )
  } catch (error) {
    log.error({ err: error }, 'the seed run failed')
    process.exitCode = 1
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
