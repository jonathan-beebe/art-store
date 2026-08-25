import { sql } from 'kysely'
import type { ActionContext } from '../../../actions/action-context.ts'
import { platformBalance } from '../../../actions/escrow/ledger-balances.ts'
import type { LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import { REVERSED_FULFILLMENT_STATUSES } from '../../../core/orders/fulfillment-status.ts'
import { centsFromColumn, ZERO_CENTS, type Cents } from '../../../core/money.ts'

/** Escrow across every seller, plus what the platform kept and what it gave back. */
export type PlatformMoney = LedgerBalance & {
  feesEarnedCents: Cents
  feesRefundedCents: Cents
  refundedCents: Cents
}

/** The platform's side of the ledger, folded in SQL rather than in JS. */
export async function platformMoney(
  context: Pick<ActionContext, 'db'>,
): Promise<PlatformMoney> {
  const fees = await feeTotals(context)

  return {
    ...(await platformBalance(context)),
    feesEarnedCents: fees.earnedCents,
    feesRefundedCents: fees.refundedCents,
    refundedCents: await refundedTotal(context),
  }
}

type RawSum = number | string | bigint
type FeeTotalsRow = { earnedCents: RawSum; refundedCents: RawSum }

const REVERSED_STATUSES_SQL = sql.join(REVERSED_FULFILLMENT_STATUSES.map((status) => sql.lit(status)))

/**
 * The fee is priced at placement and earned when the order pays, which is the
 * moment the seller's net is held — so a held entry is what says the fee is
 * real, and an unpaid order's fee is not counted. `isReversed`'s two endings
 * are what move a fulfillment's fee from earned to forgone.
 */
async function feeTotals({ db }: Pick<ActionContext, 'db'>): Promise<{ earnedCents: Cents; refundedCents: Cents }> {
  const { rows } = await sql<FeeTotalsRow>`
    select
      coalesce(sum(case when fulfillments.status in (${REVERSED_STATUSES_SQL}) then 0 else fulfillments.fee_cents end), 0) as earned_cents,
      coalesce(sum(case when fulfillments.status in (${REVERSED_STATUSES_SQL}) then fulfillments.fee_cents else 0 end), 0) as refunded_cents
    from fulfillments
    inner join ledger_entries on ledger_entries.fulfillment_id = fulfillments.id
    where ledger_entries.entry_type = 'held'
  `.execute(db)

  const row = rows[0]

  return row === undefined
    ? { earnedCents: ZERO_CENTS, refundedCents: ZERO_CENTS }
    : { earnedCents: centsFromColumn(row.earnedCents), refundedCents: centsFromColumn(row.refundedCents) }
}

/** Every cent handed back to a customer, whoever issued it. */
async function refundedTotal({ db }: Pick<ActionContext, 'db'>): Promise<Cents> {
  const { rows } = await sql<{ total: RawSum }>`
    select coalesce(sum(amount_cents), 0) as total from refunds
  `.execute(db)

  const row = rows[0]

  return row === undefined ? ZERO_CENTS : centsFromColumn(row.total)
}
