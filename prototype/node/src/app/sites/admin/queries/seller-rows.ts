import type { ActionContext } from '../../../actions/action-context.ts'
import { ledgerMovements } from '../../../actions/escrow/ledger-movements.ts'
import { ledgerBalancesBySeller, type LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

/** A seller as the sellers table shows it, one row per seller. */
export type SellerRow = LedgerBalance & {
  id: number
  email: string
  shopName: string | null
  createdAt: Timestamp
  listingCount: number
  fulfillmentCount: number
  removedListingCount: number
}

const EMPTY_BALANCE: LedgerBalance = { heldCents: 0, availableCents: 0, paidOutCents: 0 }

/**
 * Every seller with the counts and the balance the table shows. The balance
 * folds one ledger read rather than querying `sellerBalance` per seller.
 */
export async function sellerRows(context: Pick<ActionContext, 'db'>): Promise<readonly SellerRow[]> {
  const { db } = context
  const sellers = await db
    .selectFrom('sellers')
    .select(['id', 'email', 'shopName', 'createdAt'])
    .orderBy('id')
    .execute()
  const listingCounts = await countBySeller(db, 'listings')
  const fulfillmentCounts = await countBySeller(db, 'fulfillments')
  const removedCounts = await removedListingCountsBySeller(db)
  const movements = await ledgerMovements(context)
  const balances = ledgerBalancesBySeller(movements)

  return sellers.map((seller) => ({
    ...seller,
    listingCount: listingCounts.get(seller.id) ?? 0,
    fulfillmentCount: fulfillmentCounts.get(seller.id) ?? 0,
    removedListingCount: removedCounts.get(seller.id) ?? 0,
    ...(balances.get(seller.id) ?? EMPTY_BALANCE),
  }))
}

async function countBySeller(
  db: AppDatabase,
  table: 'listings' | 'fulfillments',
): Promise<Map<number, number>> {
  const rows = await db
    .selectFrom(table)
    .select(['sellerId', (eb) => eb.fn.countAll().as('count')])
    .groupBy('sellerId')
    .execute()

  return new Map(rows.map((row) => [row.sellerId, Number(row.count)]))
}

/** A listing carries at most one unlifted removal, so counting rows counts listings. */
async function removedListingCountsBySeller(db: AppDatabase): Promise<Map<number, number>> {
  const rows = await db
    .selectFrom('listingRemovals')
    .innerJoin('listings', 'listings.id', 'listingRemovals.listingId')
    .select(['listings.sellerId as sellerId', (eb) => eb.fn.countAll().as('count')])
    .where('listingRemovals.liftedAt', 'is', null)
    .groupBy('listings.sellerId')
    .execute()

  return new Map(rows.map((row) => [row.sellerId, Number(row.count)]))
}
