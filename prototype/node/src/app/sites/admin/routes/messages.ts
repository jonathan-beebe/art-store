import type { FastifyRequest } from 'fastify'
import { z } from 'zod'
import type { ActionContext } from '../../../actions/action-context.ts'
import { conversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import type { AdminId } from '../../../core/ids/entity-ids.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { customerDetail } from '../queries/customer-detail.ts'
import { sellerDetail } from '../queries/seller-detail.ts'

const replyForm = submittedForm({ body: z.string().optional() })

function actionContext(request: FastifyRequest): ActionContext {
  return { db: request.server.db, clock: request.server.clock }
}

/** `requireAdmin` guards this whole plugin, so this only narrows the type. */
function currentAdminId(request: FastifyRequest): AdminId {
  const { currentAdmin } = request

  if (currentAdmin === null) throw new Error('a messages route needs a signed-in admin')

  return currentAdmin.id
}

function adminActor(request: FastifyRequest): { type: 'admin'; id: AdminId } {
  return { type: 'admin', id: currentAdminId(request) }
}

export const messageRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/messages', async (request, reply) => {
    const conversations = await inboxConversations(actionContext(request), adminActor(request))

    return reply.render('messages', adminPage('Messages', { conversations }))
  })

  admin.get('/messages/:id', { schema: { params: idParams('cnv') } }, async (request, reply) => {
    const conversationId = request.params.id
    const context = actionContext(request)
    const actor = adminActor(request)
    const thread = await conversationThread(context, { conversationId, actor })
    if (thread === null) return reply.callNotFound()

    await markConversationRead(context, { conversationId, reader: actor })

    return reply.render('message', adminPage(thread.topic, { thread }))
  })

  admin.post(
    '/messages/:id',
    { schema: { params: idParams('cnv'), body: replyForm } },
    async (request, reply) => {
      const conversationId = request.params.id
      const context = actionContext(request)
      const actor = adminActor(request)
      const thread = await conversationThread(context, { conversationId, actor })
      if (thread === null) return reply.callNotFound()

      const destination = `/admin/messages/${conversationId}`
      const { body } = request.body
      if (body === undefined) {
        reply.setFlash({ alert: 'Write a message before sending.' })

        return reply.redirect(destination)
      }

      try {
        await postMessage(context, { conversationId, sender: actor, body })
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error

        reply.setFlash({ alert: error.message })
      }

      return reply.redirect(destination)
    },
  )

  admin.post('/sellers/:id/messages', { schema: { params: idParams('sel') } }, async (request, reply) => {
    const sellerId = request.params.id
    const context = actionContext(request)
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
    const context = actionContext(request)
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
