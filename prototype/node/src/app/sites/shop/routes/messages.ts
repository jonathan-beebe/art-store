import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import type { MessagingActor } from '../../../actions/messaging/conversation-actor.ts'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { conversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { loadCustomerOrder } from '../customer-order.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const threadParameters = z.object({ id: z.coerce.number().int().positive() })
const listingParameters = z.object({ slug: z.string() })
const fulfillmentMessageParameters = z.object({ fulfillmentId: z.coerce.number().int().positive() })
const replyBody = z.object({ body: z.string() })
const questionBody = z.object({ body: z.string().catch('') })

/**
 * The seller behind one fulfillment. `loadCustomerOrder`'s own view of a
 * fulfillment leaves this column out, so opening its thread reads it directly.
 */
async function fulfillmentSellerId(db: AppDatabase, fulfillmentId: number): Promise<number | null> {
  const row = await db
    .selectFrom('fulfillments')
    .select('sellerId')
    .where('id', '=', fulfillmentId)
    .executeTakeFirst()

  return row?.sellerId ?? null
}

export const messageRoutes: FastifyPluginCallback = (shop, _options, done) => {
  const context = { db: shop.db, clock: shop.clock }

  shop.get('/messages', async (request, reply) => {
    const customer = storefrontCustomer(request)
    const conversations = await inboxConversations(context, { type: 'customer', id: customer.id })

    return reply.render('messages', shopPage({ title: 'Messages', conversations }))
  })

  shop.get('/messages/:id', async (request, reply) => {
    const asked = threadParameters.safeParse(request.params)
    if (!asked.success) return renderNotFound(reply)

    const actor: MessagingActor = { type: 'customer', id: storefrontCustomer(request).id }
    const thread = await conversationThread(context, { conversationId: asked.data.id, actor })
    if (thread === null) return renderNotFound(reply)

    await markConversationRead(context, { conversationId: thread.conversation.id, reader: actor })

    return reply.render('message-thread', shopPage({ title: thread.topic, thread }))
  })

  shop.post('/messages/:id', async (request, reply) => {
    const asked = threadParameters.safeParse(request.params)
    if (!asked.success) return renderNotFound(reply)

    const actor: MessagingActor = { type: 'customer', id: storefrontCustomer(request).id }
    const thread = await conversationThread(context, { conversationId: asked.data.id, actor })
    if (thread === null) return renderNotFound(reply)

    const submitted = replyBody.safeParse(request.body)
    if (!submitted.success) {
      reply.setFlash({ alert: 'Write a message before sending.' })
      return await reply.redirect(`/messages/${asked.data.id}`)
    }

    try {
      await postMessage(context, {
        conversationId: asked.data.id,
        sender: actor,
        body: submitted.data.body,
      })
    } catch (error) {
      if (!(error instanceof TransitionError)) throw error
      reply.setFlash({ alert: error.message })
    }

    return await reply.redirect(`/messages/${asked.data.id}`)
  })

  shop.post('/art/:slug/questions', async (request, reply) => {
    const { slug } = listingParameters.parse(request.params)
    const found = await findListingOnStorefront(shop.db, slug)
    if (found === null) return renderNotFound(reply)

    const customer = storefrontCustomer(request)
    const conversation = await openConversation(context, {
      kind: 'listing_question',
      sellerId: found.listing.sellerId,
      customerId: customer.id,
      listingId: found.listing.id,
    })

    try {
      await postMessage(context, {
        conversationId: conversation.id,
        sender: { type: 'customer', id: customer.id },
        body: questionBody.parse(request.body).body,
      })
    } catch (error) {
      if (!(error instanceof TransitionError)) throw error
      reply.setFlash({ alert: error.message })
      return await reply.redirect(`/art/${slug}`)
    }

    reply.setFlash({ notice: 'Your question has been sent to the seller.' })
    return await reply.redirect(`/messages/${conversation.id}`)
  })

  shop.get('/support', async (request, reply) => {
    const admin = await shop.db
      .selectFrom('admins')
      .select('id')
      .orderBy('id')
      .limit(1)
      .executeTakeFirst()
    if (admin === undefined) {
      reply.setFlash({ alert: 'Support is not available right now.' })
      return await reply.redirect('/account')
    }

    const customer = storefrontCustomer(request)
    const conversation = await openConversation(context, {
      kind: 'admin_customer',
      customerId: customer.id,
      adminId: admin.id,
    })

    return await reply.redirect(`/messages/${conversation.id}`)
  })

  shop.post('/orders/:id/fulfillments/:fulfillmentId/messages', async (request, reply) => {
    const found = await loadCustomerOrder(shop, request)
    const asked = fulfillmentMessageParameters.safeParse(request.params)
    if (found === null || !asked.success) return renderNotFound(reply)

    const fulfillment = found.fulfillments.find(
      (candidate) => candidate.id === asked.data.fulfillmentId,
    )
    if (fulfillment === undefined) return renderNotFound(reply)

    const sellerId = await fulfillmentSellerId(shop.db, fulfillment.id)
    if (sellerId === null) return renderNotFound(reply)

    const customer = storefrontCustomer(request)
    const conversation = await openConversation(context, {
      kind: 'fulfillment',
      sellerId,
      customerId: customer.id,
      fulfillmentId: fulfillment.id,
    })

    return await reply.redirect(`/messages/${conversation.id}`)
  })

  done()
}
