import type { ActionContext } from '../../../actions/action-context.ts'
import { ledgerMovements } from '../../../actions/escrow/ledger-movements.ts'
import { ledgerBalance, type LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import { feeTotals, type FeeSubject } from '../../../core/escrow/fee-totals.ts'
import { addCents, ZERO_CENTS, type Cents } from '../../../core/money.ts'

/** Escrow across every seller, plus what the platform kept and what it gave back. */
export type PlatformMoney = LedgerBalance & {
  feesEarnedCents: Cents
  feesRefundedCents: Cents
  refundedCents: Cents
}

/**
 * The platform's side of the ledger. The balance folds every seller's
 * movements at once — `ledgerBalance` adds signed amounts, so one seller's
 * ledger and all of them together fold the same way.
 */
export async function platformMoney(
  context: Pick<ActionContext, 'db'>,
): Promise<PlatformMoney> {
  const movements = await ledgerMovements(context)
  const fees = feeTotals(await settledFulfillments(context))

  return {
    ...ledgerBalance(movements),
    feesEarnedCents: fees.earnedCents,
    feesRefundedCents: fees.refundedCents,
    refundedCents: await refundedTotal(context),
  }
}

/**
 * The fee is priced at placement and earned when the order pays, which is the
 * moment the seller's net is held — so a held entry is what says the fee is
 * real, and an unpaid order's fee is not counted. A declined or refunded
 * fulfillment still reads here; how it ended is what moves its fee from
 * earned to forgone.
 */
async function settledFulfillments({ db }: Pick<ActionContext, 'db'>): Promise<readonly FeeSubject[]> {
  return db
    .selectFrom('fulfillments')
    .innerJoin('ledgerEntries', 'ledgerEntries.fulfillmentId', 'fulfillments.id')
    .where('ledgerEntries.entryType', '=', 'held')
    .select(['fulfillments.feeCents as feeCents', 'fulfillments.status as status'])
    .execute()
}

/** Every cent handed back to a customer, whoever issued it. */
async function refundedTotal({ db }: Pick<ActionContext, 'db'>): Promise<Cents> {
  const refunds = await db.selectFrom('refunds').select('amountCents').execute()

  return refunds.reduce<Cents>((total, refund) => addCents(total, refund.amountCents), ZERO_CENTS)
}
