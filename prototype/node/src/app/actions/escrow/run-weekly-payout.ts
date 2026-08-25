import type { SellerId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionDid, actionStory } from '../action-story.ts'
import { writeLedgerEntry } from './write-ledger-entry.ts'
import { sellerBalances } from './ledger-balances.ts'
import { payoutMovement } from '../../core/escrow/ledger-movement.ts'
import { planWeeklyPayout, payoutTotal, type PayoutIntent } from '../../core/escrow/payout-plan.ts'
import {
  payoutPeriodEndingBefore,
  payoutPeriodEndsAt,
  payoutPeriodLabel,
  type PayoutPeriod,
} from '../../core/escrow/payout-period.ts'
import { formatCents } from '../../core/money.ts'
import type { Payout } from '../../db/commerce-schema.ts'
import { toTimestamp, type Timestamp } from '../../db/timestamp.ts'

/**
 * Pays every seller the escrow released in the Monday-to-Sunday week that just
 * ended. The `paid_out` entry is dated at the close of the period it settles,
 * not the moment the run happens, so a second run of the same period reads the
 * money as already sent and pays nothing.
 *
 * The run is one story with a `payout.pay` line inside it per seller, so the
 * whole week's money reads back off one `txn_id`.
 */
export async function runWeeklyPayout(
  context: ActionContext,
  asOf: Date,
): Promise<readonly Payout[]> {
  const period = payoutPeriodEndingBefore(asOf)
  const label = payoutPeriodLabel(period)

  return actionStory<readonly Payout[]>(
    context,
    {
      event: 'payout.run',
      will: { msg: `paying out ${label}`, data: { period: label } },
      ended: (payouts) => ({
        phase: 'did',
        msg:
          payouts.length === 0
            ? 'no seller has a released balance for this period'
            : `${payouts.length} seller(s) paid`,
        data: { period: label, count: payouts.length, total_cents: payoutTotal(payouts) },
      }),
    },
    async (transacted) => {
      const endsAt = toTimestamp(payoutPeriodEndsAt(period))
      const balances = await sellerBalances(transacted, endsAt)
      const settledSellerIds = await sellersSettledFor(transacted, period)
      const intents = planWeeklyPayout({ balances, settledSellerIds, period })

      const payouts: Payout[] = []
      for (const intent of intents) {
        payouts.push(await payOut(transacted, intent, endsAt, asOf, label))
      }

      return payouts
    },
  )
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
  context: ActionContext,
  intent: PayoutIntent,
  endsAt: Timestamp,
  asOf: Date,
  period: string,
): Promise<Payout> {
  const payout = await context.db
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

  await writeLedgerEntry(
    context,
    {
      sellerId: intent.sellerId,
      fulfillmentId: null,
      payoutId: payout.id,
      movement: payoutMovement(intent.amountCents),
      occurredAt: endsAt,
    },
    asOf,
  )

  actionDid(
    context,
    'payout.pay',
    `seller ${intent.sellerId} ${formatCents(intent.amountCents)}`,
    {
      payout_id: payout.id,
      seller_id: intent.sellerId,
      amount_cents: intent.amountCents,
      period,
    },
  )

  return payout
}
