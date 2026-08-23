import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { ledgerMovements, type SellerLedgerMovement } from './ledger-movements.ts'
import { isPayable, ledgerBalance } from '../../core/escrow/ledger-balance.ts'
import { payoutMovement } from '../../core/escrow/ledger-movement.ts'
import {
  payoutPeriodEndingBefore,
  payoutPeriodEndsAt,
  type PayoutPeriod,
} from '../../core/escrow/payout-period.ts'
import type { Cents } from '../../core/money.ts'
import type { Payout } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

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

    const payouts: Payout[] = []
    for (const [sellerId, balance] of payableBalances(movements)) {
      payouts.push(await payOut(transacted, sellerId, balance, period, asOf))
    }

    return payouts
  })
}

function payableBalances(movements: readonly SellerLedgerMovement[]): Map<number, Cents> {
  const bySeller = new Map<number, SellerLedgerMovement[]>()
  for (const movement of movements) {
    bySeller.set(movement.sellerId, [...(bySeller.get(movement.sellerId) ?? []), movement])
  }

  const payable = new Map<number, Cents>()
  for (const [sellerId, own] of bySeller) {
    const balance = ledgerBalance(own)
    if (isPayable(balance)) payable.set(sellerId, balance.availableCents)
  }

  return payable
}

async function payOut(
  { db }: ActionContext,
  sellerId: number,
  availableCents: Cents,
  period: PayoutPeriod,
  asOf: Date,
): Promise<Payout> {
  const payout = await db
    .insertInto('payouts')
    .values({
      sellerId,
      periodStart: period.firstDay,
      periodEnd: period.lastDay,
      amountCents: availableCents,
      paidAt: toTimestamp(asOf),
    })
    .returningAll()
    .executeTakeFirstOrThrow()

  const movement = payoutMovement(availableCents)

  await db
    .insertInto('ledgerEntries')
    .values({
      sellerId,
      fulfillmentId: null,
      payoutId: payout.id,
      entryType: movement.entryType,
      amountCents: movement.amountCents,
      occurredAt: toTimestamp(payoutPeriodEndsAt(period)),
    })
    .execute()

  return payout
}
