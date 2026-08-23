import type { Selectable } from 'kysely'
import type { ActionContext } from '../../../actions/action-context.ts'
import type { ActiveListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import { sellerBalance } from '../../../actions/escrow/seller-balance.ts'
import type { LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import type { Fulfillment, Listing, Payout } from '../../../db/commerce-schema.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { SellerTable } from '../../../db/schema.ts'
import { fromTimestamp } from '../../../db/timestamp.ts'

/** A seller's listing plus the active removal, if any, a listing row shows. */
export type SellerListingRow = Listing & { removal: ActiveListingRemoval | null }

export type SellerDetail = {
  seller: Selectable<SellerTable>
  listings: readonly SellerListingRow[]
  fulfillments: readonly Fulfillment[]
  balance: LedgerBalance
  payouts: readonly Payout[]
}

/** The seller page's whole read: null when the id names nobody. */
export async function sellerDetail(
  context: Pick<ActionContext, 'db'>,
  sellerId: number,
): Promise<SellerDetail | null> {
  const { db } = context
  const seller = await db.selectFrom('sellers').selectAll().where('id', '=', sellerId).executeTakeFirst()
  if (seller === undefined) return null

  const listings = await listingsWithRemoval(db, sellerId)
  const fulfillments = await db
    .selectFrom('fulfillments')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('id', 'desc')
    .execute()
  const balance = await sellerBalance(context, sellerId)
  const payouts = await db
    .selectFrom('payouts')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('periodStart', 'desc')
    .execute()

  return { seller, listings, fulfillments, balance, payouts }
}

async function listingsWithRemoval(db: AppDatabase, sellerId: number): Promise<readonly SellerListingRow[]> {
  const listings = await db
    .selectFrom('listings')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('id', 'desc')
    .execute()

  const removals = await activeRemovalsByListing(db, listings.map((listing) => listing.id))

  return listings.map((listing) => ({ ...listing, removal: removals.get(listing.id) ?? null }))
}

async function activeRemovalsByListing(
  db: AppDatabase,
  listingIds: readonly number[],
): Promise<Map<number, ActiveListingRemoval>> {
  if (listingIds.length === 0) return new Map()

  const rows = await db
    .selectFrom('listingRemovals')
    .select(['listingId', 'id', 'kind', 'reason', 'createdAt', 'liftedAt'])
    .where('listingId', 'in', listingIds)
    .where('liftedAt', 'is', null)
    .execute()

  return new Map(
    rows.map((row) => [
      row.listingId,
      { id: row.id, kind: row.kind, reason: row.reason, createdAt: fromTimestamp(row.createdAt), liftedAt: null },
    ]),
  )
}
