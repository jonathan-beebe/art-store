import type { Selectable } from 'kysely'
import type { ActionContext } from '../../../actions/action-context.ts'
import { activeCustomerBlock, type ActiveCustomerBlock } from '../../../actions/moderation/active-customer-block.ts'
import type {
  CustomerId,
  CustomerMergeId,
  ListingId,
  OrderId,
} from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { CustomerTable } from '../../../db/schema.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export type CustomerOrderRow = {
  id: OrderId
  status: string
  totalCents: number
  placedAt: Timestamp
}

export type CustomerFavoriteRow = {
  listingId: ListingId
  title: string
  createdAt: Timestamp
}

export type CustomerCartLineRow = {
  listingId: ListingId
  title: string
  quantity: number
}

export type MergeDirection = 'into' | 'out_of'

/** One `customer_merges` row from this customer's side: who the other party is. */
export type CustomerMergeRow = {
  id: CustomerMergeId
  direction: MergeDirection
  otherCustomerId: CustomerId
  createdAt: Timestamp
}

export type CustomerDetail = {
  customer: Selectable<CustomerTable>
  orders: readonly CustomerOrderRow[]
  favorites: readonly CustomerFavoriteRow[]
  cartLines: readonly CustomerCartLineRow[]
  merges: readonly CustomerMergeRow[]
  block: ActiveCustomerBlock | null
}

/** The customer page's whole read: null when the id names nobody. */
export async function customerDetail(
  context: Pick<ActionContext, 'db'>,
  customerId: CustomerId,
): Promise<CustomerDetail | null> {
  const { db } = context
  const customer = await db.selectFrom('customers').selectAll().where('id', '=', customerId).executeTakeFirst()
  if (customer === undefined) return null

  const orders = await ordersForCustomer(db, customerId)
  const favorites = await favoritesForCustomer(db, customerId)
  const cartLines = await cartLinesForCustomer(db, customerId)
  const merges = await mergesForCustomer(db, customerId)
  const block = await activeCustomerBlock(context, customerId)

  return { customer, orders, favorites, cartLines, merges, block }
}

async function ordersForCustomer(db: AppDatabase, customerId: CustomerId): Promise<readonly CustomerOrderRow[]> {
  return db
    .selectFrom('orders')
    .select(['id', 'status', 'totalCents', 'placedAt'])
    .where('customerId', '=', customerId)
    .orderBy('placedAt', 'desc')
    .orderBy('id', 'desc')
    .execute()
}

async function favoritesForCustomer(
  db: AppDatabase,
  customerId: CustomerId,
): Promise<readonly CustomerFavoriteRow[]> {
  return db
    .selectFrom('favorites')
    .innerJoin('listings', 'listings.id', 'favorites.listingId')
    .select(['favorites.listingId as listingId', 'listings.title as title', 'favorites.createdAt as createdAt'])
    .where('favorites.customerId', '=', customerId)
    .orderBy('favorites.createdAt', 'desc')
    .orderBy('favorites.id', 'desc')
    .execute()
}

async function cartLinesForCustomer(
  db: AppDatabase,
  customerId: CustomerId,
): Promise<readonly CustomerCartLineRow[]> {
  return db
    .selectFrom('cartItems')
    .innerJoin('carts', 'carts.id', 'cartItems.cartId')
    .innerJoin('listings', 'listings.id', 'cartItems.listingId')
    .select(['cartItems.listingId as listingId', 'listings.title as title', 'cartItems.quantity as quantity'])
    .where('carts.customerId', '=', customerId)
    .orderBy('cartItems.createdAt', 'desc')
    .orderBy('cartItems.id', 'desc')
    .execute()
}

/** Merges that named this customer on either side: the anonymous row folded
 * into it, or this row folded into a verified customer elsewhere. */
async function mergesForCustomer(db: AppDatabase, customerId: CustomerId): Promise<readonly CustomerMergeRow[]> {
  const into = await db
    .selectFrom('customerMerges')
    .selectAll()
    .where('customerId', '=', customerId)
    .execute()
  const outOf = await db
    .selectFrom('customerMerges')
    .selectAll()
    .where('anonymousCustomerId', '=', customerId)
    .execute()

  return [
    ...into.map((row) => ({
      id: row.id,
      direction: 'into' as const,
      otherCustomerId: row.anonymousCustomerId,
      createdAt: row.createdAt,
    })),
    ...outOf.map((row) => ({
      id: row.id,
      direction: 'out_of' as const,
      otherCustomerId: row.customerId,
      createdAt: row.createdAt,
    })),
  ]
}
