import type { Expression, ExpressionBuilder, SqlBool } from 'kysely'
import type { ActionContext } from '../../../actions/action-context.ts'
import type { CustomerId } from '../../../core/ids/entity-ids.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'
import { toCount } from '../../../db/count.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { Database } from '../../../db/schema.ts'
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

type CustomersFilter = ExpressionBuilder<Database, 'customers'>

function isBlocked(eb: CustomersFilter): Expression<SqlBool> {
  return eb.exists(
    eb
      .selectFrom('customerBlocks')
      .select('customerBlocks.id')
      .whereRef('customerBlocks.customerId', '=', 'customers.id')
      .where('customerBlocks.liftedAt', 'is', null),
  )
}

function matchesStanding(eb: CustomersFilter, standing: CustomerStandingFilter): Expression<SqlBool>[] {
  if (standing === 'verified') return [eb('customers.email', 'is not', null)]
  if (standing === 'anonymous') return [eb('customers.email', 'is', null)]
  if (standing === 'blocked') return [isBlocked(eb)]

  return []
}

/** How many customers hold a given standing, independent of which page is shown. */
export async function countCustomerRows(
  context: Pick<ActionContext, 'db'>,
  standing: CustomerStandingFilter = 'all',
): Promise<number> {
  const counted = await context.db
    .selectFrom('customers')
    .select(({ fn }) => fn.countAll<string | number | bigint>().as('total'))
    .where((eb) => eb.and(matchesStanding(eb, standing)))
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}

/** One page of customers with their counts and standing, narrowed to one filter. */
export async function customerRows(
  context: Pick<ActionContext, 'db'>,
  standing: CustomerStandingFilter = 'all',
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<readonly CustomerRow[]> {
  const { db } = context
  const customers = await db
    .selectFrom('customers')
    .select(['id', 'email', 'createdAt'])
    .where((eb) => eb.and(matchesStanding(eb, standing)))
    .orderBy('createdAt')
    .orderBy('id')
    .offset(page.offset)
    .limit(page.limit)
    .execute()

  const customerIds = customers.map((customer) => customer.id)
  const orderCounts = await countByCustomer(db, 'orders', customerIds)
  const favoriteCounts = await countByCustomer(db, 'favorites', customerIds)
  const cartLineCounts = await cartLineCountsByCustomer(db, customerIds)
  const blockedIds = await blockedCustomerIds(db, customerIds)

  return customers.map((customer) => ({
    ...customer,
    orderCount: orderCounts.get(customer.id) ?? 0,
    favoriteCount: favoriteCounts.get(customer.id) ?? 0,
    cartLineCount: cartLineCounts.get(customer.id) ?? 0,
    isBlocked: blockedIds.has(customer.id),
  }))
}

async function countByCustomer(
  db: AppDatabase,
  table: 'orders' | 'favorites',
  customerIds: readonly CustomerId[],
): Promise<Map<CustomerId, number>> {
  if (customerIds.length === 0) return new Map()

  const rows = await db
    .selectFrom(table)
    .select(['customerId', (eb) => eb.fn.countAll().as('count')])
    .where('customerId', 'in', customerIds)
    .groupBy('customerId')
    .execute()

  return new Map(rows.map((row) => [row.customerId, Number(row.count)]))
}

async function cartLineCountsByCustomer(
  db: AppDatabase,
  customerIds: readonly CustomerId[],
): Promise<Map<CustomerId, number>> {
  if (customerIds.length === 0) return new Map()

  const rows = await db
    .selectFrom('cartItems')
    .innerJoin('carts', 'carts.id', 'cartItems.cartId')
    .select(['carts.customerId as customerId', (eb) => eb.fn.countAll().as('count')])
    .where('carts.customerId', 'in', customerIds)
    .groupBy('carts.customerId')
    .execute()

  return new Map(rows.map((row) => [row.customerId, Number(row.count)]))
}

/** A customer carries at most one unlifted block at a time, per `blockCustomer`. */
async function blockedCustomerIds(
  db: AppDatabase,
  customerIds: readonly CustomerId[],
): Promise<Set<CustomerId>> {
  if (customerIds.length === 0) return new Set()

  const rows = await db
    .selectFrom('customerBlocks')
    .select('customerId')
    .where('liftedAt', 'is', null)
    .where('customerId', 'in', customerIds)
    .execute()

  return new Set(rows.map((row) => row.customerId))
}
