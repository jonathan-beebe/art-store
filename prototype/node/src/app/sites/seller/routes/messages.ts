import { z } from 'zod'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { conversationThread, type ConversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openSupportConversation } from '../../../actions/messaging/open-support-conversation.ts'
import { messagePostRefusalCopy, postMessage } from '../../../actions/messaging/post-message.ts'
import type { ConversationId } from '../../../core/ids/entity-ids.ts'
import { faqPrefill } from '../../../core/messaging/faq-prefill.ts'
import { messageBodyError } from '../../../core/messaging/message-body.ts'
import type { FastifyReply } from 'fastify'
import { requestActions } from '../../../http/request-actions.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { rateLimitGuard } from '../../../plugins/rate-limit.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'

const replyForm = submittedForm({ body: z.string().optional() })

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

  return rendered.render('messages/show', {
    title: thread.topic,
    thread,
    formatDateTime,
    faqPrefill: faqPrefill(thread.messages),
    replyBody: state.body ?? '',
    replyError: state.error,
    replyFormError: state.formError,
  })
}

/** The reply body a tripped `message_post` submitted, read the same way the
 * route's own schema would — `onTrip` runs before the route handler ever
 * sees a typed body of its own. */
function submittedReplyBody(body: unknown): string {
  const parsed = replyForm.safeParse(body)

  return parsed.success ? (parsed.data.body ?? '') : ''
}

const guardMessagePost = rateLimitGuard<{ id: ConversationId }>({
  name: 'message_post',
  key: currentSellerId,
  onTrip: (request) => async (reply, message) => {
    const actor = { type: 'seller' as const, id: currentSellerId(request) }
    const thread = await conversationThread({ db: request.server.db }, { conversationId: request.params.id, actor })
    if (thread === null) return sellerNotFound(reply)

    return renderThread(reply, thread, { body: submittedReplyBody(request.body), formError: message })
  },
})
const guardConversationOpen = rateLimitGuard({ name: 'conversation_open', key: currentSellerId })

export const messagesRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/messages', async (request, reply) => {
    const { db } = request.server
    const conversations = await inboxConversations(
      { db },
      { type: 'seller', id: currentSellerId(request) },
    )

    return reply.render('messages/index', { title: 'Messages', conversations })
  })

  portal.get('/messages/:id', { schema: { params: idParams('cnv') } }, async (request, reply) => {
    const conversationId = request.params.id
    const { db, clock } = request.server
    const actor = { type: 'seller' as const, id: currentSellerId(request) }
    const thread = await conversationThread({ db }, { conversationId, actor })
    if (thread === null) return sellerNotFound(reply)

    await markConversationRead({ db, clock }, { conversationId, reader: actor })

    return renderThread(reply, thread)
  })

  portal.post(
    '/messages/:id',
    { schema: { params: idParams('cnv'), body: replyForm }, preHandler: guardMessagePost },
    async (request, reply) => {
      const conversationId = request.params.id
      const { db } = request.server
      const actor = { type: 'seller' as const, id: currentSellerId(request) }
      const thread = await conversationThread({ db }, { conversationId, actor })
      if (thread === null) return sellerNotFound(reply)

      const { body } = request.body
      const bodyError = messageBodyError(body)
      if (bodyError !== null) {
        return renderThread(reply, thread, { body: body ?? '', error: bodyError }, 422)
      }

      const posted = await postMessage(requestActions(request), { conversationId, sender: actor, body: body ?? '' })
      if (posted.outcome === 'refused') {
        return renderThread(reply, thread, { body: body ?? '', formError: messagePostRefusalCopy(posted.reason, body) }, 422)
      }

      return reply.redirect(`/seller/messages/${conversationId}`)
    },
  )

  portal.get('/support', { preHandler: guardConversationOpen }, async (request, reply) => {
    const result = await openSupportConversation(requestActions(request), {
      actorType: 'seller',
      actorId: currentSellerId(request),
    })

    if (result.outcome === 'no-admin') {
      reply.setFlash({ alert: 'No admin is available to message yet.' })

      return reply.redirect('/seller')
    }

    return reply.redirect(`/seller/messages/${result.conversation.id}`)
  })

  done()
}
