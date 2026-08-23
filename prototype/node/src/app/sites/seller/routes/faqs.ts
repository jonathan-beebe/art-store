import type { FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { findListingFaq, listingFaqs } from '../../../actions/messaging/listing-faqs.ts'
import { publishListingFaq } from '../../../actions/messaging/publish-listing-faq.ts'
import { unpublishListingFaq } from '../../../actions/messaging/unpublish-listing-faq.ts'
import { updateListingFaq } from '../../../actions/messaging/update-listing-faq.ts'
import { resolveLocalRedirect } from '../../../core/auth/local-redirect.ts'
import { parseFaqDraft, type FaqDraftErrors } from '../../../core/messaging/faq-draft.ts'
import { idParams, idValue, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestOrigin } from '../../auth/request-origin.ts'
import { currentSellerId } from '../current-seller.ts'
import { sellerNotFound } from '../not-found.ts'
import { ownedListing } from '../queries/listings.ts'

const faqParams = z.object({ id: idValue, faqId: idValue })

const faqForm = submittedForm({
  question: z.string().optional(),
  answer: z.string().optional(),
  source_message_id: idValue.optional(),
  redirect_to: z.string().optional(),
})

/** The first thing wrong with the submission, on the page it came from. */
function refuseFaq(reply: FastifyReply, destination: string, errors: FaqDraftErrors): FastifyReply {
  reply.setFlash({ alert: Object.values(errors)[0] })

  return reply.redirect(destination)
}

function faqsDestination(
  request: FastifyRequest,
  listingId: number,
  redirectTo: string | undefined,
): string {
  return resolveLocalRedirect(redirectTo, {
    fallback: `/seller/listings/${listingId}/faqs`,
    origin: requestOrigin(request),
  })
}

export const faqsRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/listings/:id/faqs', { schema: { params: idParams } }, async (request, reply) => {
    const listingId = request.params.id
    const { db } = request.server
    const listing = await ownedListing(db, currentSellerId(request), listingId)
    if (listing === null) return sellerNotFound(reply)

    const faqs = await listingFaqs({ db }, listingId)

    return reply.render('faqs/index', {
      title: `Questions & answers — ${listing.title}`,
      listing,
      faqs,
    })
  })

  portal.post(
    '/listings/:id/faqs',
    { schema: { params: idParams, body: faqForm } },
    async (request, reply) => {
      const listingId = request.params.id
      const { db, clock } = request.server
      const listing = await ownedListing(db, currentSellerId(request), listingId)
      if (listing === null) return sellerNotFound(reply)

      const submitted = request.body
      const destination = faqsDestination(request, listingId, submitted.redirect_to)

      const draft = parseFaqDraft(submitted)
      if (!draft.ok) return refuseFaq(reply, destination, draft.errors)

      await publishListingFaq(
        { db, clock },
        { listingId, draft: draft.value, sourceMessageId: submitted.source_message_id },
      )

      reply.setFlash({ notice: 'Published to the listing.' })

      return reply.redirect(destination)
    },
  )

  portal.post(
    '/listings/:id/faqs/:faqId',
    { schema: { params: faqParams, body: faqForm } },
    async (request, reply) => {
      const { id: listingId, faqId } = request.params
      const { db, clock } = request.server
      const listing = await ownedListing(db, currentSellerId(request), listingId)
      if (listing === null) return sellerNotFound(reply)

      const faq = await findListingFaq({ db }, { listingId, faqId })
      if (faq === null) return sellerNotFound(reply)

      const submitted = request.body
      const destination = faqsDestination(request, listingId, submitted.redirect_to)

      const draft = parseFaqDraft(submitted)
      if (!draft.ok) return refuseFaq(reply, destination, draft.errors)

      await updateListingFaq({ db, clock }, { faqId: faq.id, draft: draft.value })

      reply.setFlash({ notice: 'FAQ updated.' })

      return reply.redirect(destination)
    },
  )

  portal.post(
    '/listings/:id/faqs/:faqId/unpublish',
    { schema: { params: faqParams } },
    async (request, reply) => {
      const { id: listingId, faqId } = request.params
      const { db, clock } = request.server
      const listing = await ownedListing(db, currentSellerId(request), listingId)
      if (listing === null) return sellerNotFound(reply)

      const faq = await findListingFaq({ db }, { listingId, faqId })
      if (faq === null) return sellerNotFound(reply)

      await unpublishListingFaq({ db, clock }, faq.id)

      reply.setFlash({ notice: 'Unpublished.' })

      return reply.redirect(`/seller/listings/${listingId}/faqs`)
    },
  )

  done()
}
