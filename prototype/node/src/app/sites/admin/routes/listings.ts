import { z } from 'zod'
import { LISTING_STATUSES } from '../../../core/listings/listing-status.ts'
import { REMOVAL_KINDS } from '../../../core/moderation/listing-removal.ts'
import { idParams, idValue, optionalFilter } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { listingDetail } from '../queries/listing-detail.ts'
import { listingRows, REMOVED_FILTERS } from '../queries/listing-rows.ts'

const listingsQuery = z.object({
  status: optionalFilter(z.enum(LISTING_STATUSES)),
  seller: optionalFilter(idValue('sel')),
  removed: optionalFilter(z.enum(REMOVED_FILTERS)).default('any'),
})

export const listingRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/listings', { schema: { querystring: listingsQuery } }, async (request, reply) => {
    const { status, seller, removed } = request.query
    const listings = await listingRows({ db: admin.db }, { status, sellerId: seller, removed })

    return reply.render(
      'listings',
      adminPage('Listings', {
        listings,
        statuses: LISTING_STATUSES,
        removedFilters: REMOVED_FILTERS,
        filters: { status: status ?? '', seller: seller ?? '', removed },
      }),
    )
  })

  admin.get('/listings/:id', { schema: { params: idParams('lst') } }, async (request, reply) => {
    const detail = await listingDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render(
      'listing',
      adminPage(detail.listing.title, { ...detail, removalKinds: REMOVAL_KINDS }),
    )
  })

  done()
}
