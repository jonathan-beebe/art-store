import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { MessagingActor } from './conversation-actor.ts'
import { isUnreadBy } from '../../core/messaging/unread-messages.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type MarkConversationReadInput = {
  conversationId: number
  reader: MessagingActor
}

/**
 * Marks everything this reader had left in one thread. Which messages those are
 * is `isUnreadBy`'s answer rather than a second copy of the rule in SQL.
 */
export async function markConversationRead(
  context: ActionContext,
  input: MarkConversationReadInput,
): Promise<number> {
  return runInTransaction(context, async ({ db, clock }) => {
    const messages = await db
      .selectFrom('messages')
      .select(['id', 'conversationId', 'senderType', 'senderId', 'readAt'])
      .where('conversationId', '=', input.conversationId)
      .execute()

    const unreadIds = messages
      .filter((message) => isUnreadBy(message, input.reader))
      .map((message) => message.id)

    if (unreadIds.length === 0) return 0

    await db
      .updateTable('messages')
      .set({ readAt: toTimestamp(clock.now()) })
      .where('id', 'in', unreadIds)
      .execute()

    return unreadIds.length
  })
}
