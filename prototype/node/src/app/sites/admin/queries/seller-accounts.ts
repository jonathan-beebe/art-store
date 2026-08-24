import type { ActionContext } from '../../../actions/action-context.ts'
import { ledgerMovements } from '../../../actions/escrow/ledger-movements.ts'
import { ledgerBalancesBySeller, type LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import type { SellerId } from '../../../core/ids/entity-ids.ts'
import { addCents, centsFromColumn, ZERO_CENTS, type Cents } from '../../../core/money.ts'
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
 * balance folded from one read of the whole ledger and reconciled against the
 * `payouts` rows that are supposed to equal its paid-out figure.
 */
export async function sellerAccounts(
  context: Pick<ActionContext, 'db'>,
): Promise<readonly SellerAccount[]> {
  const sellers = await sellerOptions(context)
  const balances = ledgerBalancesBySeller(await ledgerMovements(context))
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

/** What each seller's sales handed back to customers, folded from the refunds themselves. */
async function refundTotalsBySeller({ db }: Pick<ActionContext, 'db'>): Promise<Map<SellerId, Cents>> {
  const rows = await db
    .selectFrom('refunds')
    .innerJoin('fulfillments', 'fulfillments.id', 'refunds.fulfillmentId')
    .select(['fulfillments.sellerId as sellerId', 'refunds.amountCents as amountCents'])
    .execute()

  const bySeller = new Map<SellerId, Cents>()
  for (const row of rows) {
    bySeller.set(row.sellerId, addCents(bySeller.get(row.sellerId) ?? ZERO_CENTS, row.amountCents))
  }

  return bySeller
}

/**
 * A fee is earned when the order pays, which is when the net is held, so
 * lifetime sales fold every fulfillment that has a `held` ledger entry.
 */
async function lifetimeSalesBySeller({ db }: Pick<ActionContext, 'db'>): Promise<Map<SellerId, LifetimeSales>> {
  const rows = await db
    .selectFrom('fulfillments')
    .innerJoin('ledgerEntries', 'ledgerEntries.fulfillmentId', 'fulfillments.id')
    .where('ledgerEntries.entryType', '=', 'held')
    .select([
      'fulfillments.sellerId',
      'fulfillments.subtotalCents',
      'fulfillments.feeCents',
      'fulfillments.netCents',
    ])
    .execute()

  const bySeller = new Map<SellerId, LifetimeSales>()
  for (const row of rows) {
    const current = bySeller.get(row.sellerId) ?? ZERO_LIFETIME
    bySeller.set(row.sellerId, {
      subtotalCents: addCents(current.subtotalCents, row.subtotalCents),
      feeCents: addCents(current.feeCents, row.feeCents),
      netCents: addCents(current.netCents, row.netCents),
    })
  }

  return bySeller
}
