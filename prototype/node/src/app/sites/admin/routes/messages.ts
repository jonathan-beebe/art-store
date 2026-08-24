import type { FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { conversationThread, type ConversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import type { AdminId, ConversationId } from '../../../core/ids/entity-ids.ts'
import { messageBodyError } from '../../../core/messaging/message-body.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { requestActions } from '../../../http/request-actions.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { rateLimitGuard } from '../../../plugins/rate-limit.ts'
import { adminPage } from '../page.ts'
import { customerDetail } from '../queries/customer-detail.ts'
import { sellerDetail } from '../queries/seller-detail.ts'

const replyForm = submittedForm({ body: z.string().optional() })

/** `requireAdmin` guards this whole plugin, so this only narrows the type. */
function currentAdminId(request: FastifyRequest): AdminId {
  const { currentAdmin } = request

  if (currentAdmin === null) throw new Error('a messages route needs a signed-in admin')

  return currentAdmin.id
}

function adminActor(request: FastifyRequest): { type: 'admin'; id: AdminId } {
  return { type: 'admin', id: currentAdminId(request) }
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
    'message',
    adminPage(thread.topic, {
      thread,
      replyBody: state.body ?? '',
      replyError: state.error,
      replyFormError: state.formError,
    }),
  )
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
  key: currentAdminId,
  onTrip: (request) => async (reply, message) => {
    const context = requestActions(request)
    const thread = await conversationThread(context, { conversationId: request.params.id, actor: adminActor(request) })
    if (thread === null) {
      reply.callNotFound()

      return reply
    }

    return renderThread(reply, thread, { body: submittedReplyBody(request.body), formError: message })
  },
})

export const messageRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/messages', async (request, reply) => {
    const conversations = await inboxConversations(requestActions(request), adminActor(request))

    return reply.render('messages', adminPage('Messages', { conversations }))
  })

  admin.get('/messages/:id', { schema: { params: idParams('cnv') } }, async (request, reply) => {
    const conversationId = request.params.id
    const context = requestActions(request)
    const actor = adminActor(request)
    const thread = await conversationThread(context, { conversationId, actor })
    if (thread === null) return reply.callNotFound()

    await markConversationRead(context, { conversationId, reader: actor })

    return renderThread(reply, thread)
  })

  admin.post(
    '/messages/:id',
    { schema: { params: idParams('cnv'), body: replyForm }, preHandler: guardMessagePost },
    async (request, reply) => {
      const conversationId = request.params.id
      const context = requestActions(request)
      const actor = adminActor(request)
      const thread = await conversationThread(context, { conversationId, actor })
      if (thread === null) return reply.callNotFound()

      const { body } = request.body
      const bodyError = messageBodyError(body)
      if (bodyError !== null) {
        return renderThread(reply, thread, { body: body ?? '', error: bodyError }, 422)
      }

      try {
        await postMessage(context, { conversationId, sender: actor, body: body ?? '' })
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error

        return renderThread(reply, thread, { body: body ?? '', formError: error.message }, 422)
      }

      return reply.redirect(`/admin/messages/${conversationId}`)
    },
  )

  admin.post('/sellers/:id/messages', { schema: { params: idParams('sel') } }, async (request, reply) => {
    const sellerId = request.params.id
    const context = requestActions(request)
    const detail = await sellerDetail(context, sellerId)
    if (detail === null) return reply.callNotFound()

    const conversation = await openConversation(context, {
      kind: 'admin_seller',
      adminId: currentAdminId(request),
      sellerId,
    })

    return reply.redirect(`/admin/messages/${conversation.id}`)
  })

  admin.post('/customers/:id/messages', { schema: { params: idParams('cus') } }, async (request, reply) => {
    const customerId = request.params.id
    const context = requestActions(request)
    const detail = await customerDetail(context, customerId)
    if (detail === null) return reply.callNotFound()

    const conversation = await openConversation(context, {
      kind: 'admin_customer',
      adminId: currentAdminId(request),
      customerId,
    })

    return reply.redirect(`/admin/messages/${conversation.id}`)
  })

  done()
}
