import type { ActorType } from '../auth/actor-type.ts'
import type { ConversationParticipant } from './conversation-access.ts'
import type { ActorId, ConversationId } from '../ids/entity-ids.ts'

/** What the fold needs off a message row: who sent it, and whether it was read. */
export type ReadMarker = {
  conversationId: ConversationId
  senderType: ActorType
  senderId: ActorId
  readAt: string | null
}

/** Whether this actor sent this message. */
export function isSentBy(
  message: Pick<ReadMarker, 'senderType' | 'senderId'>,
  actor: ConversationParticipant,
): boolean {
  return message.senderType === actor.type && message.senderId === actor.id
}

/** Whether this reader has this one message still to read. */
export function isUnreadBy(message: ReadMarker, reader: ConversationParticipant): boolean {
  return message.readAt === null && !isSentBy(message, reader)
}

/**
 * How many messages in each conversation this reader has not read. A message is
 * unread for everyone but its sender until its `readAt` is set, because a
 * conversation has exactly two sides.
 */
export function unreadCountsByConversation(
  messages: readonly ReadMarker[],
  reader: ConversationParticipant,
): ReadonlyMap<ConversationId, number> {
  const counts = new Map<ConversationId, number>()
  for (const message of messages) {
    if (isUnreadBy(message, reader)) {
      counts.set(message.conversationId, (counts.get(message.conversationId) ?? 0) + 1)
    }
  }
  return counts
}

export function totalUnreadMessages(counts: ReadonlyMap<ConversationId, number>): number {
  return [...counts.values()].reduce((total, count) => total + count, 0)
}
