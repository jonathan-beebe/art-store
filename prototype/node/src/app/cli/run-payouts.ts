import { fileURLToPath } from 'node:url'
import { parseAsOf } from './parse-as-of.ts'
import { runWeeklyPayout } from '../actions/escrow/run-weekly-payout.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { payoutPeriodEndingBefore, payoutPeriodLabel } from '../core/escrow/payout-period.ts'
import { formatCents } from '../core/money.ts'
import { openDatabase } from '../db/database.ts'

/** Runs the weekly payout against the configured database and prints one line
 * per seller paid. Importable so a test can run it against a temp database
 * without the process ever starting. */
export async function main(argv: readonly string[], env: NodeJS.ProcessEnv): Promise<void> {
  const config = loadConfig(env)
  const asOf = parseAsOf(argv.slice(2), systemClock.now())
  const db = openDatabase(config.databaseFile)

  try {
    const payouts = await runWeeklyPayout({ db, clock: systemClock }, asOf)

    console.log(`Payout period ${payoutPeriodLabel(payoutPeriodEndingBefore(asOf))}`)
    for (const payout of payouts) {
      console.log(`seller ${payout.sellerId} ${formatCents(payout.amountCents)}`)
    }
    console.log(
      payouts.length === 0
        ? 'No seller has a released balance for this period.'
        : `${payouts.length} seller(s) paid.`,
    )
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
