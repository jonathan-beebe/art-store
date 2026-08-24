import type { SellerId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { ledgerMovements } from './ledger-movements.ts'
import { ledgerBalancesBySeller } from '../../core/escrow/ledger-balance.ts'
import { payoutMovement } from '../../core/escrow/ledger-movement.ts'
import { planWeeklyPayout, type PayoutIntent } from '../../core/escrow/payout-plan.ts'
import { payoutPeriodEndingBefore, payoutPeriodEndsAt, type PayoutPeriod } from '../../core/escrow/payout-period.ts'
import type { Payout } from '../../db/commerce-schema.ts'
import { toTimestamp, type Timestamp } from '../../db/timestamp.ts'

/**
 * Pays every seller the escrow released in the Monday-to-Sunday week that just
 * ended. The `paid_out` entry is dated at the close of the period it settles,
 * not the moment the run happens, so a second run of the same period reads the
 * money as already sent and pays nothing.
 */
export async function runWeeklyPayout(context: ActionContext, asOf: Date): Promise<readonly Payout[]> {
  return runInTransaction(context, async (transacted) => {
    const period = payoutPeriodEndingBefore(asOf)
    const endsAt = toTimestamp(payoutPeriodEndsAt(period))
    const movements = await ledgerMovements(transacted, endsAt)
    const settledSellerIds = await sellersSettledFor(transacted, period)
    const balances = ledgerBalancesBySeller(movements)
    const intents = planWeeklyPayout({ balances, settledSellerIds, period })

    const payouts: Payout[] = []
    for (const intent of intents) {
      payouts.push(await payOut(transacted, intent, endsAt, asOf))
    }

    return payouts
  })
}

async function sellersSettledFor(
  { db }: ActionContext,
  period: PayoutPeriod,
): Promise<ReadonlySet<SellerId>> {
  const rows = await db
    .selectFrom('payouts')
    .select('sellerId')
    .where('periodStart', '=', period.firstDay)
    .execute()

  return new Set(rows.map((row) => row.sellerId))
}

async function payOut(
  { db }: ActionContext,
  intent: PayoutIntent,
  endsAt: Timestamp,
  asOf: Date,
): Promise<Payout> {
  const payout = await db
    .insertInto('payouts')
    .values({
      id: newId('pyt', asOf),
      sellerId: intent.sellerId,
      periodStart: intent.periodStart,
      periodEnd: intent.periodEnd,
      amountCents: intent.amountCents,
      paidAt: toTimestamp(asOf),
    })
    .returningAll()
    .executeTakeFirstOrThrow()

  const movement = payoutMovement(intent.amountCents)

  await db
    .insertInto('ledgerEntries')
    .values({
      id: newId('led', asOf),
      sellerId: intent.sellerId,
      fulfillmentId: null,
      payoutId: payout.id,
      entryType: movement.entryType,
      amountCents: movement.amountCents,
      occurredAt: endsAt,
    })
    .execute()

  return payout
}
