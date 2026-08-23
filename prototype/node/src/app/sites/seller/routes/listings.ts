import path from 'node:path'
import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { activeListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import { changeListingStatus } from '../../../actions/listings/change-listing-status.ts'
import { createListing } from '../../../actions/listings/create-listing.ts'
import { updateListing } from '../../../actions/listings/update-listing.ts'
import {
  listingDraftErrors,
  parseListingDraft,
  type ListingDraftErrors,
  type ListingDraftFields,
} from '../../../core/listings/listing-draft.ts'
import { listingImageSource } from '../../../core/listings/placeholder-image.ts'
import { LISTING_STATUSES, type ListingStatus } from '../../../core/listings/listing-status.ts'
import type { Listing } from '../../../db/commerce-schema.ts'
import { formatCents } from '../../../core/money.ts'
import { activityTimeline } from '../../../core/reports/activity-timeline.ts'
import { activityTotals } from '../../../core/reports/activity-totals.ts'
import { statusLabel } from '../../../core/reports/status-label.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { currentSellerId } from '../current-seller.ts'
import { dollarsInputValue, formatDate, formatDay } from '../format.ts'
import { listingDraftFieldsFrom, uploadedImagePart, type MultipartBody } from '../listing-form.ts'
import { saveUploadedListingImage } from '../listing-image-upload.ts'
import { sellerListingTransitions } from '../listing-transitions.ts'
import { sellerNotFound } from '../not-found.ts'
import { parseIdParam } from '../params.ts'
import { listingEventCountsByDay, listingEventTotals, salesForListing } from '../queries/listing-activity.ts'
import {
  listingEventCountsByListing,
  listingIdsWithActiveRemoval,
  listingsForSeller,
  ownedListing,
} from '../queries/listings.ts'

const ACTIVITY_WINDOW_DAYS = 14
const ACTIVITY_WINDOW_MS = ACTIVITY_WINDOW_DAYS * 24 * 60 * 60 * 1000
const UPLOADS_DIR = path.join(import.meta.dirname, '..', '..', '..', '..', 'public', 'uploads')

const statusChangeSchema = z.object({ status: z.enum(LISTING_STATUSES) })

function emptyDraftFields(): ListingDraftFields {
  return { title: '', description: '', medium: '', dimensions: '', price: '', quantity: '1' }
}

function editFieldsFrom(listing: Listing): ListingDraftFields {
  return {
    title: listing.title,
    description: listing.description ?? '',
    medium: listing.medium ?? '',
    dimensions: listing.dimensions ?? '',
    price: dollarsInputValue(listing.priceCents),
    quantity: String(listing.quantity),
  }
}

async function imagePathFromUpload(body: MultipartBody): Promise<string | undefined> {
  const image = uploadedImagePart(body)
  if (image === null) return undefined

  return saveUploadedListingImage(UPLOADS_DIR, await image.toBuffer(), image.mimetype, image.filename)
}

function blocksReturnToSale(hasActiveRemoval: boolean, status: ListingStatus): boolean {
  return hasActiveRemoval && status === 'for_sale'
}

async function findOwnedListing(request: FastifyRequest, reply: FastifyReply): Promise<Listing | null> {
  const id = parseIdParam(request.params)
  if (id === null) {
    await sellerNotFound(reply)
    return null
  }

  const listing = await ownedListing(request.server.db, currentSellerId(request), id)
  if (listing === null) await sellerNotFound(reply)

  return listing
}

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db } = request.server
  const listings = await listingsForSeller(db, currentSellerId(request))
  const ids = listings.map((listing) => listing.id)
  const [eventCounts, removedIds] = await Promise.all([
    listingEventCountsByListing(db, ids),
    listingIdsWithActiveRemoval(db, ids),
  ])

  const rows = listings.map((listing) => ({
    listing,
    activity: activityTotals(eventCounts.get(listing.id) ?? {}),
    transitions: sellerListingTransitions(listing.status, removedIds.has(listing.id)),
    imageSrc: listingImageSource(listing.imagePath, listing.title),
  }))

  return reply.render('listings/index', { title: 'Listings', rows, statusLabel, formatCents })
}

async function newForm(_request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  return reply.render('listings/new', {
    title: 'New listing',
    fields: emptyDraftFields(),
    errors: {} as ListingDraftErrors,
  })
}

