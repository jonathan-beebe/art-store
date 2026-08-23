import { recordListingEvent } from '../../../actions/listings/record-listing-event.ts'
import { listingFaqs } from '../../../actions/messaging/listing-faqs.ts'
import { currentCustomerStanding } from '../../../actions/moderation/current-customer-standing.ts'
import { canShop } from '../../../core/moderation/customer-standing.ts'
import { blockedShopperNotice } from '../../../core/shop/blocked-shopper-notice.ts'
import { slugParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { isListingFavorited } from '../queries/find-favorite-listings.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

export const listingRoutes: ZodRoutes = (shop, _options, done) => {
  shop.get('/art/:slug', { schema: { params: slugParams } }, async (request, reply) => {
    const { db, clock } = shop
    const found = await findListingOnStorefront(db, request.params.slug)
    if (found === null) return renderNotFound(reply)

    const { listing, seller, isPurchasable } = found
    const customer = storefrontCustomer(request)
    await recordListingEvent(
      { db, clock },
      { listingId: listing.id, customerId: customer.id, eventType: 'view' },
    )

    const standing = await currentCustomerStanding({ db }, customer.id)

    return reply.render(
      'listing',
      shopPage({
        title: listing.title,
        listing,
        seller,
        isPurchasable,
        isFavorited: await isListingFavorited(db, {
          customerId: customer.id,
          listingId: listing.id,
        }),
        canShop: canShop(standing),
        blockedNotice: blockedShopperNotice(standing),
        faqs: await listingFaqs({ db }, listing.id),
      }),
    )
  })

  done()
}
