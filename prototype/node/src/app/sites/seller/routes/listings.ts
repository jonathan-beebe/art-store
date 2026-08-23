import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { activeListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import { changeListingStatus } from '../../../actions/listings/change-listing-status.ts'
import { createListing } from '../../../actions/listings/create-listing.ts'
import { updateListing } from '../../../actions/listings/update-listing.ts'
import {
  parseListingDraft,
  type ListingDraftErrors,
  type ListingDraftFields,
  type UploadedImageFormat,
} from '../../../core/listings/listing-draft.ts'
import { sniffImageFormat, type ImageFormat } from '../../../core/listings/image-format.ts'
import { listingImageSource } from '../../../core/listings/placeholder-image.ts'
import { LISTING_STATUSES, availableListingTransitions } from '../../../core/listings/listing-status.ts'
import type { Listing } from '../../../db/commerce-schema.ts'
import { dollarsInputValue, formatCents } from '../../../core/money.ts'
import { activityTimeline, activityWindow } from '../../../core/reports/activity-timeline.ts'
import { activityTotals } from '../../../core/reports/activity-totals.ts'
import { statusLabel } from '../../../core/status-label.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate, formatDay } from '../format.ts'
import {
  listingDraftFieldsFrom,
  parseListingFormBody,
  uploadedImagePart,
  type ListingFormBody,
} from '../listing-form.ts'
import { MAX_IMAGE_UPLOAD_MB, saveUploadedListingImage } from '../listing-image-upload.ts'
import { sellerNotFound } from '../not-found.ts'
import { identityCookieValue } from '../../../plugins/identity.ts'
import { parseIdParam } from '../../../http/id-param.ts'
import { listingEventCountsByDay, listingEventTotals, salesForListing } from '../queries/listing-activity.ts'
import {
  listingEventCountsByListing,
  listingIdsWithActiveRemoval,
  listingsForSeller,
  ownedListing,
} from '../queries/listings.ts'

const ACTIVITY_WINDOW_DAYS = 14
const OVERSIZED_IMAGE_MESSAGE = `Upload an image under ${MAX_IMAGE_UPLOAD_MB} MB.`

const statusChangeSchema = z.object({ status: z.enum(LISTING_STATUSES) })

/** A form shown before anything has been submitted has nothing wrong with it. */
const NO_ERRORS: ListingDraftErrors = {}

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

type UploadedImage = { buffer: Buffer; format: UploadedImageFormat }

/** Reads the uploaded part's bytes and sniffs its format — the part's own
 * filename and `Content-Type` decide nothing. Null when the field was left
 * empty. */
async function readUploadedImage(body: ListingFormBody): Promise<UploadedImage | null> {
  const image = uploadedImagePart(body)
  if (image === null) return null

  const buffer = await image.toBuffer()

  return { buffer, format: sniffImageFormat(buffer) ?? 'unrecognized' }
}

/** The upload once `parseListingDraft` has already refused an unrecognized
 * format — narrows `format` to a real `ImageFormat` for the caller. */
function acceptedUploadedImage(uploadedImage: UploadedImage | null): { buffer: Buffer; format: ImageFormat } | null {
  if (uploadedImage === null || uploadedImage.format === 'unrecognized') return null

  return { buffer: uploadedImage.buffer, format: uploadedImage.format }
}

async function savedImagePath(uploadsDir: string, uploadedImage: UploadedImage | null): Promise<string | undefined> {
  const accepted = acceptedUploadedImage(uploadedImage)
  if (accepted === null) return undefined

  return saveUploadedListingImage(uploadsDir, accepted.buffer, accepted.format)
}

/** The seller id an identity cookie names, read independent of the
 * `preHandler` identity hooks — a multipart part too large for the size
 * limit throws while the body is still parsing, before any `preHandler`
 * (including the one that resolves `request.currentSeller`) has run. */
async function sellerIdFromIdentityCookie(request: FastifyRequest): Promise<number | null> {
  const raw = identityCookieValue(request, 'seller')
  if (raw === null) return null

  const id = Number(raw)

  return Number.isInteger(id) && id >= 1 ? id : null
}

/**
 * The listing form re-rendered with an image field error, for a multipart
 * upload @fastify/multipart refused as too large before any route handler
 * ran. Shows the listing's saved fields on an edit URL for its owner,
 * otherwise the blank new-listing form.
 */
export async function renderOversizedImageForm(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const errors: ListingDraftErrors = { image: OVERSIZED_IMAGE_MESSAGE }
  const id = parseIdParam(request.params)
  const sellerId = id === null ? null : await sellerIdFromIdentityCookie(request)
  const listing = id === null || sellerId === null ? null : await ownedListing(request.server.db, sellerId, id)

  if (listing !== null) {
    return reply.code(422).render('listings/edit', {
      title: `Edit ${listing.title}`,
      listing,
      fields: editFieldsFrom(listing),
      errors,
      imageSrc: listingImageSource(listing.imagePath, listing.title),
    })
  }

  return reply.code(422).render('listings/new', { title: 'New listing', fields: emptyDraftFields(), errors })
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
  const eventCounts = await listingEventCountsByListing(db, ids)
  const removedIds = await listingIdsWithActiveRemoval(db, ids)

  const rows = listings.map((listing) => ({
    listing,
    activity: activityTotals(eventCounts.get(listing.id) ?? {}),
    transitions: availableListingTransitions(listing.status, removedIds.has(listing.id)),
    imageSrc: listingImageSource(listing.imagePath, listing.title),
  }))

  return reply.render('listings/index', { title: 'Listings', rows, statusLabel, formatCents })
}

async function newForm(_request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  return reply.render('listings/new', {
    title: 'New listing',
    fields: emptyDraftFields(),
    errors: NO_ERRORS,
  })
}

async function create(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const body = parseListingFormBody(request.body)
  const uploadedImage = await readUploadedImage(body)
  const fields = listingDraftFieldsFrom(body, uploadedImage?.format ?? null)
  const draft = parseListingDraft(fields)
  if (!draft.ok) {
    return reply.code(422).render('listings/new', { title: 'New listing', fields, errors: draft.errors })
  }

  const { db, clock, config } = request.server
  const listing = await createListing(
    { db, clock },
    {
      sellerId: currentSellerId(request),
      draft: draft.value,
      imagePath: await savedImagePath(config.uploadsDir, uploadedImage),
    },
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
    errors: NO_ERRORS,
    imageSrc: listingImageSource(listing.imagePath, listing.title),
  })
}

