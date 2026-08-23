import { addToCart } from '../../actions/carts/add-to-cart.ts'
import { currentCart } from '../../actions/carts/current-cart.ts'
import { changeListingStatus } from '../../actions/listings/change-listing-status.ts'
import { createListing } from '../../actions/listings/create-listing.ts'
import { finalizeOrder } from '../../actions/orders/finalize-order.ts'
import { placeOrderOrThrow } from '../../actions/orders/place-order.ts'
import type { ActionContext } from '../../actions/action-context.ts'
import type { ListingDraft } from '../../core/listings/listing-draft.ts'
import type { ListingStatus } from '../../core/listings/listing-status.ts'
import type { RemovalKind } from '../../core/moderation/listing-removal.ts'
import type { ShippingAddress } from '../../core/orders/shipping-address.ts'
import type { Listing, Order } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type ArtworkInput = {
  sellerId: number
  title?: string
  description?: string | null
  medium?: string | null
  dimensions?: string | null
  priceCents?: number
  quantity?: number
  status?: ListingStatus
}

/** Every listing is born a draft, so a fixture walks the lifecycle to reach the
 * status the test is about. */
const ROUTE_TO_STATUS: Readonly<Record<ListingStatus, readonly ListingStatus[]>> = {
  draft: [],
  for_sale: ['for_sale'],
  sold: ['for_sale', 'sold'],
  archived: ['archived'],
}

/**
 * A seller's piece as the storefront tests need it: on sale by default, at any
 * point in its lifecycle on request.
 */
function artworkDraft(input: ArtworkInput): ListingDraft {
  return {
    title: input.title ?? 'Harbour at dusk',
    description: input.description ?? 'Oil study of the harbour.',
    medium: input.medium ?? 'Oil on canvas',
    dimensions: input.dimensions ?? '40 x 60 cm',
    priceCents: input.priceCents ?? 24_000,
    quantity: input.quantity ?? 1,
  }
}

export async function listArtwork(context: ActionContext, input: ArtworkInput): Promise<Listing> {
  let listing = await createListing(context, {
    sellerId: input.sellerId,
    draft: artworkDraft(input),
  })

  for (const status of ROUTE_TO_STATUS[input.status ?? 'for_sale']) {
    listing = await changeListingStatus(context, { listingId: listing.id, status })
  }

  return listing
}

export async function removeListing(
  { db, clock }: ActionContext,
  input: { listingId: number; adminId: number; kind?: RemovalKind; reason?: string },
): Promise<void> {
  await db
    .insertInto('listingRemovals')
    .values({
      listingId: input.listingId,
      adminId: input.adminId,
      kind: input.kind ?? 'temporary',
      reason: input.reason ?? 'Reported as a reproduction.',
      createdAt: toTimestamp(clock.now()),
      liftedAt: null,
    })
    .execute()
}

export const TEST_SHIPPING: ShippingAddress = {
  name: 'Ada Lovelace',
  line1: '12 Analytical Way',
  line2: null,
  city: 'London',
  region: 'Greater London',
  postalCode: 'EC1A 1BB',
  country: 'GB',
}

export const APPROVED_CARD = '4242424242424242'
export const DECLINED_CARD = '4000000000000002'

/**
 * A cart holding one seller's piece, ready for an order. Tests about the order
 * pages arrive here without walking the storefront to get there.
 */
export async function cartWithArtwork(
  context: ActionContext,
  input: { customerId: number; listingId: number; quantity?: number },
): Promise<number> {
  const cart = await currentCart(context, input.customerId)
  await addToCart(context, {
    cartId: cart.id,
    listingId: input.listingId,
    quantity: input.quantity ?? 1,
  })

  return cart.id
}

export async function placeCustomerOrder(
  context: ActionContext,
  input: { cartId: number; customerId: number; email?: string; isEmailVerified?: boolean },
): Promise<Order> {
  return placeOrderOrThrow(context, {
    cartId: input.cartId,
    purchaser: {
      id: input.customerId,
      email: input.email ?? 'buyer@example.com',
      isEmailVerified: input.isEmailVerified ?? true,
    },
    shipping: TEST_SHIPPING,
  })
}

/** An order already paid for, which is where shipping and delivery start. */
export async function payForOrder(
  context: ActionContext,
  input: { orderId: number; cardNumber?: string },
): Promise<Order> {
  return finalizeOrder(context, {
    orderId: input.orderId,
    cardNumber: input.cardNumber ?? APPROVED_CARD,
  })
}

export async function blockCustomer(
  { db, clock }: ActionContext,
  input: { customerId: number; adminId: number; reason?: string },
): Promise<void> {
  await db
    .insertInto('customerBlocks')
    .values({
      customerId: input.customerId,
      adminId: input.adminId,
      reason: input.reason ?? 'Chargeback fraud.',
      createdAt: toTimestamp(clock.now()),
      liftedAt: null,
    })
    .execute()
}
