import type { ActionContext } from '../actions/action-context.ts'
import { addToCart } from '../actions/carts/add-to-cart.ts'
import { currentCart } from '../actions/carts/current-cart.ts'
import { claimCustomerIdentity } from '../actions/customers/claim-customer-identity.ts'
import { createAnonymousCustomer } from '../actions/customers/create-anonymous-customer.ts'
import { toggleFavorite } from '../actions/favorites/toggle-favorite.ts'
import { recordListingEvent } from '../actions/listings/record-listing-event.ts'
import { blockCustomer } from '../actions/moderation/block-customer.ts'
import { fixedClock } from '../clock.ts'
import type {
  AdminId,
  CustomerId,
  ListingId,
} from '../core/ids/entity-ids.ts'
import type { AppDatabase } from './database.ts'
import { requireListingId } from './seed-catalog.ts'

export const HERMIONE_EMAIL = 'hermione@example.com'
const HERMIONE_NAME = 'Hermione Granger'
const HERMIONE_VERIFIED_AT = new Date('2026-06-01T00:00:00.000Z')

/** The listings Hermione looked at before favoriting or buying any of them. */
const HERMIONE_VIEWED_TITLES = [
  'Divination Tower Vase, Tall',
  'The Orchard at First Light',
  'House Scarf Throw, Scarlet and Gold',
  'Burrow Kitchen Tea Bowl',
  'Gryffindor Common Room, Late Morning',
  'Garden Gnome in Reclaimed Oak',
] as const

const HERMIONE_FAVORITE_TITLES = [
  'Divination Tower Vase, Tall',
  'The Orchard at First Light',
  'House Scarf Throw, Scarlet and Gold',
] as const

/** Left in Hermione's cart, unlike the titles the order history checks out. */
const HERMIONE_CART_TITLES = ['Nine Owls', 'Great Hall Serving Bowl'] as const

const BLOCKED_CUSTOMER_EMAIL = 'mundungus@example.com'
const BLOCKED_REASON = 'Paid two sellers in leprechaun gold that vanished before the payout cleared.'
const BLOCKED_AT = new Date('2026-07-20T10:00:00.000Z')

/** A few storefront visitors who never gave an address, each browsing a
 * different corner of the catalog. */
const ANONYMOUS_BROWSING: readonly (readonly string[])[] = [
  ['Lavender Fields from the North Tower', 'Tea Leaf Study', 'Cast Bronze Seeing Orb'],
  ['Standing Stones, Black Lake', 'Diagon Alley After Rain'],
  ['Patchwork Shawl Runner, Ochre', 'Platform Nine and Three-Quarters', 'Portrait of a Gamekeeper'],
]

export type SeededHermione = { id: CustomerId; email: string }

export type SeededCustomers = {
  hermione: SeededHermione
  blockedCustomerId: CustomerId
  anonymousCustomerIds: readonly CustomerId[]
  count: number
}

/** The verified customer, the blocked customer, and the anonymous browsers a
 * reviewer needs to see every corner of the storefront and admin site. */
export async function seedCustomers(
  db: AppDatabase,
  listingIdsByTitle: Record<string, ListingId>,
  adminId: AdminId,
): Promise<SeededCustomers> {
  const hermione = await seedHermione(db, listingIdsByTitle)
  const blockedCustomerId = await seedBlockedCustomer(db, adminId)
  const anonymousCustomerIds = await seedAnonymousBrowsers(db, listingIdsByTitle)

  return {
    hermione,
    blockedCustomerId,
    anonymousCustomerIds,
    count: 2 + anonymousCustomerIds.length,
  }
}

async function seedHermione(
  db: AppDatabase,
  listingIdsByTitle: Record<string, ListingId>,
): Promise<SeededHermione> {
  const claimed = await claimCustomerIdentity(
    { db, clock: fixedClock(HERMIONE_VERIFIED_AT) },
    { email: HERMIONE_EMAIL, currentCustomerId: null },
  )
  await db.updateTable('customers').set({ name: HERMIONE_NAME }).where('id', '=', claimed.id).execute()

  await recordViews(db, claimed.id, listingIdsByTitle, HERMIONE_VIEWED_TITLES, new Date('2026-07-01T09:00:00.000Z'))
  await favoriteEach(db, claimed.id, listingIdsByTitle, HERMIONE_FAVORITE_TITLES, new Date('2026-07-01T09:10:00.000Z'))
  await fillCart(db, claimed.id, listingIdsByTitle, HERMIONE_CART_TITLES, new Date('2026-07-15T10:00:00.000Z'))

  return { id: claimed.id, email: HERMIONE_EMAIL }
}

/** Records one view per title, a minute apart so none collapse into the same
 * per-listing-per-hour window. */
async function recordViews(
  db: AppDatabase,
  customerId: CustomerId,
  listingIdsByTitle: Record<string, ListingId>,
  titles: readonly string[],
  startingAt: Date,
): Promise<void> {
  let at = startingAt.getTime()
  for (const title of titles) {
    await recordListingEvent(
      { db, clock: fixedClock(new Date(at)) },
      { listingId: requireListingId(listingIdsByTitle, title), customerId, eventType: 'view' },
    )
    at += 60_000
  }
}

async function favoriteEach(
  db: AppDatabase,
  customerId: CustomerId,
  listingIdsByTitle: Record<string, ListingId>,
  titles: readonly string[],
  startingAt: Date,
): Promise<void> {
  let at = startingAt.getTime()
  for (const title of titles) {
    await toggleFavorite(
      { db, clock: fixedClock(new Date(at)) },
      { customerId, listingId: requireListingId(listingIdsByTitle, title) },
    )
    at += 60_000
  }
}

async function fillCart(
  db: AppDatabase,
  customerId: CustomerId,
  listingIdsByTitle: Record<string, ListingId>,
  titles: readonly string[],
  at: Date,
): Promise<void> {
  const context: ActionContext = { db, clock: fixedClock(at) }
  const cart = await currentCart(context, customerId)

  for (const title of titles) {
    await addToCart(context, {
      cartId: cart.id,
      listingId: requireListingId(listingIdsByTitle, title),
      quantity: 1,
    })
  }
}

async function seedBlockedCustomer(db: AppDatabase, adminId: AdminId): Promise<CustomerId> {
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
  listingIdsByTitle: Record<string, ListingId>,
): Promise<readonly CustomerId[]> {
  const ids: CustomerId[] = []
  let at = new Date('2026-07-12T15:00:00.000Z').getTime()

  for (const titles of ANONYMOUS_BROWSING) {
    const customer = await createAnonymousCustomer({ db, clock: fixedClock(new Date(at)) })
    ids.push(customer.id)
    await recordViews(db, customer.id, listingIdsByTitle, titles, new Date(at))
    at += 3_600_000
  }

  return ids
}