async function update(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const listing = await findOwnedListing(request, reply)
  if (listing === null) return reply

  const body = parseListingFormBody(request.body)
  const uploadedImage = await readUploadedImage(body)
  const fields = listingDraftFieldsFrom(body, uploadedImage?.format ?? null)
  const draft = parseListingDraft(fields)
  if (!draft.ok) {
    return reply.code(422).render('listings/edit', {
      title: `Edit ${listing.title}`,
      listing,
      fields,
      errors: draft.errors,
      imageSrc: listingImageSource(listing.imagePath, listing.title),
    })
  }

  const { db, clock, config } = request.server
  const updated = await updateListing(
    { db, clock },
    {
      listingId: listing.id,
      draft: draft.value,
      imagePath: await savedImagePath(config.uploadsDir, uploadedImage),
    },
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
  const window = activityWindow(now, ACTIVITY_WINDOW_DAYS)
  const eventTotals = await listingEventTotals(db, listing.id)
  const dailyCounts = await listingEventCountsByDay(db, listing.id, window.since)
  const sales = await salesForListing(db, listing.id)
  const removal = await activeListingRemoval({ db }, listing.id)

  return reply.render('listings/show', {
    title: listing.title,
    listing,
    imageSrc: listingImageSource(listing.imagePath, listing.title),
    totals: activityTotals(eventTotals),
    days: activityTimeline(dailyCounts, { endsOn: now, days: window.days }),
    sales,
    removal,
    transitions: availableListingTransitions(listing.status, removal !== null),
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
