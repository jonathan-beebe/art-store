import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { listingPage } from '../../../core/shop/listing-page.ts'
import { parseListingSearch } from '../../../core/shop/listing-search.ts'
import {
  countStorefrontListings,
  findStorefrontListings,
  findStorefrontMedia,
} from '../queries/find-storefront-listings.ts'
import { shopPage } from '../shop-page.ts'

// Three across on a wide screen, four rows deep.
const LISTINGS_PER_PAGE = 12

const query = z.object({
  q: z.string().optional(),
  medium: z.string().optional(),
  page: z.string().optional(),
})

export const homeRoutes: FastifyPluginCallback = (shop, _options, done) => {
  shop.get('/', async (request, reply) => {
    const asked = query.safeParse(request.query).data ?? {}
    const search = parseListingSearch({ term: asked.q, medium: asked.medium })
    const page = listingPage({
      requested: asked.page,
      size: LISTINGS_PER_PAGE,
      totalCount: await countStorefrontListings(shop.db, search),
    })

    return reply.render(
      'home',
      shopPage({
        title: search.term === null ? 'Original art' : `Art matching “${search.term}”`,
        searchTerm: search.term ?? '',
        search,
        page,
        listings: await findStorefrontListings(shop.db, { search, page }),
        media: await findStorefrontMedia(shop.db),
      }),
    )
  })

  done()
}
