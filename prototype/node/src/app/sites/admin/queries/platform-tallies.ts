import type { ActionContext } from '../../../actions/action-context.ts'
import { tallyOver, type Tally } from '../../../core/analytics/tally.ts'
import { isVerifiedCustomer } from '../../../core/customers/customer-verification.ts'
import { LISTING_STATUSES, type ListingStatus } from '../../../core/listings/listing-status.ts'
import {
  FULFILLMENT_STATUSES,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'
import { ORDER_STATUSES, type OrderStatus } from '../../../core/orders/order-status.ts'

/** How many customers have given an address, and how many are still a cookie. */
export type CustomerCounts = { verified: number; anonymous: number }

export type PlatformTallies = {
  sellerCount: number
  customers: CustomerCounts
  listings: readonly Tally<ListingStatus>[]
  orders: readonly Tally<OrderStatus>[]
  fulfillments: readonly Tally<FulfillmentStatus>[]
}

/** A `group by` row, before the states nobody has reached are added back. */
type CountedStatus = { status: string; count: number }

/** Who and what is on the platform, counted by the states the domain names. */
export async function platformTallies({
  db,
}: Pick<ActionContext, 'db'>): Promise<PlatformTallies> {
  const sellers = await db
    .selectFrom('sellers')
    .select((eb) => eb.fn.countAll<number>().as('count'))
    .executeTakeFirstOrThrow()

  const customers = await db.selectFrom('customers').select(['email']).execute()
  const verified = customers.filter(isVerifiedCustomer).length

  const listings = await db
    .selectFrom('listings')
    .select(['status'])
    .select((eb) => eb.fn.countAll<number>().as('count'))
    .groupBy('status')
    .execute()

  const orders = await db
    .selectFrom('orders')
    .select(['status'])
    .select((eb) => eb.fn.countAll<number>().as('count'))
    .groupBy('status')
    .execute()

  const fulfillments = await db
    .selectFrom('fulfillments')
    .select(['status'])
    .select((eb) => eb.fn.countAll<number>().as('count'))
    .groupBy('status')
    .execute()

  return {
    sellerCount: Number(sellers.count),
    customers: { verified, anonymous: customers.length - verified },
    listings: tallyOver(LISTING_STATUSES, asTallies(listings)),
    orders: tallyOver(ORDER_STATUSES, asTallies(orders)),
    fulfillments: tallyOver(FULFILLMENT_STATUSES, asTallies(fulfillments)),
  }
}

function asTallies<Status extends string>(
  counted: readonly CountedStatus[],
): readonly Tally<Status>[] {
  return counted.map((row) => ({ key: row.status as Status, count: Number(row.count) }))
}
