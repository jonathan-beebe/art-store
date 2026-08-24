import { z } from 'zod'
import type { MessagingActor } from '../../../actions/messaging/conversation-actor.ts'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { conversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { openSupportConversation } from '../../../actions/messaging/open-support-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import { runInTransaction } from '../../../actions/transaction.ts'
import type {
  FulfillmentId,
  SellerId,
} from '../../../core/ids/entity-ids.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import type { Conversation } from '../../../db/commerce-schema.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { idParams, idValue, slugParams, submittedForm } from '../../../http/request-schema.ts'
import { requestActions } from '../../../http/request-actions.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { loadCustomerOrder } from '../customer-order.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const fulfillmentParams = z.object({ id: idValue('ord'), fulfillmentId: idValue('ful') })
const replyForm = submittedForm({ body: z.string().optional() })
// The question box is the first thing a visitor writes to a seller, and an
// empty one is refused by the same rule that refuses an empty reply.
const questionForm = submittedForm({ body: z.string().catch('') })

/**
 * The seller behind one fulfillment. `loadCustomerOrder`'s own view of a
 * fulfillment leaves this column out, so opening its thread reads it directly.
 */
async function fulfillmentSellerId(
  db: AppDatabase,
  fulfillmentId: FulfillmentId,
): Promise<SellerId | null> {
  const row = await db
    .selectFrom('fulfillments')
    .select('sellerId')
    .where('id', '=', fulfillmentId)
    .executeTakeFirst()

  return row?.sellerId ?? null
}

export const messageRoutes: ZodRoutes = (shop, _options, done) => {
  shop.get('/messages', async (request, reply) => {
    const customer = storefrontCustomer(request)
    const conversations = await inboxConversations(requestActions(request), {
      type: 'customer',
      id: customer.id,
    })

    return reply.render('messages', shopPage({ title: 'Messages', conversations }))
  })

  shop.get('/messages/:id', { schema: { params: idParams('cnv') } }, async (request, reply) => {
    const conversationId = request.params.id
    const actor: MessagingActor = { type: 'customer', id: storefrontCustomer(request).id }
    const thread = await conversationThread(requestActions(request), { conversationId, actor })
    if (thread === null) return renderNotFound(reply)

    await markConversationRead(requestActions(request), { conversationId: thread.conversation.id, reader: actor })

    return reply.render('message-thread', shopPage({ title: thread.topic, thread }))
  })

  shop.post(
    '/messages/:id',
    { schema: { params: idParams('cnv'), body: replyForm } },
    async (request, reply) => {
      const conversationId = request.params.id
      const actor: MessagingActor = { type: 'customer', id: storefrontCustomer(request).id }
      const thread = await conversationThread(requestActions(request), { conversationId, actor })
      if (thread === null) return renderNotFound(reply)

      const { body } = request.body
      if (body === undefined) {
        reply.setFlash({ alert: 'Write a message before sending.' })

        return await reply.redirect(`/messages/${conversationId}`)
      }

      try {
        await postMessage(requestActions(request), { conversationId, sender: actor, body })
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error
        reply.setFlash({ alert: error.message })
      }

      return await reply.redirect(`/messages/${conversationId}`)
    },
  )

  shop.post(
    '/art/:slug/questions',
    { schema: { params: slugParams, body: questionForm } },
    async (request, reply) => {
      const { slug } = request.params
      const found = await findListingOnStorefront(shop.db, slug)
      if (found === null) return renderNotFound(reply)

      const customer = storefrontCustomer(request)
      const { body } = request.body

      let conversation: Conversation
      try {
        // A refused first message must leave no conversation behind, so the open
        // and the post run as one transaction: `postMessage`'s `TransitionError`
        // escapes it uncaught here, which is what rolls the open back too.
        conversation = await runInTransaction(requestActions(request), async (transacted) => {
          const opened = await openConversation(transacted, {
            kind: 'listing_question',
            sellerId: found.listing.sellerId,
            customerId: customer.id,
            listingId: found.listing.id,
          })

          await postMessage(transacted, {
            conversationId: opened.id,
            sender: { type: 'customer', id: customer.id },
            body,
          })

          return opened
        })
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error
        reply.setFlash({ alert: error.message })

        return await reply.redirect(`/art/${slug}`)
      }

      reply.setFlash({ notice: 'Your question has been sent to the seller.' })

      return await reply.redirect(`/messages/${conversation.id}`)
    },
  )

  shop.get('/support', async (request, reply) => {
    const customer = storefrontCustomer(request)
    const result = await openSupportConversation(requestActions(request), {
      actorType: 'customer',
      actorId: customer.id,
    })

    if (result.outcome === 'no-admin') {
      reply.setFlash({ alert: 'Support is not available right now.' })

      return await reply.redirect('/account')
    }

    return await reply.redirect(`/messages/${result.conversation.id}`)
  })

  shop.post(
    '/orders/:id/fulfillments/:fulfillmentId/messages',
    { schema: { params: fulfillmentParams } },
    async (request, reply) => {
      const found = await loadCustomerOrder(shop, request, request.params.id)
      if (found === null) return renderNotFound(reply)

      const fulfillment = found.fulfillments.find(
        (candidate) => candidate.id === request.params.fulfillmentId,
      )
      if (fulfillment === undefined) return renderNotFound(reply)

      const sellerId = await fulfillmentSellerId(shop.db, fulfillment.id)
      if (sellerId === null) return renderNotFound(reply)

      const customer = storefrontCustomer(request)
      const conversation = await openConversation(requestActions(request), {
        kind: 'fulfillment',
        sellerId,
        customerId: customer.id,
        fulfillmentId: fulfillment.id,
      })

      return await reply.redirect(`/messages/${conversation.id}`)
    },
  )

  done()
}
