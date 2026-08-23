import { parseAsOf } from './parse-as-of.ts'
import { runWeeklyPayout } from '../actions/escrow/run-weekly-payout.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { payoutPeriodEndingBefore, payoutPeriodLabel } from '../core/escrow/payout-period.ts'
import { formatCents } from '../core/money.ts'
import { openDatabase } from '../db/database.ts'

const config = loadConfig(process.env)
const asOf = parseAsOf(process.argv.slice(2), systemClock.now())
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
