import { z } from 'zod'
import type { MessagingActor } from '../../../actions/messaging/conversation-actor.ts'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { conversationThread, type ConversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { openSupportConversation } from '../../../actions/messaging/open-support-conversation.ts'
import {
  messagePostRefusalCopy,
  postMessage,
  type PostMessageRefusalReason,
} from '../../../actions/messaging/post-message.ts'
import { runInTransaction } from '../../../actions/transaction.ts'
import type {
  ConversationId,
  FulfillmentId,
  OrderId,
  SellerId,
} from '../../../core/ids/entity-ids.ts'
import { messageBodyError } from '../../../core/messaging/message-body.ts'
import type { Conversation } from '../../../db/commerce-schema.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { idParams, idValue, slugParams, submittedForm } from '../../../http/request-schema.ts'
import { requestActions } from '../../../http/request-actions.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import type { FastifyReply, FastifyRequest } from 'fastify'
import { rateLimitGuard, type RateLimitedFormRender } from '../../../plugins/rate-limit.ts'
import { loadCustomerOrder } from '../customer-order.ts'
import { renderListingPage } from '../listing-page.ts'
import { renderOrderPage } from '../order-page.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const fulfillmentParams = z.object({ id: idValue('ord'), fulfillmentId: idValue('ful') })
const replyForm = submittedForm({ body: z.string().optional() })
const questionForm = submittedForm({ body: z.string().optional() })

/** Thrown inside the `POST /art/:slug/questions` transaction to roll back a
 * listing question's conversation open when the first message posted into it
 * is refused. Carries the refusal's reason out to the catch that renders it. */
class FirstMessageRefused extends Error {
  readonly reason: PostMessageRefusalReason

  constructor(reason: PostMessageRefusalReason) {
    super(`first message refused: ${reason}`)
    this.name = 'FirstMessageRefused'
    this.reason = reason
  }
}

/** What the reply form on a thread page shows back: the body as typed, a
 * field error for it, or a field-less refusal for the shared slot. */
type ThreadReplyState = { body?: string; error?: string; formError?: string }

function renderThread(
  reply: FastifyReply,
  thread: ConversationThread,
  state: ThreadReplyState = {},
  status?: number,
): FastifyReply {
  const rendered = status === undefined ? reply : reply.code(status)

  return rendered.render(
    'message-thread',
    shopPage({
      title: thread.topic,
      thread,
      replyBody: state.body ?? '',
      replyError: state.error,
      replyFormError: state.formError,
    }),
  )
}

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

/** The body a tripped `message_post` submitted, read the same way the route's
 * own schema would — `onTrip` runs before the route handler ever sees a typed
 * body of its own. Both `replyForm` and `questionForm` share this shape. */
function submittedBody(body: unknown): string {
  const parsed = replyForm.safeParse(body)

  return parsed.success ? (parsed.data.body ?? '') : ''
}

/** Every guard below is keyed by the storefront customer, which
 * `resolveCustomerIdentity` puts on every request under this plugin. */
const guardThreadMessagePost = rateLimitGuard<{ id: ConversationId }>({
  name: 'message_post',
  key: (request) => storefrontCustomer(request).id,
  onTrip: (request) => async (reply, message) => {
    const actor: MessagingActor = { type: 'customer', id: storefrontCustomer(request).id }
    const thread = await conversationThread(requestActions(request), { conversationId: request.params.id, actor })
    if (thread === null) return renderNotFound(reply)

    return renderThread(reply, thread, { body: submittedBody(request.body), formError: message })
  },
})

/** The listing page's own re-render for a tripped limit on `/art/:slug/questions`
 * — shared by both limits that guard the route, so either one's trip lands the
 * visitor back on the same page with the same question kept. */
function questionPageOnTrip(
  request: FastifyRequest & { params: { slug: string } },
): RateLimitedFormRender {
  return async (reply, message) =>
    renderListingPage(request.server, request, reply, request.params.slug, {
      questionBody: submittedBody(request.body),
      questionFormError: message,
    })
}

const guardQuestionMessagePost = rateLimitGuard<{ slug: string }>({
  name: 'message_post',
  key: (request) => storefrontCustomer(request).id,
  onTrip: questionPageOnTrip,
})

/** `conversation_open`'s guard where a trip has nowhere of its own to answer
 * on — `GET /support` redirects on success and carries no form to re-render,
 * so a trip here keeps the shared 429 page. */
const guardConversationOpen = rateLimitGuard({
  name: 'conversation_open',
  key: (request) => storefrontCustomer(request).id,
})

/** `conversation_open`'s guard for `POST /art/:slug/questions`, whose trip
 * must land the visitor back on the listing page next to the question form,
 * the same place `guardQuestionMessagePost`'s own trip on this route does. */
const guardConversationOpenForQuestion = rateLimitGuard<{ slug: string }>({
  name: 'conversation_open',
  key: (request) => storefrontCustomer(request).id,
  onTrip: questionPageOnTrip,
})

/** `conversation_open`'s guard for `POST /orders/:id/fulfillments/:fulfillmentId/messages`,
 * whose trip must land the visitor back on the order page, beside the
 * "Message the seller" form that tripped it. */
const guardConversationOpenForFulfillmentMessage = rateLimitGuard<{
  id: OrderId
  fulfillmentId: FulfillmentId
}>({
  name: 'conversation_open',
  key: (request) => storefrontCustomer(request).id,
  onTrip: (request) => async (reply, message) =>
    renderOrderPage(request.server, request, reply, request.params.id, {
      formError: message,
      formErrorFulfillmentId: request.params.fulfillmentId,
    }),
})

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

    return renderThread(reply, thread)
  })

  shop.post(
    '/messages/:id',
    { schema: { params: idParams('cnv'), body: replyForm }, preHandler: guardThreadMessagePost },
    async (request, reply) => {
      const conversationId = request.params.id
      const actor: MessagingActor = { type: 'customer', id: storefrontCustomer(request).id }
      const thread = await conversationThread(requestActions(request), { conversationId, actor })
      if (thread === null) return renderNotFound(reply)

      const { body } = request.body
      const bodyError = messageBodyError(body)
      if (bodyError !== null) {
        return renderThread(reply, thread, { body: body ?? '', error: bodyError }, 422)
      }

      const posted = await postMessage(requestActions(request), { conversationId, sender: actor, body: body ?? '' })
      if (posted.outcome === 'refused') {
        return renderThread(reply, thread, { body: body ?? '', formError: messagePostRefusalCopy(posted.reason, body) }, 422)
      }

      return await reply.redirect(`/messages/${conversationId}`)
    },
  )

  shop.post(
    '/art/:slug/questions',
    {
      schema: { params: slugParams, body: questionForm },
      preHandler: [guardConversationOpenForQuestion, guardQuestionMessagePost],
    },
    async (request, reply) => {
      const { slug } = request.params
      const found = await findListingOnStorefront(shop.db, slug)
      if (found === null) return renderNotFound(reply)

      const customer = storefrontCustomer(request)
      const { body } = request.body
      const bodyError = messageBodyError(body)
      if (bodyError !== null) {
        return renderListingPage(shop, request, reply, slug, { questionBody: body ?? '', questionError: bodyError }, 422)
      }

      let conversation: Conversation
      try {
        // A refused first message must leave no conversation behind, so the open
        // and the post run as one transaction. Kysely rolls a transaction back
        // only on a throw, and `postMessage` returns its refusal rather than
        // throwing one, so the refusal is re-thrown as this sentinel to drive
        // the rollback and is unwrapped again once it escapes the transaction.
        conversation = await runInTransaction(requestActions(request), async (transacted) => {
          const opened = await openConversation(transacted, {
            kind: 'listing_question',
            sellerId: found.listing.sellerId,
            customerId: customer.id,
            listingId: found.listing.id,
          })

          const posted = await postMessage(transacted, {
            conversationId: opened.id,
            sender: { type: 'customer', id: customer.id },
            body: body ?? '',
          })
          if (posted.outcome === 'refused') throw new FirstMessageRefused(posted.reason)

          return opened
        })
      } catch (error) {
        if (!(error instanceof FirstMessageRefused)) throw error

        return renderListingPage(
          shop,
          request,
          reply,
          slug,
          { questionBody: body ?? '', questionFormError: messagePostRefusalCopy(error.reason, body) },
          422,
        )
      }

      reply.setFlash({ notice: 'Your question has been sent to the seller.' })

      return await reply.redirect(`/messages/${conversation.id}`)
    },
  )

  shop.get('/support', { preHandler: guardConversationOpen }, async (request, reply) => {
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
    { schema: { params: fulfillmentParams }, preHandler: guardConversationOpenForFulfillmentMessage },
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
