import { z } from 'zod'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { conversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openSupportConversation } from '../../../actions/messaging/open-support-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import { faqPrefill } from '../../../core/messaging/faq-prefill.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'

const replyForm = submittedForm({ body: z.string().optional() })

export const messagesRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/messages', async (request, reply) => {
    const { db } = request.server
    const conversations = await inboxConversations(
      { db },
      { type: 'seller', id: currentSellerId(request) },
    )

    return reply.render('messages/index', { title: 'Messages', conversations })
  })

  portal.get('/messages/:id', { schema: { params: idParams } }, async (request, reply) => {
    const conversationId = request.params.id
    const { db, clock } = request.server
    const actor = { type: 'seller' as const, id: currentSellerId(request) }
    const thread = await conversationThread({ db }, { conversationId, actor })
    if (thread === null) return sellerNotFound(reply)

    await markConversationRead({ db, clock }, { conversationId, reader: actor })

    return reply.render('messages/show', {
      title: thread.topic,
      thread,
      formatDateTime,
      faqPrefill: faqPrefill(thread.messages),
    })
  })

  portal.post(
    '/messages/:id',
    { schema: { params: idParams, body: replyForm } },
    async (request, reply) => {
      const conversationId = request.params.id
      const { db, clock } = request.server
      const actor = { type: 'seller' as const, id: currentSellerId(request) }
      const thread = await conversationThread({ db }, { conversationId, actor })
      if (thread === null) return sellerNotFound(reply)

      const { body } = request.body
      if (body === undefined) {
        reply.setFlash({ alert: 'Write a message before sending.' })

        return reply.redirect(`/seller/messages/${conversationId}`)
      }

      try {
        await postMessage({ db, clock }, { conversationId, sender: actor, body })
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error
        reply.setFlash({ alert: error.message })
      }

      return reply.redirect(`/seller/messages/${conversationId}`)
    },
  )

  portal.get('/support', async (request, reply) => {
    const { db, clock } = request.server
    const result = await openSupportConversation(
      { db, clock },
      { actorType: 'seller', actorId: currentSellerId(request) },
    )

    if (result.outcome === 'no-admin') {
      reply.setFlash({ alert: 'No admin is available to message yet.' })

      return reply.redirect('/seller')
    }

    return reply.redirect(`/seller/messages/${result.conversation.id}`)
  })

  done()
}
