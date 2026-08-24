import { toggleFavorite } from '../../../actions/favorites/toggle-favorite.ts'
import { keepLocalRedirect } from '../../../core/auth/local-redirect.ts'
import { slugParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestOrigin } from '../../auth/request-origin.ts'
import { findFavoriteListings } from '../queries/find-favorite-listings.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

export const favoriteRoutes: ZodRoutes = (shop, _options, done) => {
  shop.get('/favorites', async (request, reply) => {
    const { db } = shop
    const customer = storefrontCustomer(request)

    return reply.render(
      'favorites',
      shopPage({ title: 'Favorites', listings: await findFavoriteListings(db, customer.id) }),
    )
  })

  shop.post('/art/:slug/favorite', { schema: { params: slugParams } }, async (request, reply) => {
    const { db, clock } = shop
    const { slug } = request.params
    const found = await findListingOnStorefront(db, slug)
    if (found === null) return renderNotFound(reply)

    const customer = storefrontCustomer(request)
    await toggleFavorite({ db, clock }, { customerId: customer.id, listingId: found.listing.id })

    const destination =
      keepLocalRedirect(request.headers.referer, 'customer', requestOrigin(request)) ?? `/art/${slug}`

    return reply.redirect(destination)
  })

  done()
}
