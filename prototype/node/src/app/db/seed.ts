import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { createCliLogger } from '../logging.ts'
import { logStep, tellStory } from '../log-story.ts'
import { openDatabase } from './database.ts'
import { seedAdmins } from './seed-admins.ts'
import { seedDemoData, type SeedDemoDataSummary } from './seed-demo-data.ts'
import { seedWizardingSellers } from './seed-wizarding-sellers.ts'

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
    await tellStory(
      log,
      {
        event: 'seed.run',
        will: {
          msg: `seeding ${config.databaseFile}`,
          data: { database_file: config.databaseFile },
        },
        ended: (summary) => ({
          phase: 'did',
          msg: summary === null ? 'demo data already seeded, skipping' : summarySentence(summary),
          data: summary === null ? { skipped: true } : { ...summary },
        }),
      },
      async () => {
        const adminCount = await seedAdmins({ db, clock: systemClock })
        logStep(log, 'seed.run', {
          msg: `seeded ${adminCount} admins`,
          data: { admin_count: adminCount },
        })

        const demoSummary = await seedDemoData({ db, clock: systemClock })

        // After the demo data: its already-seeded check reads "any seller
        // exists", so seeding these two first would skip the whole demo.
        const wizarding = await seedWizardingSellers(db)
        logStep(log, 'seed.run', {
          msg:
            wizarding === null
              ? 'wizarding sellers already seeded, skipping'
              : `seeded ${wizarding.sellerCount} wizarding sellers, ${wizarding.listingCount} listings`,
          data: wizarding === null ? { wizarding_skipped: true } : { ...wizarding },
        })

        return demoSummary
      },
    )
  } catch {
    // tellStory already wrote the `failed` line; the exit code is what is left.
    process.exitCode = 1
  } finally {
    await db.destroy()
  }
}

function summarySentence(summary: SeedDemoDataSummary): string {
  return `seeded ${summary.sellerCount} sellers, ${summary.listingCount} listings, ${summary.customerCount} customers, ${summary.orderCount} orders, ${summary.pageViewRowCount} page-view rows, ${summary.conversationCount} conversations, ${summary.messageCount} messages, ${summary.faqCount} listing FAQ`
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
