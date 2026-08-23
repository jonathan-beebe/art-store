import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { conversationThread, type ConversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import { parseIdParam } from '../../../plugins/id-param.ts'

const replyForm = z.object({ body: z.string() })

type FaqPrefill = { question: string; answer: string; sourceMessageId: number | null }

function faqPrefillFrom(thread: ConversationThread): FaqPrefill {
  const first = thread.messages[0]
  const lastFromSeller = [...thread.messages].reverse().find((message) => message.isMine)

  return {
    question: first?.body ?? '',
    answer: lastFromSeller?.body ?? '',
    sourceMessageId: lastFromSeller?.id ?? null,
  }
}

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
    faqPrefill: faqPrefillFrom(thread),
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
  const admin = await db.selectFrom('admins').select('id').orderBy('id').limit(1).executeTakeFirst()
  if (admin === undefined) {
    reply.setFlash({ alert: 'No admin is available to message yet.' })
    return reply.redirect('/seller')
  }

  const conversation = await openConversation(
    { db, clock },
    { kind: 'admin_seller', sellerId: currentSellerId(request), adminId: admin.id },
  )

  return reply.redirect(`/seller/messages/${conversation.id}`)
}

export const messagesRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/messages', index)
  portal.get('/messages/:id', show)
  portal.post('/messages/:id', postReply)
  portal.get('/support', support)

  done()
}
