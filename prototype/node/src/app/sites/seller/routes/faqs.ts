import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { findListingFaq, listingFaqs } from '../../../actions/messaging/listing-faqs.ts'
import { publishListingFaq } from '../../../actions/messaging/publish-listing-faq.ts'
import { unpublishListingFaq } from '../../../actions/messaging/unpublish-listing-faq.ts'
import { updateListingFaq } from '../../../actions/messaging/update-listing-faq.ts'
import { resolveLocalRedirect } from '../../../core/auth/local-redirect.ts'
import { parseFaqDraft, type FaqDraftErrors } from '../../../core/messaging/faq-draft.ts'
import { requestOrigin } from '../../auth/request-origin.ts'
import { formBody } from '../../../plugins/form-body.ts'
import { currentSellerId } from '../current-seller.ts'
import { sellerNotFound } from '../not-found.ts'
import { parseIdParam } from '../../../plugins/id-param.ts'
import { ownedListing } from '../queries/listings.ts'

const faqForm = z.object({
  question: z.string().optional(),
  answer: z.string().optional(),
  source_message_id: z.coerce.number().int().positive().optional(),
  redirect_to: z.string().optional(),
})

type FaqParams = { listingId: number; faqId: number }

function parseFaqParams(params: unknown): FaqParams | null {
  const parsed = z
    .object({ id: z.coerce.number().int().positive(), faqId: z.coerce.number().int().positive() })
    .safeParse(params)

  return parsed.success ? { listingId: parsed.data.id, faqId: parsed.data.faqId } : null
}

/** The first thing wrong with the submission, on the page it came from. */
function refuseFaq(reply: FastifyReply, destination: string, errors: FaqDraftErrors): FastifyReply {
  reply.setFlash({ alert: Object.values(errors)[0] })

  return reply.redirect(destination)
}

function faqsDestination(request: FastifyRequest, listingId: number, redirectTo: string | undefined): string {
  return resolveLocalRedirect(redirectTo, {
    fallback: `/seller/listings/${listingId}/faqs`,
    origin: requestOrigin(request),
  })
}

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db } = request.server
  const listing = await ownedListing(db, currentSellerId(request), id)
  if (listing === null) return sellerNotFound(reply)

  const faqs = await listingFaqs({ db }, id)

  return reply.render('faqs/index', { title: `Questions & answers — ${listing.title}`, listing, faqs })
}

async function publish(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db, clock } = request.server
  const listing = await ownedListing(db, currentSellerId(request), id)
  if (listing === null) return sellerNotFound(reply)

  const submitted = faqForm.parse(formBody(request))
  const destination = faqsDestination(request, id, submitted.redirect_to)

  const draft = parseFaqDraft(submitted)
  if (!draft.ok) return refuseFaq(reply, destination, draft.errors)

  await publishListingFaq(
    { db, clock },
    { listingId: id, draft: draft.value, sourceMessageId: submitted.source_message_id },
  )

  reply.setFlash({ notice: 'Published to the listing.' })
  return reply.redirect(destination)
}

async function update(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const params = parseFaqParams(request.params)
  if (params === null) return sellerNotFound(reply)

  const { db, clock } = request.server
  const listing = await ownedListing(db, currentSellerId(request), params.listingId)
  if (listing === null) return sellerNotFound(reply)

  const faq = await findListingFaq({ db }, params)
  if (faq === null) return sellerNotFound(reply)

  const submitted = faqForm.parse(formBody(request))
  const destination = faqsDestination(request, params.listingId, submitted.redirect_to)

  const draft = parseFaqDraft(submitted)
  if (!draft.ok) return refuseFaq(reply, destination, draft.errors)

  await updateListingFaq({ db, clock }, { faqId: faq.id, draft: draft.value })

  reply.setFlash({ notice: 'FAQ updated.' })
  return reply.redirect(destination)
}

async function unpublish(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const params = parseFaqParams(request.params)
  if (params === null) return sellerNotFound(reply)

  const { db, clock } = request.server
  const listing = await ownedListing(db, currentSellerId(request), params.listingId)
  if (listing === null) return sellerNotFound(reply)

  const faq = await findListingFaq({ db }, params)
  if (faq === null) return sellerNotFound(reply)

  await unpublishListingFaq({ db, clock }, faq.id)

  reply.setFlash({ notice: 'Unpublished.' })
  return reply.redirect(`/seller/listings/${params.listingId}/faqs`)
}

export const faqsRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/listings/:id/faqs', index)
  portal.post('/listings/:id/faqs', publish)
  portal.post('/listings/:id/faqs/:faqId', update)
  portal.post('/listings/:id/faqs/:faqId/unpublish', unpublish)

  done()
}
