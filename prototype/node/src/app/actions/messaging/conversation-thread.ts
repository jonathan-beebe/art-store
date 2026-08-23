import type { ActionContext } from '../action-context.ts'
import { conversationActor, type MessagingActor } from './conversation-actor.ts'
import { counterpartName, participantNames, senderName } from './conversation-participants.ts'
import { conversationTopicOf } from './conversation-topics.ts'
import type { ActorType } from '../../core/auth/actor-type.ts'
import { conversationAccess } from '../../core/messaging/conversation-access.ts'
import { isUnreadBy } from '../../core/messaging/unread-messages.ts'
import type { Conversation, Message } from '../../db/commerce-schema.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { Timestamp } from '../../db/timestamp.ts'

/** One message as a thread page shows it. */
export type ThreadMessage = {
  id: number
  body: string
  sentAt: Timestamp
  senderType: ActorType
  senderName: string
  isMine: boolean
  isUnread: boolean
}

/** What the listing question is about, for the page that offers "Publish as FAQ". */
export type ThreadListing = { id: number; title: string; slug: string }

export type ThreadFulfillment = { id: number; orderId: number }

export type ConversationThread = {
  conversation: Conversation
  topic: string
  counterpart: string
  messages: readonly ThreadMessage[]
  mayPost: boolean
  listing: ThreadListing | null
  fulfillment: ThreadFulfillment | null
}

/**
 * One thread as its participant reads it, or null when the actor is not in it.
 * A conversation somebody else is in and one that never existed answer the
 * same, so no site reveals which it was.
 */
export async function conversationThread(
  context: Pick<ActionContext, 'db'>,
  { conversationId, actor }: { conversationId: number; actor: MessagingActor },
): Promise<ConversationThread | null> {
  const conversation = await context.db
    .selectFrom('conversations')
    .selectAll()
    .where('id', '=', conversationId)
    .executeTakeFirst()
  if (conversation === undefined) return null

  const access = conversationAccess(conversation, await conversationActor(context, actor))
  if (!access.mayRead) return null

  const messages = await context.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .orderBy('id')
    .execute()

  const [topic, names, listing, fulfillment] = await Promise.all([
    conversationTopicOf(context, conversation),
    participantNames(context, [conversation]),
    findThreadListing(context.db, conversation.listingId),
    findThreadFulfillment(context.db, conversation.fulfillmentId),
  ])

  return {
    conversation,
    topic,
    counterpart: counterpartName(conversation, actor, names),
    messages: messages.map((message) => toThreadMessage(message, actor, names)),
    mayPost: access.mayPost,
    listing,
    fulfillment,
  }
}

function toThreadMessage(
  message: Message,
  actor: MessagingActor,
  names: Awaited<ReturnType<typeof participantNames>>,
): ThreadMessage {
  return {
    id: message.id,
    body: message.body,
    sentAt: message.sentAt,
    senderType: message.senderType,
    senderName: senderName(message, names),
    isMine: message.senderType === actor.type && message.senderId === actor.id,
    isUnread: isUnreadBy(message, actor),
  }
}

async function findThreadListing(
  db: AppDatabase,
  listingId: number | null,
): Promise<ThreadListing | null> {
  if (listingId === null) return null

  const listing = await db
    .selectFrom('listings')
    .select(['id', 'title', 'slug'])
    .where('id', '=', listingId)
    .executeTakeFirst()

  return listing ?? null
}

async function findThreadFulfillment(
  db: AppDatabase,
  fulfillmentId: number | null,
): Promise<ThreadFulfillment | null> {
  if (fulfillmentId === null) return null

  const fulfillment = await db
    .selectFrom('fulfillments')
    .select(['id', 'orderId'])
    .where('id', '=', fulfillmentId)
    .executeTakeFirst()

  return fulfillment ?? null
}
