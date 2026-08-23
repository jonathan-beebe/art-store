import type { ActionContext } from '../../../actions/action-context.ts'
import { ledgerMovements, type SellerLedgerMovement } from '../../../actions/escrow/ledger-movements.ts'
import { ledgerBalance, type LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import { addCents, type Cents } from '../../../core/money.ts'

/** A name a table cell can show even for a seller who never set a shop name. */
export type SellerOption = { id: number; name: string }

/** A seller's escrow balance reconciled against what payouts actually sent, plus lifetime sales. */
export type SellerAccount = LedgerBalance & {
  sellerId: number
  sellerName: string
  payoutTotalCents: Cents
  reconciles: boolean
  lifetimeSubtotalCents: Cents
  lifetimeFeeCents: Cents
  lifetimeNetCents: Cents
}

type LifetimeSales = { subtotalCents: Cents; feeCents: Cents; netCents: Cents }

const ZERO_BALANCE: LedgerBalance = { heldCents: 0, availableCents: 0, paidOutCents: 0 }
const ZERO_LIFETIME: LifetimeSales = { subtotalCents: 0, feeCents: 0, netCents: 0 }

/**
 * Every seller, whether or not they have moved any money, with their escrow
 * balance folded from one read of the whole ledger and reconciled against the
 * `payouts` rows that are supposed to equal its paid-out figure.
 */
export async function sellerAccounts(
  context: Pick<ActionContext, 'db'>,
): Promise<readonly SellerAccount[]> {
  const sellers = await sellerOptions(context)
  const balances = balancesBySeller(await ledgerMovements(context))
  const payoutTotals = await payoutTotalsBySeller(context)
  const lifetimeSales = await lifetimeSalesBySeller(context)

  return sellers.map((seller) => toAccount(seller, balances, payoutTotals, lifetimeSales))
}

export async function sellerOptions({ db }: Pick<ActionContext, 'db'>): Promise<readonly SellerOption[]> {
  const sellers = await db
    .selectFrom('sellers')
    .select(['id', 'shopName', 'email'])
    .orderBy('id')
    .execute()

  return sellers.map((seller) => ({ id: seller.id, name: seller.shopName ?? seller.email }))
}

function toAccount(
  seller: SellerOption,
  balances: Map<number, LedgerBalance>,
  payoutTotals: Map<number, Cents>,
  lifetimeSales: Map<number, LifetimeSales>,
): SellerAccount {
  const balance = balances.get(seller.id) ?? ZERO_BALANCE
  const payoutTotalCents = payoutTotals.get(seller.id) ?? 0
  const lifetime = lifetimeSales.get(seller.id) ?? ZERO_LIFETIME

  return {
    sellerId: seller.id,
    sellerName: seller.name,
    ...balance,
    payoutTotalCents,
    reconciles: payoutTotalCents === balance.paidOutCents,
    lifetimeSubtotalCents: lifetime.subtotalCents,
    lifetimeFeeCents: lifetime.feeCents,
    lifetimeNetCents: lifetime.netCents,
  }
}

function balancesBySeller(movements: readonly SellerLedgerMovement[]): Map<number, LedgerBalance> {
  const bySeller = new Map<number, SellerLedgerMovement[]>()
  for (const movement of movements) {
    bySeller.set(movement.sellerId, [...(bySeller.get(movement.sellerId) ?? []), movement])
  }

  return new Map([...bySeller].map(([sellerId, own]) => [sellerId, ledgerBalance(own)]))
}

async function payoutTotalsBySeller({ db }: Pick<ActionContext, 'db'>): Promise<Map<number, Cents>> {
  const rows = await db
    .selectFrom('payouts')
    .select(['sellerId', (eb) => eb.fn.sum<number>('amountCents').as('total')])
    .groupBy('sellerId')
    .execute()

  return new Map(rows.map((row) => [row.sellerId, Number(row.total)]))
}

/**
 * A fee is earned when the order pays, which is when the net is held, so
 * lifetime sales fold every fulfillment that has a `held` ledger entry.
 */
async function lifetimeSalesBySeller({ db }: Pick<ActionContext, 'db'>): Promise<Map<number, LifetimeSales>> {
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

  const bySeller = new Map<number, LifetimeSales>()
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
