import { z } from 'zod'
import { listingPage } from '../../../core/shop/listing-page.ts'
import { filterQuery, parseListingSearch } from '../../../core/shop/listing-search.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import {
  countStorefrontListings,
  findStorefrontListings,
  findStorefrontMedia,
} from '../queries/find-storefront-listings.ts'
import { shopPage } from '../shop-page.ts'

// Three across on a wide screen, four rows deep.
const LISTINGS_PER_PAGE = 12

const searchQuery = z.object({
  q: z.string().optional(),
  medium: z.string().optional(),
  page: z.string().optional(),
})

export const homeRoutes: ZodRoutes = (shop, _options, done) => {
  shop.get('/', { schema: { querystring: searchQuery } }, async (request, reply) => {
    const asked = request.query
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
        filterQuery: filterQuery(search),
        page,
        listings: await findStorefrontListings(shop.db, { search, page }),
        media: await findStorefrontMedia(shop.db),
      }),
    )
  })

  done()
}
