import type { ActionContext } from '../action-context.ts'
import type { MessagingActor } from './conversation-actor.ts'
import { participantNames } from './conversation-participants.ts'
import { conversationTopics } from './conversation-topics.ts'
import { participantColumn } from '../../core/messaging/conversation-kind.ts'
import { counterpartName } from '../../core/messaging/participant-name.ts'
import { conversationPath } from '../../core/messaging/conversation-path.ts'
import {
  totalUnreadMessages,
  unreadCountsByConversation,
} from '../../core/messaging/unread-messages.ts'
import type { Conversation } from '../../db/commerce-schema.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { Timestamp } from '../../db/timestamp.ts'

/** One row of an inbox, on whichever of the three sites is showing it. */
export type InboxConversation = {
  id: number
  topic: string
  counterpart: string
  lastMessageAt: Timestamp
  preview: string | null
  unreadCount: number
  path: string
}

/**
 * Every thread this actor is in, newest first, with what is still unread in
 * each. All three inboxes read this: the pairing differs, the page does not.
 */
export async function inboxConversations(
  context: Pick<ActionContext, 'db'>,
  actor: MessagingActor,
): Promise<readonly InboxConversation[]> {
  const conversations = await conversationsFor(context.db, actor)
  if (conversations.length === 0) return []

  const messages = await messagesIn(
    context.db,
    conversations.map((conversation) => conversation.id),
  )
  const unread = unreadCountsByConversation(messages, actor)
  const previews = new Map(messages.map((message) => [message.conversationId, message.body]))
  const [topics, names] = await Promise.all([
    conversationTopics(context, conversations),
    participantNames(context, conversations),
  ])

  return conversations.map((conversation) => ({
    id: conversation.id,
    topic: topics.get(conversation.id) ?? '',
    counterpart: counterpartName(conversation, actor, names),
    lastMessageAt: conversation.lastMessageAt,
    preview: previews.get(conversation.id) ?? null,
    unreadCount: unread.get(conversation.id) ?? 0,
    path: conversationPath(actor.type, conversation.id),
  }))
}

/** How many messages this actor has waiting across every thread, for the nav. */
export async function unreadMessageCount(
  { db }: Pick<ActionContext, 'db'>,
  actor: MessagingActor,
): Promise<number> {
  const conversations = await conversationsFor(db, actor)
  if (conversations.length === 0) return 0

  const messages = await messagesIn(
    db,
    conversations.map((conversation) => conversation.id),
  )

  return totalUnreadMessages(unreadCountsByConversation(messages, actor))
}

async function conversationsFor(
  db: AppDatabase,
  actor: MessagingActor,
): Promise<readonly Conversation[]> {
  return db
    .selectFrom('conversations')
    .selectAll()
    .where(participantColumn(actor.type), '=', actor.id)
    .orderBy('lastMessageAt', 'desc')
    .orderBy('id', 'desc')
    .execute()
}

/** Ordered oldest first, so the last write of each preview wins. */
async function messagesIn(db: AppDatabase, conversationIds: readonly number[]) {
  return db
    .selectFrom('messages')
    .select(['id', 'conversationId', 'senderType', 'senderId', 'body', 'readAt'])
    .where('conversationId', 'in', conversationIds)
    .orderBy('id')
    .execute()
}
