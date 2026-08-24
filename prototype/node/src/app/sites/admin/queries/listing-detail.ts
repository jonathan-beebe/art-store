import { activeListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import type { ActionContext } from '../../../actions/action-context.ts'
import type { ListingId, ListingRemovalId, SellerId } from '../../../core/ids/entity-ids.ts'
import { isOnStorefront } from '../../../core/listings/listing-availability.ts'
import { canLiftRemoval, type RemovalKind } from '../../../core/moderation/listing-removal.ts'
import { shopName } from '../../../core/shop/shop-name.ts'
import type { Listing } from '../../../db/commerce-schema.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export type ListingDetailRemoval = {
  id: ListingRemovalId
  kind: RemovalKind
  reason: string
  createdAt: Timestamp
  liftedAt: Timestamp | null
  canLift: boolean
}

export type ListingDetail = {
  listing: Listing
  seller: { id: SellerId; name: string }
  isOnStorefront: boolean
  activeRemoval: ListingDetailRemoval | null
  removals: ListingDetailRemoval[]
}

/**
 * A listing's full moderation record: the listing, its seller, and every
 * `listing_removals` row it has ever had, oldest first. `null` for an id that
 * names nobody, so the route answers 404 without a domain check of its own.
 */
export async function listingDetail(
  context: Pick<ActionContext, 'db'>,
  listingId: ListingId,
): Promise<ListingDetail | null> {
  const { db } = context
  const listing = await db
    .selectFrom('listings')
    .selectAll()
    .where('id', '=', listingId)
    .executeTakeFirst()
  if (listing === undefined) return null

  const seller = await db
    .selectFrom('sellers')
    .select(['id', 'shopName', 'email'])
    .where('id', '=', listing.sellerId)
    .executeTakeFirstOrThrow()

  const removals = await removalHistory(db, listingId)
  const active = await activeListingRemoval(context, listingId)

  return {
    listing,
    seller: { id: seller.id, name: shopName(seller) },
    isOnStorefront: isOnStorefront(listing.status, active !== null),
    activeRemoval: active === null ? null : (removals.find((removal) => removal.id === active.id) ?? null),
    removals,
  }
}

async function removalHistory(
  db: ActionContext['db'],
  listingId: ListingId,
): Promise<ListingDetailRemoval[]> {
  const rows = await db
    .selectFrom('listingRemovals')
    .select(['id', 'kind', 'reason', 'createdAt', 'liftedAt'])
    .where('listingId', '=', listingId)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  return rows.map((row) => ({ ...row, canLift: canLiftRemoval(row.kind) }))
}
