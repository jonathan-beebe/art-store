import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { toggleFavorite } from '../../../actions/favorites/toggle-favorite.ts'
import { keepLocalRedirect } from '../../../core/auth/local-redirect.ts'
import { requestOrigin } from '../../auth/request-origin.ts'
import { findFavoriteListings } from '../queries/find-favorite-listings.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const parameters = z.object({ slug: z.string() })

export const favoriteRoutes: FastifyPluginCallback = (shop, _options, done) => {
  shop.get('/favorites', async (request, reply) => {
    const { db } = shop
    const customer = storefrontCustomer(request)

    return reply.render(
      'favorites',
      shopPage({ title: 'Favorites', listings: await findFavoriteListings(db, customer.id) }),
    )
  })

  shop.post('/art/:slug/favorite', async (request, reply) => {
    const { db, clock } = shop
    const { slug } = parameters.parse(request.params)
    const found = await findListingOnStorefront(db, slug)
    if (found === null) return renderNotFound(reply)

    const customer = storefrontCustomer(request)
    await toggleFavorite({ db, clock }, { customerId: customer.id, listingId: found.listing.id })

    const destination = keepLocalRedirect(request.headers.referer, requestOrigin(request)) ?? `/art/${slug}`
    return reply.redirect(destination)
  })

  done()
}
