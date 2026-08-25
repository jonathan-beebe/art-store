import { sql } from 'kysely'
import type { ActionContext } from '../../../actions/action-context.ts'
import { sellerBalances } from '../../../actions/escrow/ledger-balances.ts'
import type { LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import type { SellerId } from '../../../core/ids/entity-ids.ts'
import { centsFromColumn, ZERO_CENTS, type Cents } from '../../../core/money.ts'
import { shopName } from '../../../core/shop/shop-name.ts'

/** A name a table cell can show even for a seller who never set a shop name. */
export type SellerOption = { id: SellerId; name: string }

/** A seller's escrow balance reconciled against what payouts actually sent, plus lifetime sales. */
export type SellerAccount = LedgerBalance & {
  sellerId: SellerId
  sellerName: string
  payoutTotalCents: Cents
  /** Everything handed back to customers out of this seller's sales. */
  refundedCents: Cents
  reconciles: boolean
  lifetimeSubtotalCents: Cents
  lifetimeFeeCents: Cents
  lifetimeNetCents: Cents
}

type LifetimeSales = { subtotalCents: Cents; feeCents: Cents; netCents: Cents }

const ZERO_BALANCE: LedgerBalance = {
  heldCents: ZERO_CENTS,
  availableCents: ZERO_CENTS,
  paidOutCents: ZERO_CENTS,
}
const ZERO_LIFETIME: LifetimeSales = {
  subtotalCents: ZERO_CENTS,
  feeCents: ZERO_CENTS,
  netCents: ZERO_CENTS,
}

/**
 * Every seller, whether or not they have moved any money, with their escrow
 * balance and every other total read as a SQL aggregate rather than folded
 * from a row-by-row read of the ledger, reconciled against the `payouts`
 * rows that are supposed to equal its paid-out figure.
 */
export async function sellerAccounts(
  context: Pick<ActionContext, 'db'>,
): Promise<readonly SellerAccount[]> {
  const sellers = await sellerOptions(context)
  const balances = await sellerBalances(context)
  const payoutTotals = await payoutTotalsBySeller(context)
  const lifetimeSales = await lifetimeSalesBySeller(context)
  const refunded = await refundTotalsBySeller(context)

  return sellers.map((seller) => toAccount(seller, { balances, payoutTotals, lifetimeSales, refunded }))
}

export async function sellerOptions({ db }: Pick<ActionContext, 'db'>): Promise<readonly SellerOption[]> {
  const sellers = await db
    .selectFrom('sellers')
    .select(['id', 'shopName', 'email'])
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  return sellers.map((seller) => ({ id: seller.id, name: shopName(seller) }))
}

/** Everything the whole-platform reads folded once, indexed by seller. */
type SellerLedgers = {
  balances: ReadonlyMap<SellerId, LedgerBalance>
  payoutTotals: Map<SellerId, Cents>
  lifetimeSales: Map<SellerId, LifetimeSales>
  refunded: Map<SellerId, Cents>
}

function toAccount(seller: SellerOption, ledgers: SellerLedgers): SellerAccount {
  const balance = ledgers.balances.get(seller.id) ?? ZERO_BALANCE
  const payoutTotalCents = ledgers.payoutTotals.get(seller.id) ?? ZERO_CENTS
  const lifetime = ledgers.lifetimeSales.get(seller.id) ?? ZERO_LIFETIME

  return {
    sellerId: seller.id,
    sellerName: seller.name,
    ...balance,
    payoutTotalCents,
    refundedCents: ledgers.refunded.get(seller.id) ?? ZERO_CENTS,
    reconciles: payoutTotalCents === balance.paidOutCents,
    lifetimeSubtotalCents: lifetime.subtotalCents,
    lifetimeFeeCents: lifetime.feeCents,
    lifetimeNetCents: lifetime.netCents,
  }
}

async function payoutTotalsBySeller({ db }: Pick<ActionContext, 'db'>): Promise<Map<SellerId, Cents>> {
  const rows = await db
    .selectFrom('payouts')
    .select(['sellerId', (eb) => eb.fn.sum<string | number | bigint>('amountCents').as('total')])
    .groupBy('sellerId')
    .execute()

  return new Map(rows.map((row) => [row.sellerId, centsFromColumn(row.total)]))
}

type RawSum = number | string | bigint
type RefundTotalRow = { sellerId: SellerId; total: RawSum }

/** What each seller's sales handed back to customers, grouped in SQL from the refunds themselves. */
async function refundTotalsBySeller({ db }: Pick<ActionContext, 'db'>): Promise<Map<SellerId, Cents>> {
  const { rows } = await sql<RefundTotalRow>`
    select fulfillments.seller_id as seller_id, coalesce(sum(refunds.amount_cents), 0) as total
    from refunds
    inner join fulfillments on fulfillments.id = refunds.fulfillment_id
    group by fulfillments.seller_id
  `.execute(db)

  return new Map(rows.map((row) => [row.sellerId, centsFromColumn(row.total)]))
}

type LifetimeSalesRow = {
  sellerId: SellerId
  subtotalCents: RawSum
  feeCents: RawSum
  netCents: RawSum
}

/**
 * A fee is earned when the order pays, which is when the net is held, so
 * lifetime sales sums every fulfillment that has a `held` ledger entry.
 */
async function lifetimeSalesBySeller({ db }: Pick<ActionContext, 'db'>): Promise<Map<SellerId, LifetimeSales>> {
  const { rows } = await sql<LifetimeSalesRow>`
    select
      fulfillments.seller_id as seller_id,
      coalesce(sum(fulfillments.subtotal_cents), 0) as subtotal_cents,
      coalesce(sum(fulfillments.fee_cents), 0) as fee_cents,
      coalesce(sum(fulfillments.net_cents), 0) as net_cents
    from fulfillments
    inner join ledger_entries on ledger_entries.fulfillment_id = fulfillments.id
    where ledger_entries.entry_type = 'held'
    group by fulfillments.seller_id
  `.execute(db)

  return new Map(
    rows.map((row) => [
      row.sellerId,
      {
        subtotalCents: centsFromColumn(row.subtotalCents),
        feeCents: centsFromColumn(row.feeCents),
        netCents: centsFromColumn(row.netCents),
      },
    ]),
  )
}