async function create(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const body = request.body as MultipartBody
  const fields = listingDraftFieldsFrom(body)
  const errors = listingDraftErrors(fields)
  if (Object.keys(errors).length > 0) {
    return reply.code(422).render('listings/new', { title: 'New listing', fields, errors })
  }

  const { db, clock } = request.server
  const listing = await createListing(
    { db, clock },
    { sellerId: currentSellerId(request), draft: parseListingDraft(fields), imagePath: await imagePathFromUpload(body) },
  )

  reply.setFlash({ notice: `"${listing.title}" is saved as a draft.` })
  return reply.redirect('/seller/listings')
}

async function editForm(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const listing = await findOwnedListing(request, reply)
  if (listing === null) return reply

  return reply.render('listings/edit', {
    title: `Edit ${listing.title}`,
    listing,
    fields: editFieldsFrom(listing),
    errors: {} as ListingDraftErrors,
    imageSrc: listingImageSource(listing.imagePath, listing.title),
  })
}

async function update(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const listing = await findOwnedListing(request, reply)
  if (listing === null) return reply

  const body = request.body as MultipartBody
  const fields = listingDraftFieldsFrom(body)
  const errors = listingDraftErrors(fields)
  if (Object.keys(errors).length > 0) {
    return reply.code(422).render('listings/edit', {
      title: `Edit ${listing.title}`,
      listing,
      fields,
      errors,
      imageSrc: listingImageSource(listing.imagePath, listing.title),
    })
  }

  const { db, clock } = request.server
  const updated = await updateListing(
    { db, clock },
    { listingId: listing.id, draft: parseListingDraft(fields), imagePath: await imagePathFromUpload(body) },
  )

  reply.setFlash({ notice: `"${updated.title}" is updated.` })
  return reply.redirect('/seller/listings')
}

function refuseStatusChange(reply: FastifyReply, message: string): FastifyReply {
  reply.setFlash({ alert: message })
  return reply.redirect('/seller/listings')
}

async function changeStatus(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const listing = await findOwnedListing(request, reply)
  if (listing === null) return reply

  const parsed = statusChangeSchema.safeParse(request.body)
  if (!parsed.success) return refuseStatusChange(reply, 'Choose a status to change to.')

  const { db, clock } = request.server
  const removal = await activeListingRemoval({ db }, listing.id)
  if (blocksReturnToSale(removal !== null, parsed.data.status)) {
    return refuseStatusChange(reply, 'This listing was removed by an admin and cannot be put back on sale.')
  }

  try {
    const updated = await changeListingStatus({ db, clock }, { listingId: listing.id, status: parsed.data.status })
    reply.setFlash({ notice: `"${updated.title}" is now ${statusLabel(updated.status).toLowerCase()}.` })
    return reply.redirect('/seller/listings')
  } catch (error) {
    if (error instanceof TransitionError) return refuseStatusChange(reply, error.message)
    throw error
  }
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const listing = await findOwnedListing(request, reply)
  if (listing === null) return reply

  const { db, clock } = request.server
  const now = clock.now()
  const [eventTotals, dailyCounts, sales, removal] = await Promise.all([
    listingEventTotals(db, listing.id),
    listingEventCountsByDay(db, listing.id, new Date(now.getTime() - ACTIVITY_WINDOW_MS)),
    salesForListing(db, listing.id),
    activeListingRemoval({ db }, listing.id),
  ])

  return reply.render('listings/show', {
    title: listing.title,
    listing,
    imageSrc: listingImageSource(listing.imagePath, listing.title),
    totals: activityTotals(eventTotals),
    days: activityTimeline(dailyCounts, { endsOn: now, days: ACTIVITY_WINDOW_DAYS }),
    sales,
    removal,
    transitions: sellerListingTransitions(listing.status, removal !== null),
    statusLabel,
    formatCents,
    formatDate,
    formatDay,
  })
}

export const listingsRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/listings', index)
  portal.get('/listings/new', newForm)
  portal.post('/listings', create)
  portal.get('/listings/:id', show)
  portal.get('/listings/:id/edit', editForm)
  portal.post('/listings/:id', update)
  portal.post('/listings/:id/status', changeStatus)

  done()
}
