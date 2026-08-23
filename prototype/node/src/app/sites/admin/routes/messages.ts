import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { adminPage } from '../page.ts'
import { customerDetail } from '../queries/customer-detail.ts'
import { sellerDetail } from '../queries/seller-detail.ts'
import { conversationThread } from '../../../actions/messaging/conversation-thread.ts'
import { inboxConversations } from '../../../actions/messaging/conversation-inbox.ts'
import { markConversationRead } from '../../../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import type { ActionContext } from '../../../actions/action-context.ts'
import { TransitionError } from '../../../core/transition-error.ts'

const idParams = z.object({ id: z.coerce.number().int().positive() })
const replyForm = z.object({ body: z.string() })

function parseId(params: unknown): number | null {
  const parsed = idParams.safeParse(params)

  return parsed.success ? parsed.data.id : null
}

function notFound(reply: FastifyReply): FastifyReply {
  return reply.code(404).type('text/plain').send('Not found')
}

function actionContext(request: FastifyRequest): ActionContext {
  return { db: request.server.db, clock: request.server.clock }
}

/** `requireAdmin` guards this whole plugin, so this only narrows the type. */
function currentAdminId(request: FastifyRequest): number {
  const { currentAdmin } = request

  if (currentAdmin === null) throw new Error('a messages route needs a signed-in admin')

  return currentAdmin.id
}

function adminActor(request: FastifyRequest): { type: 'admin'; id: number } {
  return { type: 'admin', id: currentAdminId(request) }
}

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const conversations = await inboxConversations(actionContext(request), adminActor(request))

  return reply.render('messages', adminPage('Messages', { conversations }))
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply | void> {
  const conversationId = parseId(request.params)
  if (conversationId === null) return notFound(reply)

  const context = actionContext(request)
  const actor = adminActor(request)
  const thread = await conversationThread(context, { conversationId, actor })
  if (thread === null) return notFound(reply)

  await markConversationRead(context, { conversationId, reader: actor })

  return reply.render('message', adminPage(thread.topic, { thread }))
}

async function postReply(
  request: FastifyRequest,
  reply: FastifyReply,
): Promise<FastifyReply | void> {
  const conversationId = parseId(request.params)
  if (conversationId === null) return notFound(reply)

  const context = actionContext(request)
  const actor = adminActor(request)
  const thread = await conversationThread(context, { conversationId, actor })
  if (thread === null) return notFound(reply)

  const destination = `/admin/messages/${conversationId}`
  const submitted = replyForm.safeParse(request.body)
  if (!submitted.success) {
    reply.setFlash({ alert: 'Write a message before sending.' })

    return reply.redirect(destination)
  }

  try {
    await postMessage(context, { conversationId, sender: actor, body: submitted.data.body })
  } catch (error) {
    if (!(error instanceof TransitionError)) throw error

    reply.setFlash({ alert: error.message })

    return reply.redirect(destination)
  }

  return reply.redirect(destination)
}

async function messageSeller(
  request: FastifyRequest,
  reply: FastifyReply,
): Promise<FastifyReply | void> {
  const sellerId = parseId(request.params)
  if (sellerId === null) return notFound(reply)

  const context = actionContext(request)
  const detail = await sellerDetail(context, sellerId)
  if (detail === null) return notFound(reply)

  const conversation = await openConversation(context, {
    kind: 'admin_seller',
    adminId: currentAdminId(request),
    sellerId,
  })

  return reply.redirect(`/admin/messages/${conversation.id}`)
}

async function messageCustomer(
  request: FastifyRequest,
  reply: FastifyReply,
): Promise<FastifyReply | void> {
  const customerId = parseId(request.params)
  if (customerId === null) return notFound(reply)

  const context = actionContext(request)
  const detail = await customerDetail(context, customerId)
  if (detail === null) return notFound(reply)

  const conversation = await openConversation(context, {
    kind: 'admin_customer',
    adminId: currentAdminId(request),
    customerId,
  })

  return reply.redirect(`/admin/messages/${conversation.id}`)
}

export const messageRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/messages', index)
  admin.get('/messages/:id', show)
  admin.post('/messages/:id', postReply)
  admin.post('/sellers/:id/messages', messageSeller)
  admin.post('/customers/:id/messages', messageCustomer)

  done()
}
