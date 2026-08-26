import type { FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { conversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { findListingFaq, listingFaqs } from '../../../actions/messaging/listing-faqs.ts'
import { publishListingFaq } from '../../../actions/messaging/publish-listing-faq.ts'
import { unpublishListingFaq } from '../../../actions/messaging/unpublish-listing-faq.ts'
import { updateListingFaq } from '../../../actions/messaging/update-listing-faq.ts'
import { resolveLocalRedirect } from '../../../core/auth/local-redirect.ts'
import type { ConversationId, ListingFaqId, ListingId } from '../../../core/ids/entity-ids.ts'
import { faqPrefill } from '../../../core/messaging/faq-prefill.ts'
import { parseFaqDraft, type FaqDraftErrors, type FaqDraftFields } from '../../../core/messaging/faq-draft.ts'
import type { Listing, ListingFaq } from '../../../db/commerce-schema.ts'
import { idParams, idValue, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestActions } from '../../../http/request-actions.ts'
import { requestOrigin } from '../../auth/request-origin.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import { ownedListing } from '../queries/listings.ts'

const faqParams = z.object({ id: idValue('lst'), faqId: idValue('faq') })

const faqForm = submittedForm({
  question: z.string().optional(),
  answer: z.string().optional(),
  source_message_id: idValue('msg').optional(),
  redirect_to: z.string().optional(),
  // Set only by the "Publish as FAQ" form on a message thread, so a refused
  // submission from there re-renders that thread rather than the FAQ index.
  conversation_id: idValue('cnv').optional(),
})

/** What one FAQ form (the publish form or one edit form) shows back after a
 * refusal: the values as typed, a message beside each bad field, and a
 * field-less refusal (a domain rule, not a validation) for the shared slot. */
type FaqFormState = { fields: FaqDraftFields; errors: FaqDraftErrors; formError?: string }

function faqsDestination(
  request: FastifyRequest,
  listingId: ListingId,
  redirectTo: string | undefined,
): string {
  return resolveLocalRedirect(redirectTo, {
    actorType: 'seller',
    fallback: `/seller/listings/${listingId}/faqs`,
    origin: requestOrigin(request),
  })
}

/** One form's state on the FAQ index page, defaulted for the row (or the
 * publish form) that carries no refused submission. */
function faqFormStateOrBlank(
  state: FaqFormState | undefined,
): { fields: FaqDraftFields; errors: FaqDraftErrors; formError: string | null } {
  if (state === undefined) return { fields: {}, errors: {}, formError: null }

  return { fields: state.fields, errors: state.errors, formError: state.formError ?? null }
}

/** The FAQ index, blank or carrying one row's refused submission — an edit
 * row by its id, or the publish form at the foot of the page. */
function renderFaqsIndex(
  reply: FastifyReply,
  listing: Listing,
  faqs: readonly ListingFaq[],
  opts: { editFaqId?: ListingFaqId; edit?: FaqFormState; create?: FaqFormState } = {},
): FastifyReply {
  const edit = faqFormStateOrBlank(opts.edit)
  const create = faqFormStateOrBlank(opts.create)
  const failed = opts.edit !== undefined || opts.create !== undefined

  return reply.code(failed ? 422 : 200).render('faqs/index', {
    title: `Questions & answers — ${listing.title}`,
    listing,
    faqs,
    editFaqId: opts.editFaqId ?? null,
    editFields: edit.fields,
    editErrors: edit.errors,
    editFormError: edit.formError,
    createFields: create.fields,
    createErrors: create.errors,
    createFormError: create.formError,
  })
}

/** The message thread a "Publish as FAQ" submission named, carrying that
 * submission's refusal — null when the thread is gone, which the caller
 * answers with the same 404 a stale url would get. */
async function renderThreadFaqError(
  request: FastifyRequest,
  reply: FastifyReply,
  conversationId: ConversationId,
  state: FaqFormState,
): Promise<FastifyReply> {
  const { db } = request.server
  const actor = { type: 'seller' as const, id: currentSellerId(request) }
  const thread = await conversationThread({ db }, { conversationId, actor })
  if (thread === null) return sellerNotFound(reply)

  return reply.code(422).render('messages/show', {
    title: thread.topic,
    thread,
    formatDateTime,
    faqPrefill: {
      question: state.fields.question ?? '',
      answer: state.fields.answer ?? '',
      sourceMessageId: faqPrefill(thread.messages).sourceMessageId,
    },
    faqErrors: state.errors,
    faqFormError: state.formError ?? null,
  })
}

/** A refused "Publish as FAQ" submission, on the page it came from: the
 * message thread when the form carried the conversation it was posted from,
 * the FAQ index otherwise. */
async function refusePublish(
  request: FastifyRequest,
  reply: FastifyReply,
  listing: Listing,
  conversationId: ConversationId | undefined,
  state: FaqFormState,
): Promise<FastifyReply> {
  if (conversationId !== undefined) return renderThreadFaqError(request, reply, conversationId, state)

  const faqs = await listingFaqs({ db: request.server.db }, listing.id)

  return renderFaqsIndex(reply, listing, faqs, { create: state })
}

export const faqsRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/listings/:id/faqs', { schema: { params: idParams('lst') } }, async (request, reply) => {
    const listingId = request.params.id
    const { db } = request.server
    const listing = await ownedListing(db, currentSellerId(request), listingId)
    if (listing === null) return sellerNotFound(reply)

    const faqs = await listingFaqs({ db }, listingId)

    return renderFaqsIndex(reply, listing, faqs)
  })

  portal.post(
    '/listings/:id/faqs',
    { schema: { params: idParams('lst'), body: faqForm } },
    async (request, reply) => {
      const listingId = request.params.id
      const { db } = request.server
      const listing = await ownedListing(db, currentSellerId(request), listingId)
      if (listing === null) return sellerNotFound(reply)

      const submitted = request.body
      const destination = faqsDestination(request, listingId, submitted.redirect_to)

      const draft = parseFaqDraft(submitted)
      if (!draft.ok) {
        return refusePublish(request, reply, listing, submitted.conversation_id, {
          fields: submitted,
          errors: draft.errors,
        })
      }

      const published = await publishListingFaq(requestActions(request), {
        listingId,
        draft: draft.value,
        sourceMessageId: submitted.source_message_id,
      })
      if (published.outcome === 'refused') {
        return refusePublish(request, reply, listing, submitted.conversation_id, {
          fields: submitted,
          errors: {},
          formError: 'That question is already published to the listing.',
        })
      }

      reply.setFlash({ notice: 'Published to the listing.' })

      return reply.redirect(destination)
    },
  )

  portal.post(
    '/listings/:id/faqs/:faqId',
    { schema: { params: faqParams, body: faqForm } },
    async (request, reply) => {
      const { id: listingId, faqId } = request.params
      const { db } = request.server
      const listing = await ownedListing(db, currentSellerId(request), listingId)
      if (listing === null) return sellerNotFound(reply)

      const faq = await findListingFaq({ db }, { listingId, faqId })
      if (faq === null) return sellerNotFound(reply)

      const submitted = request.body
      const destination = faqsDestination(request, listingId, submitted.redirect_to)

      const draft = parseFaqDraft(submitted)
      if (!draft.ok) {
        const faqs = await listingFaqs({ db }, listingId)

        return renderFaqsIndex(reply, listing, faqs, {
          editFaqId: faqId,
          edit: { fields: submitted, errors: draft.errors },
        })
      }

      await updateListingFaq(requestActions(request), { faqId: faq.id, draft: draft.value })

      reply.setFlash({ notice: 'FAQ updated.' })

      return reply.redirect(destination)
    },
  )

  portal.post(
    '/listings/:id/faqs/:faqId/unpublish',
    { schema: { params: faqParams } },
    async (request, reply) => {
      const { id: listingId, faqId } = request.params
      const { db } = request.server
      const listing = await ownedListing(db, currentSellerId(request), listingId)
      if (listing === null) return sellerNotFound(reply)

      const faq = await findListingFaq({ db }, { listingId, faqId })
      if (faq === null) return sellerNotFound(reply)

      await unpublishListingFaq(requestActions(request), faq.id)

      reply.setFlash({ notice: 'Unpublished.' })

      return reply.redirect(`/seller/listings/${listingId}/faqs`)
    },
  )

  done()
}
