import type { FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { changeListingStatus } from '../../../actions/listings/change-listing-status.ts'
import { createListing } from '../../../actions/listings/create-listing.ts'
import { updateListing } from '../../../actions/listings/update-listing.ts'
import { activeListingRemoval } from '../../../actions/moderation/active-listing-removal.ts'
import type { ListingId } from '../../../core/ids/entity-ids.ts'
import { sniffImageFormat, type ImageFormat } from '../../../core/listings/image-format.ts'
import {
  parseListingDraft,
  type ListingDraftErrors,
  type ListingDraftFields,
  type UploadedImageFormat,
} from '../../../core/listings/listing-draft.ts'
import {
  LISTING_STATUSES,
  availableListingTransitions,
} from '../../../core/listings/listing-status.ts'
import { listingImageSource } from '../../../core/listings/placeholder-image.ts'
import { dollarsInputValue, formatCents } from '../../../core/money.ts'
import { activityTimeline, activityWindow } from '../../../core/reports/activity-timeline.ts'
import { activityTotals } from '../../../core/reports/activity-totals.ts'
import { statusButtons, statusLabel } from '../../../core/status-label.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import type { Listing } from '../../../db/commerce-schema.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestActions } from '../../../http/request-actions.ts'
import { identityId } from '../../../plugins/identity.ts'
import { rateLimitGuard } from '../../../plugins/rate-limit.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate, formatDay } from '../format.ts'
import {
  listingDraftFieldsFrom,
  listingFormBody,
  uploadedImagePart,
  type ListingFormBody,
} from '../listing-form.ts'
import { MAX_IMAGE_UPLOAD_MB, saveUploadedListingImage } from '../listing-image-upload.ts'
import { sellerNotFound } from '../not-found.ts'
import {
  listingEventCountsByDay,
  listingEventTotals,
  salesForListing,
} from '../queries/listing-activity.ts'
import {
  listingEventCountsByListing,
  listingIdsWithActiveRemoval,
  listingsForSeller,
  ownedListing,
} from '../queries/listings.ts'

const ACTIVITY_WINDOW_DAYS = 14
const OVERSIZED_IMAGE_MESSAGE = `Upload an image under ${MAX_IMAGE_UPLOAD_MB} MB.`

// The status field carries a transition the page offered. Anything else — no
// field at all, or a status the lifecycle does not name — is a button that
// named nothing, which the route answers with a flash rather than a 400.
const statusChangeForm = submittedForm({
  status: z.enum(LISTING_STATUSES).optional().catch(undefined),
})

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
function acceptedUploadedImage(
  uploadedImage: UploadedImage | null,
): { buffer: Buffer; format: ImageFormat } | null {
  if (uploadedImage === null || uploadedImage.format === 'unrecognized') return null

  return { buffer: uploadedImage.buffer, format: uploadedImage.format }
}

async function savedImagePath(
  uploadsDir: string,
  uploadedImage: UploadedImage | null,
): Promise<string | undefined> {
  const accepted = acceptedUploadedImage(uploadedImage)
  if (accepted === null) return undefined

  return saveUploadedListingImage(uploadsDir, accepted.buffer, accepted.format)
}

/**
 * The listing form re-rendered with an image field error, for a multipart
 * upload @fastify/multipart refused as too large before any route handler
 * ran. Shows the listing's saved fields on an edit URL for its owner,
 * otherwise the blank new-listing form.
 *
 * A part over the size limit throws while the body is still parsing, which is
 * before the route's own schemas run, so the url segment is read here rather
 * than off `request.params`. For the same reason the seller comes from the
 * identity cookie: no `preHandler` has resolved one yet.
 */
export async function renderOversizedImageForm(
  request: FastifyRequest,
  reply: FastifyReply,
): Promise<FastifyReply> {
  const errors: ListingDraftErrors = { image: OVERSIZED_IMAGE_MESSAGE }
  const asked = idParams('lst').safeParse(request.params)
  const sellerId = identityId(request, 'seller')
  const listing =
    asked.success && sellerId !== null
      ? await ownedListing(request.server.db, sellerId, asked.data.id)
      : null

  if (listing !== null) {
    return reply.code(422).render('listings/edit', {
      title: `Edit ${listing.title}`,
      listing,
      fields: editFieldsFrom(listing),
      errors,
      imageSrc: listingImageSource(listing.imagePath, listing.title),
    })
  }

  return reply.code(422).render('listings/new', {
    title: 'New listing',
    fields: emptyDraftFields(),
    errors,
  })
}

/** The seller's own listing the url names, or null with the 404 page already
 * answered — another seller's listing is not found, not refused. */
async function findOwnedListing(
  request: FastifyRequest,
  reply: FastifyReply,
  listingId: ListingId,
): Promise<Listing | null> {
  const listing = await ownedListing(request.server.db, currentSellerId(request), listingId)
  if (listing === null) await sellerNotFound(reply)

  return listing
}

function refuseStatusChange(reply: FastifyReply, message: string): FastifyReply {
  reply.setFlash({ alert: message })

  return reply.redirect('/seller/listings')
}

