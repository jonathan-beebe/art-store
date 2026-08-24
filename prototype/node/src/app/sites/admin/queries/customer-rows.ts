import type { ActionContext } from '../../../actions/action-context.ts'
import {
  isAnonymousCustomer,
  isVerifiedCustomer,
} from '../../../core/customers/customer-verification.ts'
import type { CustomerId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export const CUSTOMER_STANDING_FILTERS = ['all', 'verified', 'anonymous', 'blocked'] as const
export type CustomerStandingFilter = (typeof CUSTOMER_STANDING_FILTERS)[number]

/** A customer as the customers table shows it, one row per customer. */
export type CustomerRow = {
  id: CustomerId
  email: string | null
  createdAt: Timestamp
  orderCount: number
  favoriteCount: number
  cartLineCount: number
  isBlocked: boolean
}

/** Every customer with their counts and standing, narrowed to one filter. */
export async function customerRows(
  context: Pick<ActionContext, 'db'>,
  standing: CustomerStandingFilter = 'all',
): Promise<readonly CustomerRow[]> {
  const { db } = context
  const customers = await db
    .selectFrom('customers')
    .select(['id', 'email', 'createdAt'])
    .orderBy('createdAt')
    .orderBy('id')
    .execute()
  const orderCounts = await countByCustomer(db, 'orders')
  const favoriteCounts = await countByCustomer(db, 'favorites')
  const cartLineCounts = await cartLineCountsByCustomer(db)
  const blockedIds = await blockedCustomerIds(db)

  const rows = customers.map((customer) => ({
    ...customer,
    orderCount: orderCounts.get(customer.id) ?? 0,
    favoriteCount: favoriteCounts.get(customer.id) ?? 0,
    cartLineCount: cartLineCounts.get(customer.id) ?? 0,
    isBlocked: blockedIds.has(customer.id),
  }))

  return rows.filter((row) => matchesStanding(row, standing))
}

function matchesStanding(
  row: { email: string | null; isBlocked: boolean },
  standing: CustomerStandingFilter,
): boolean {
  if (standing === 'blocked') return row.isBlocked
  if (standing === 'verified') return isVerifiedCustomer(row)
  if (standing === 'anonymous') return isAnonymousCustomer(row)

  return true
}

async function countByCustomer(
  db: AppDatabase,
  table: 'orders' | 'favorites',
): Promise<Map<CustomerId, number>> {
  const rows = await db
    .selectFrom(table)
    .select(['customerId', (eb) => eb.fn.countAll().as('count')])
    .groupBy('customerId')
    .execute()

  return new Map(rows.map((row) => [row.customerId, Number(row.count)]))
}

async function cartLineCountsByCustomer(db: AppDatabase): Promise<Map<CustomerId, number>> {
  const rows = await db
    .selectFrom('cartItems')
    .innerJoin('carts', 'carts.id', 'cartItems.cartId')
    .select(['carts.customerId as customerId', (eb) => eb.fn.countAll().as('count')])
    .groupBy('carts.customerId')
    .execute()

  return new Map(rows.map((row) => [row.customerId, Number(row.count)]))
}

/** A customer carries at most one unlifted block at a time, per `blockCustomer`. */
async function blockedCustomerIds(db: AppDatabase): Promise<Set<CustomerId>> {
  const rows = await db
    .selectFrom('customerBlocks')
    .select('customerId')
    .where('liftedAt', 'is', null)
    .execute()

  return new Set(rows.map((row) => row.customerId))
}
