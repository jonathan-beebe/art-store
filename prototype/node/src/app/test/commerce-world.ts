import type { ActionContext } from '../actions/action-context.ts'
import { addToCart } from '../actions/carts/add-to-cart.ts'
import { currentCart } from '../actions/carts/current-cart.ts'
import { finalizeOrder } from '../actions/orders/finalize-order.ts'
import { placeOrderOrThrow } from '../actions/orders/place-order.ts'
import type { Clock } from '../clock.ts'
import type { ListingStatus } from '../core/listings/listing-status.ts'
import type { ShippingAddress } from '../core/orders/shipping-address.ts'
import type { Listing, Order } from '../db/commerce-schema.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'
import { toTimestamp } from '../db/timestamp.ts'

export const APPROVED_CARD = '4242 4242 4242 4242'
export const DECLINED_CARD = '4000 0000 0000 0002'
export const UNFUNDED_CARD = '4000 0000 0000 9995'

/** Frozen so payout periods read the same whatever day the suite runs. */
export const PLACED_AT = new Date('2026-08-20T09:00:00.000Z')

export const SHIPPING_ADDRESS: ShippingAddress = {
  name: 'Ada Lovelace',
  line1: '12 Analytical Way',
  line2: null,
  city: 'London',
  region: 'Greater London',
  postalCode: 'EC1A 1BB',
  country: 'GB',
}

/** A clock a test moves by hand, so one test can walk days of a lifecycle. */
export type TravellingClock = Clock & { travelTo(instant: Date): void }

export type CommerceWorld = {
  context: ActionContext
  db: AppDatabase
  travelTo(instant: Date): void
  close(): Promise<void>
}

/**
 * A migrated in-memory database, a clock the test drives, and the rows every
 * commerce test starts from. Identity rows are written straight through Kysely:
 * this ticket owns none of those tables.
 */
export async function openCommerceWorld(at: Date = PLACED_AT): Promise<CommerceWorld> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const clock = travellingClock(at)

  return {
    context: { db, clock },
    db,
    travelTo: clock.travelTo,
    close: () => db.destroy(),
  }
}

function travellingClock(instant: Date): TravellingClock {
  let current = instant

  return {
    now: () => new Date(current),
    travelTo: (next: Date) => {
      current = next
    },
  }
}

let uniqueSuffix = 0

function nextSuffix(): number {
  uniqueSuffix += 1
  return uniqueSuffix
}

export async function createSeller(
  { db, clock }: ActionContext,
  shopName = 'Blue Kiln Studio',
): Promise<number> {
  const seller = await db
    .insertInto('sellers')
    .values({
      email: `seller-${nextSuffix()}@example.test`,
      name: null,
      shopName,
      emailVerifiedAt: toTimestamp(clock.now()),
      createdAt: toTimestamp(clock.now()),
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  return seller.id
}

export async function createCustomer(
  { db, clock }: ActionContext,
  options: { isVerified?: boolean } = {},
): Promise<number> {
  const isVerified = options.isVerified ?? true
  const customer = await db
    .insertInto('customers')
    .values({
      email: isVerified ? `customer-${nextSuffix()}@example.test` : null,
      name: null,
      emailVerifiedAt: isVerified ? toTimestamp(clock.now()) : null,
      createdAt: toTimestamp(clock.now()),
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  return customer.id
}

export async function createAdmin({ db, clock }: ActionContext): Promise<number> {
  const admin = await db
    .insertInto('admins')
    .values({
      email: `admin-${nextSuffix()}@example.test`,
      name: 'Jonathan Beebe',
      createdAt: toTimestamp(clock.now()),
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  return admin.id
}

export type ListingOverrides = {
  title?: string
  priceCents?: number
  quantity?: number
  status?: ListingStatus
  medium?: string | null
}

export async function createListing(
  { db, clock }: ActionContext,
  sellerId: number,
  overrides: ListingOverrides = {},
): Promise<Listing> {
  const now = toTimestamp(clock.now())

  return db
    .insertInto('listings')
    .values({
      sellerId,
      title: overrides.title ?? 'Harbour at Dusk',
      slug: `listing-${nextSuffix()}`,
      description: 'Oil on canvas.',
      medium: overrides.medium ?? 'Oil',
      dimensions: '40 x 60 cm',
      priceCents: overrides.priceCents ?? 45_000,
      quantity: overrides.quantity ?? 1,
      status: overrides.status ?? 'for_sale',
      imagePath: null,
      createdAt: now,
      updatedAt: now,
    })
    .returningAll()
    .executeTakeFirstOrThrow()
}

/** A cart holding one of each listing, the way a visitor fills it. */
export async function cartHolding(
  context: ActionContext,
  customerId: number,
  listingIds: readonly number[],
): Promise<number> {
  const cart = await currentCart(context, customerId)

  for (const listingId of listingIds) {
    await addToCart(context, { cartId: cart.id, listingId, quantity: 1 })
  }

  return cart.id
}

export async function placedOrder(
  context: ActionContext,
  customerId: number,
  listingIds: readonly number[],
  options: { isVerified?: boolean } = {},
): Promise<Order> {
  const cartId = await cartHolding(context, customerId, listingIds)

  return placeOrderOrThrow(context, {
    cartId,
    purchaser: {
      id: customerId,
      email: 'ada@example.test',
      isEmailVerified: options.isVerified ?? true,
    },
    shipping: SHIPPING_ADDRESS,
  })
}

export async function paidOrder(
  context: ActionContext,
  customerId: number,
  listingIds: readonly number[],
): Promise<Order> {
  const order = await placedOrder(context, customerId, listingIds)

  return finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })
}