/** The listing form's text fields as a tripped `listing_write` submitted, read
 * the same way the route's own schema would — the image part is left alone,
 * so a trip never re-sniffs or re-saves the upload it did not get to. */
function submittedListingFields(body: unknown): ListingDraftFields {
  const parsed = listingFormBody.safeParse(body)

  return parsed.success ? listingDraftFieldsFrom(parsed.data, null) : emptyDraftFields()
}

const guardCreateListingWrite = rateLimitGuard({
  name: 'listing_write',
  key: currentSellerId,
  onTrip: (request) => async (reply, message) =>
    reply.render('listings/new', {
      title: 'New listing',
      fields: submittedListingFields(request.body),
      errors: NO_ERRORS,
      formError: message,
    }),
})

const guardUpdateListingWrite = rateLimitGuard<{ id: ListingId }>({
  name: 'listing_write',
  key: currentSellerId,
  onTrip: (request) => async (reply, message) => {
    const listing = await ownedListing(request.server.db, currentSellerId(request), request.params.id)
    if (listing === null) return sellerNotFound(reply)

    return reply.render('listings/edit', {
      title: `Edit ${listing.title}`,
      listing,
      fields: submittedListingFields(request.body),
      errors: NO_ERRORS,
      formError: message,
      imageSrc: listingImageSource(listing.imagePath, listing.title),
    })
  },
})

export const listingsRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/listings', async (request, reply) => {
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

    return reply.render('listings/index', {
      title: 'Listings',
      rows,
      statusLabel,
      statusButtons,
      formatCents,
    })
  })

  portal.get('/listings/new', async (_request, reply) =>
    reply.render('listings/new', {
      title: 'New listing',
      fields: emptyDraftFields(),
      errors: NO_ERRORS,
    }),
  )

  portal.post(
    '/listings',
    { schema: { body: listingFormBody }, preHandler: guardCreateListingWrite },
    async (request, reply) => {
      const uploadedImage = await readUploadedImage(request.body)
      const fields = listingDraftFieldsFrom(request.body, uploadedImage?.format ?? null)
      const draft = parseListingDraft(fields)
      if (!draft.ok) {
        return reply.code(422).render('listings/new', {
          title: 'New listing',
          fields,
          errors: draft.errors,
        })
      }

      const { config } = request.server
      const listing = await createListing(
        requestActions(request),
        {
          sellerId: currentSellerId(request),
          draft: draft.value,
          imagePath: await savedImagePath(config.uploadsDir, uploadedImage),
        },
      )

      reply.setFlash({ notice: `"${listing.title}" is saved as a draft.` })

      return reply.redirect('/seller/listings')
    },
  )

  portal.get('/listings/:id', { schema: { params: idParams('lst') } }, async (request, reply) => {
    const listing = await findOwnedListing(request, reply, request.params.id)
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
      statusButtons,
      formatCents,
      formatDate,
      formatDay,
    })
  })

  portal.get('/listings/:id/edit', { schema: { params: idParams('lst') } }, async (request, reply) => {
    const listing = await findOwnedListing(request, reply, request.params.id)
    if (listing === null) return reply

    return reply.render('listings/edit', {
      title: `Edit ${listing.title}`,
      listing,
      fields: editFieldsFrom(listing),
      errors: NO_ERRORS,
      imageSrc: listingImageSource(listing.imagePath, listing.title),
    })
  })

  portal.post(
    '/listings/:id',
    { schema: { params: idParams('lst'), body: listingFormBody }, preHandler: guardUpdateListingWrite },
    async (request, reply) => {
      const listing = await findOwnedListing(request, reply, request.params.id)
      if (listing === null) return reply

      const uploadedImage = await readUploadedImage(request.body)
      const fields = listingDraftFieldsFrom(request.body, uploadedImage?.format ?? null)
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

      const { config } = request.server
      const updated = await updateListing(
        requestActions(request),
        {
          listingId: listing.id,
          draft: draft.value,
          imagePath: await savedImagePath(config.uploadsDir, uploadedImage),
        },
      )

      reply.setFlash({ notice: `"${updated.title}" is updated.` })

      return reply.redirect('/seller/listings')
    },
  )

  portal.post(
    '/listings/:id/status',
    { schema: { params: idParams('lst'), body: statusChangeForm } },
    async (request, reply) => {
      const listing = await findOwnedListing(request, reply, request.params.id)
      if (listing === null) return reply

      const { status } = request.body
      if (status === undefined) return refuseStatusChange(reply, 'Choose a status to change to.')

      try {
        const updated = await changeListingStatus(requestActions(request), {
          listingId: listing.id,
          status,
        })
        reply.setFlash({ notice: `"${updated.title}" is now ${statusLabel(updated.status).toLowerCase()}.` })

        return reply.redirect('/seller/listings')
      } catch (error) {
        if (error instanceof TransitionError) return refuseStatusChange(reply, error.message)
        throw error
      }
    },
  )

  done()
}
