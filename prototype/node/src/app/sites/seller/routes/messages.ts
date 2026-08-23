import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { conversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openSupportConversation } from '../../../actions/messaging/open-support-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import { faqPrefill } from '../../../core/messaging/faq-prefill.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import { parseIdParam } from '../../../plugins/id-param.ts'

const replyForm = z.object({ body: z.string() })

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db } = request.server
  const conversations = await inboxConversations({ db }, { type: 'seller', id: currentSellerId(request) })

  return reply.render('messages/index', { title: 'Messages', conversations })
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db, clock } = request.server
  const actor = { type: 'seller' as const, id: currentSellerId(request) }
  const thread = await conversationThread({ db }, { conversationId: id, actor })
  if (thread === null) return sellerNotFound(reply)

  await markConversationRead({ db, clock }, { conversationId: id, reader: actor })

  return reply.render('messages/show', {
    title: thread.topic,
    thread,
    formatDateTime,
    faqPrefill: faqPrefill(thread.messages),
  })
}

async function postReply(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db, clock } = request.server
  const actor = { type: 'seller' as const, id: currentSellerId(request) }
  const thread = await conversationThread({ db }, { conversationId: id, actor })
  if (thread === null) return sellerNotFound(reply)

  const parsed = replyForm.safeParse(request.body)
  if (!parsed.success) {
    reply.setFlash({ alert: 'Write a message before sending.' })
    return reply.redirect(`/seller/messages/${id}`)
  }

  try {
    await postMessage({ db, clock }, { conversationId: id, sender: actor, body: parsed.data.body })
  } catch (error) {
    if (!(error instanceof TransitionError)) throw error
    reply.setFlash({ alert: error.message })
  }

  return reply.redirect(`/seller/messages/${id}`)
}

async function support(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db, clock } = request.server
  const result = await openSupportConversation({ db, clock }, { actorType: 'seller', actorId: currentSellerId(request) })

  if (result.outcome === 'no-admin') {
    reply.setFlash({ alert: 'No admin is available to message yet.' })
    return reply.redirect('/seller')
  }

  return reply.redirect(`/seller/messages/${result.conversation.id}`)
}

export const messagesRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/messages', index)
  portal.get('/messages/:id', show)
  portal.post('/messages/:id', postReply)
  portal.get('/support', support)

  done()
}
