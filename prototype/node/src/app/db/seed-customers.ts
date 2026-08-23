import type { ActionContext } from '../actions/action-context.ts'
import { addToCart } from '../actions/carts/add-to-cart.ts'
import { currentCart } from '../actions/carts/current-cart.ts'
import { claimCustomerIdentity } from '../actions/customers/claim-customer-identity.ts'
import { createAnonymousCustomer } from '../actions/customers/create-anonymous-customer.ts'
import { toggleFavorite } from '../actions/favorites/toggle-favorite.ts'
import { recordListingEvent } from '../actions/listings/record-listing-event.ts'
import { blockCustomer } from '../actions/moderation/block-customer.ts'
import { fixedClock } from '../clock.ts'
import type { AppDatabase } from './database.ts'

export const CASEY_EMAIL = 'casey@example.com'
const CASEY_NAME = 'Casey Whitfield'
const CASEY_VERIFIED_AT = new Date('2026-06-01T00:00:00.000Z')

/** The listings Casey looked at before favoriting or buying any of them. */
const CASEY_VIEWED_TITLES = [
  'Woodfired Vase, Tall',
  'Quarry at First Light',
  'Handwoven Mohair Throw',
  'Ash-Glazed Tea Bowl',
  'Kitchen Table, Late Morning',
  'Standing Figure in Reclaimed Oak',
] as const

const CASEY_FAVORITE_TITLES = ['Woodfired Vase, Tall', 'Quarry at First Light', 'Handwoven Mohair Throw'] as const

/** Left in Casey's cart, unlike the titles the order history checks out. */
const CASEY_CART_TITLES = ['Nine Herons', 'Salt-Glazed Serving Bowl'] as const

const BLOCKED_CUSTOMER_EMAIL = 'jordan@example.com'
const BLOCKED_REASON = 'Repeated chargebacks reported by two sellers.'
const BLOCKED_AT = new Date('2026-07-20T10:00:00.000Z')

/** A few storefront visitors who never gave an address, each browsing a
 * different corner of the catalog. */
const ANONYMOUS_BROWSING: readonly (readonly string[])[] = [
  ['Field Study No. 12', 'Marigold Study', 'Cast Bronze Seed Pod'],
  ['Balanced Stone Cairn', 'Neon After Rain'],
  ['Rag-Rug Runner, Ochre', 'Terminal, Platform 4', 'Portrait of a Welder'],
]

export type SeededCasey = { id: number; email: string }

export type SeededCustomers = {
  casey: SeededCasey
  blockedCustomerId: number
  anonymousCustomerIds: readonly number[]
  count: number
}

/** The verified customer, the blocked customer, and the anonymous browsers a
 * reviewer needs to see every corner of the storefront and admin site. */
export async function seedCustomers(
  db: AppDatabase,
  listingIdsByTitle: Record<string, number>,
  adminId: number,
): Promise<SeededCustomers> {
  const casey = await seedCasey(db, listingIdsByTitle)
  const blockedCustomerId = await seedBlockedCustomer(db, adminId)
  const anonymousCustomerIds = await seedAnonymousBrowsers(db, listingIdsByTitle)

  return {
    casey,
    blockedCustomerId,
    anonymousCustomerIds,
    count: 2 + anonymousCustomerIds.length,
  }
}

async function seedCasey(db: AppDatabase, listingIdsByTitle: Record<string, number>): Promise<SeededCasey> {
  const claimed = await claimCustomerIdentity(
    { db, clock: fixedClock(CASEY_VERIFIED_AT) },
    { email: CASEY_EMAIL, currentCustomerId: null },
  )
  await db.updateTable('customers').set({ name: CASEY_NAME }).where('id', '=', claimed.id).execute()

  await recordViews(db, claimed.id, listingIdsByTitle, CASEY_VIEWED_TITLES, new Date('2026-07-01T09:00:00.000Z'))
  await favoriteEach(db, claimed.id, listingIdsByTitle, CASEY_FAVORITE_TITLES, new Date('2026-07-01T09:10:00.000Z'))
  await fillCart(db, claimed.id, listingIdsByTitle, CASEY_CART_TITLES, new Date('2026-07-15T10:00:00.000Z'))

  return { id: claimed.id, email: CASEY_EMAIL }
}

/** Records one view per title, a minute apart so none collapse into the same
 * per-listing-per-hour window. */
async function recordViews(
  db: AppDatabase,
  customerId: number,
  listingIdsByTitle: Record<string, number>,
  titles: readonly string[],
  startingAt: Date,
): Promise<void> {
  let at = startingAt.getTime()
  for (const title of titles) {
    await recordListingEvent(
      { db, clock: fixedClock(new Date(at)) },
      { listingId: listingIdsByTitle[title] ?? 0, customerId, eventType: 'view' },
    )
    at += 60_000
  }
}

async function favoriteEach(
  db: AppDatabase,
  customerId: number,
  listingIdsByTitle: Record<string, number>,
  titles: readonly string[],
  startingAt: Date,
): Promise<void> {
  let at = startingAt.getTime()
  for (const title of titles) {
    await toggleFavorite(
      { db, clock: fixedClock(new Date(at)) },
      { customerId, listingId: listingIdsByTitle[title] ?? 0 },
    )
    at += 60_000
  }
}

async function fillCart(
  db: AppDatabase,
  customerId: number,
  listingIdsByTitle: Record<string, number>,
  titles: readonly string[],
  at: Date,
): Promise<void> {
  const context: ActionContext = { db, clock: fixedClock(at) }
  const cart = await currentCart(context, customerId)

  for (const title of titles) {
    await addToCart(context, { cartId: cart.id, listingId: listingIdsByTitle[title] ?? 0, quantity: 1 })
  }
}

async function seedBlockedCustomer(db: AppDatabase, adminId: number): Promise<number> {
  const context: ActionContext = { db, clock: fixedClock(BLOCKED_AT) }
  const blocked = await claimCustomerIdentity(context, {
    email: BLOCKED_CUSTOMER_EMAIL,
    currentCustomerId: null,
  })

  await blockCustomer(context, { customerId: blocked.id, adminId, reason: BLOCKED_REASON })

  return blocked.id
}

async function seedAnonymousBrowsers(
  db: AppDatabase,
  listingIdsByTitle: Record<string, number>,
): Promise<readonly number[]> {
  const ids: number[] = []
  let at = new Date('2026-07-12T15:00:00.000Z').getTime()

  for (const titles of ANONYMOUS_BROWSING) {
    const customer = await createAnonymousCustomer({ db, clock: fixedClock(new Date(at)) })
    ids.push(customer.id)
    await recordViews(db, customer.id, listingIdsByTitle, titles, new Date(at))
    at += 3_600_000
  }

  return ids
}
