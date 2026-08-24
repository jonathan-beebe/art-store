import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify'
import { listingFaqs } from '../../actions/messaging/listing-faqs.ts'
import { currentCustomerStanding } from '../../actions/moderation/current-customer-standing.ts'
import { canShop } from '../../core/moderation/customer-standing.ts'
import { blockedShopperNotice } from '../../core/shop/blocked-shopper-notice.ts'
import type { CartQuantityErrors } from '../../core/shop/cart-quantity.ts'
import { isListingFavorited } from './queries/find-favorite-listings.ts'
import { findListingOnStorefront } from './queries/find-listing-on-storefront.ts'
import { renderNotFound, shopPage } from './shop-page.ts'
import { storefrontCustomer } from './storefront-customer.ts'

/** What a refused submission on the listing page shows back: the ask-a-question
 * box, the add-to-cart quantity, or a field-less refusal for the shared slot —
 * whichever form the submission belonged to. */
export type ListingPageState = {
  questionBody?: string
  questionError?: string
  questionFormError?: string
  cartQuantity?: string
  cartErrors?: CartQuantityErrors
  cartFormError?: string
}

/**
 * The listing page, blank or carrying one form's refused submission — never
 * the page a fresh `GET` renders, which records the view this does not. Null
 * when the slug names nothing a visitor may see, the same 404 the `GET`
 * itself answers.
 */
export async function renderListingPage(
  shop: FastifyInstance,
  request: FastifyRequest,
  reply: FastifyReply,
  slug: string,
  state: ListingPageState = {},
  status?: number,
): Promise<FastifyReply> {
  const { db } = shop
  const found = await findListingOnStorefront(db, slug)
  if (found === null) return renderNotFound(reply)

  const { listing, seller, isPurchasable } = found
  const customer = storefrontCustomer(request)
  const standing = await currentCustomerStanding({ db }, customer.id)
  const rendered = status === undefined ? reply : reply.code(status)

  return rendered.render(
    'listing',
    shopPage({
      title: listing.title,
      listing,
      seller,
      isPurchasable,
      isFavorited: await isListingFavorited(db, { customerId: customer.id, listingId: listing.id }),
      canShop: canShop(standing),
      blockedNotice: blockedShopperNotice(standing),
      faqs: await listingFaqs({ db }, listing.id),
      questionBody: state.questionBody ?? '',
      questionError: state.questionError,
      questionFormError: state.questionFormError,
      cartQuantity: state.cartQuantity,
      cartErrors: state.cartErrors ?? {},
      cartFormError: state.cartFormError,
    }),
  )
}
