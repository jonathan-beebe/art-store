import { addCents, ZERO_CENTS, type Cents } from '../money.ts'
import { isPayable, type LedgerBalance } from './ledger-balance.ts'
import type { PayoutPeriod } from './payout-period.ts'
import type { SellerId } from '../ids/entity-ids.ts'

/** One seller's share of a weekly payout run — who gets paid, how much, for which period. */
export type PayoutIntent = {
  sellerId: SellerId
  amountCents: Cents
  periodStart: string
  periodEnd: string
}

export type PlanWeeklyPayoutInput = {
  balances: ReadonlyMap<SellerId, LedgerBalance>
  settledSellerIds: ReadonlySet<SellerId>
  period: PayoutPeriod
}

/**
 * Every seller to pay this run: a payable balance, not already settled for
 * this period. A seller has at most one payout per period, so one already in
 * `settledSellerIds` is skipped even though their balance is payable — the
 * money that produced it settles in the run whose window reaches it.
 */
export function planWeeklyPayout({ balances, settledSellerIds, period }: PlanWeeklyPayoutInput): readonly PayoutIntent[] {
  const intents: PayoutIntent[] = []

  for (const [sellerId, balance] of balances) {
    if (settledSellerIds.has(sellerId)) continue
    if (!isPayable(balance)) continue

    intents.push({
      sellerId,
      amountCents: balance.availableCents,
      periodStart: period.firstDay,
      periodEnd: period.lastDay,
    })
  }

  return intents
}

export function payoutTotal(payouts: readonly { amountCents: Cents }[]): Cents {
  return payouts.reduce((sum, payout) => addCents(sum, payout.amountCents), ZERO_CENTS)
}
