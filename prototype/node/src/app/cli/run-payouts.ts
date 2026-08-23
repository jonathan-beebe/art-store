import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { parseAsOf } from './parse-as-of.ts'
import { runWeeklyPayout } from '../actions/escrow/run-weekly-payout.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { payoutPeriodEndingBefore, payoutPeriodLabel } from '../core/escrow/payout-period.ts'
import { payoutTotal } from '../core/escrow/payout-plan.ts'
import { formatCents } from '../core/money.ts'
import { openDatabase } from '../db/database.ts'
import { createCliLogger } from '../logging.ts'

/**
 * Runs the weekly payout against the configured database: one structured line
 * per seller paid, then a summary. A failed run is logged and leaves
 * `process.exitCode` at 1 rather than crashing with a raw stack trace, so a
 * scheduler sees it without one. Importable, with an injectable `logger`, so a
 * test can run it against a temp database without the process ever starting.
 */
export async function main(
  argv: readonly string[],
  env: NodeJS.ProcessEnv,
  logger?: pino.Logger,
): Promise<void> {
  const config = loadConfig(env)
  const log = logger ?? createCliLogger(config)
  const asOf = parseAsOf(argv.slice(2), systemClock.now())
  const db = openDatabase(config.databaseFile)

  try {
    const payouts = await runWeeklyPayout({ db, clock: systemClock }, asOf)
    const period = payoutPeriodLabel(payoutPeriodEndingBefore(asOf))

    for (const payout of payouts) {
      log.info(
        { event: 'payout.paid', sellerId: payout.sellerId, amountCents: payout.amountCents, period },
        `seller ${payout.sellerId} ${formatCents(payout.amountCents)}`,
      )
    }

    log.info(
      { event: 'payout.run', period, count: payouts.length, totalCents: payoutTotal(payouts) },
      payouts.length === 0
        ? 'no seller has a released balance for this period'
        : `${payouts.length} seller(s) paid`,
    )
  } catch (error) {
    log.error({ err: error }, 'the payout run failed')
    process.exitCode = 1
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
