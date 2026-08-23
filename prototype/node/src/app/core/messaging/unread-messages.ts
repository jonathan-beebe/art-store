import type { ActorType } from '../auth/actor-type.ts'
import type { ConversationActor } from './conversation-access.ts'

/** What the fold needs off a message row: who sent it, and whether it was read. */
export type ReadMarker = {
  conversationId: number
  senderType: ActorType
  senderId: number
  readAt: string | null
}

/** Whether this reader has this one message still to read. */
export function isUnreadBy(message: ReadMarker, reader: ConversationActor): boolean {
  return (
    message.readAt === null && !(message.senderType === reader.type && message.senderId === reader.id)
  )
}

/**
 * How many messages in each conversation this reader has not read. A message is
 * unread for everyone but its sender until its `readAt` is set, because a
 * conversation has exactly two sides.
 */
export function unreadCountsByConversation(
  messages: readonly ReadMarker[],
  reader: ConversationActor,
): ReadonlyMap<number, number> {
  const counts = new Map<number, number>()
  for (const message of messages) {
    if (isUnreadBy(message, reader)) {
      counts.set(message.conversationId, (counts.get(message.conversationId) ?? 0) + 1)
    }
  }
  return counts
}

export function totalUnreadMessages(counts: ReadonlyMap<number, number>): number {
  return [...counts.values()].reduce((total, count) => total + count, 0)
}
