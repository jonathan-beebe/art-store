import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { LISTING_STATUSES } from '../../../core/listings/listing-status.ts'
import { REMOVAL_KINDS } from '../../../core/moderation/listing-removal.ts'
import { adminPage } from '../page.ts'
import { listingDetail } from '../queries/listing-detail.ts'
import { listingRows, type ListingRemovedFilter } from '../queries/listing-rows.ts'

const REMOVED_FILTERS = ['any', 'removed', 'visible'] as const

const idParams = z.object({ id: z.coerce.number().int().positive() })

const listingsQuery = z.object({
  status: z.enum(LISTING_STATUSES).optional(),
  seller: z.coerce.number().int().positive().optional(),
  removed: z.enum(REMOVED_FILTERS).optional(),
})

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const query = listingsQuery.parse(request.query)
  const removed: ListingRemovedFilter = query.removed ?? 'any'
  const listings = await listingRows(
    { db: request.server.db },
    { status: query.status, sellerId: query.seller, removed },
  )

  return reply.render(
    'listings',
    adminPage('Listings', {
      listings,
      statuses: LISTING_STATUSES,
      removedFilters: REMOVED_FILTERS,
      filters: { status: query.status ?? '', seller: query.seller ?? '', removed },
    }),
  )
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const parsed = idParams.safeParse(request.params)
  if (!parsed.success) return reply.code(404).type('text/plain').send('Not found')

  const detail = await listingDetail({ db: request.server.db }, parsed.data.id)
  if (detail === null) return reply.code(404).type('text/plain').send('Not found')

  return reply.render(
    'listing',
    adminPage(detail.listing.title, { ...detail, removalKinds: REMOVAL_KINDS }),
  )
}

export const listingRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/listings', index)
  admin.get('/listings/:id', show)

  done()
}
