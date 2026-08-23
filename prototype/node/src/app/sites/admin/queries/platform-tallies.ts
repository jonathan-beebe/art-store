import type { ActionContext } from '../../../actions/action-context.ts'
import { tallyOver, type Tally } from '../../../core/analytics/tally.ts'
import { isVerifiedCustomer } from '../../../core/customers/customer-verification.ts'
import { LISTING_STATUSES, type ListingStatus } from '../../../core/listings/listing-status.ts'
import {
  FULFILLMENT_STATUSES,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'
import { ORDER_STATUSES, type OrderStatus } from '../../../core/orders/order-status.ts'
import { toCount } from '../../../db/count.ts'

/** How many customers have given an address, and how many are still a cookie. */
export type CustomerCounts = { verified: number; anonymous: number }

export type PlatformTallies = {
  sellerCount: number
  customers: CustomerCounts
  listings: readonly Tally<ListingStatus>[]
  orders: readonly Tally<OrderStatus>[]
  fulfillments: readonly Tally<FulfillmentStatus>[]
}

/** Who and what is on the platform, counted by the states the domain names. */
export async function platformTallies({
  db,
}: Pick<ActionContext, 'db'>): Promise<PlatformTallies> {
  const sellers = await db
    .selectFrom('sellers')
    .select((eb) => eb.fn.countAll<string | number | bigint>().as('count'))
    .executeTakeFirstOrThrow()

  const customers = await db.selectFrom('customers').select(['email']).execute()
  const verified = customers.filter(isVerifiedCustomer).length

  const listings = await db
    .selectFrom('listings')
    .select(['status'])
    .select((eb) => eb.fn.countAll<string | number | bigint>().as('count'))
    .groupBy('status')
    .execute()

  const orders = await db
    .selectFrom('orders')
    .select(['status'])
    .select((eb) => eb.fn.countAll<string | number | bigint>().as('count'))
    .groupBy('status')
    .execute()

  const fulfillments = await db
    .selectFrom('fulfillments')
    .select(['status'])
    .select((eb) => eb.fn.countAll<string | number | bigint>().as('count'))
    .groupBy('status')
    .execute()

  return {
    sellerCount: toCount(sellers.count),
    customers: { verified, anonymous: customers.length - verified },
    listings: tallyOver(LISTING_STATUSES, tallies(listings)),
    orders: tallyOver(ORDER_STATUSES, tallies(orders)),
    fulfillments: tallyOver(FULFILLMENT_STATUSES, tallies(fulfillments)),
  }
}

/** A `group by status` result as tallies, keyed by whatever status union the
 * rows carry — the states nobody has reached are added back by `tallyOver`. */
function tallies<Status extends string>(
  rows: readonly { status: Status; count: string | number | bigint }[],
): readonly Tally<Status>[] {
  return rows.map((row) => ({ key: row.status, count: toCount(row.count) }))
}
