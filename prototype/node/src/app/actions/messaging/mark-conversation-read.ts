import type { ConversationId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import type { MessagingActor } from './conversation-actor.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type MarkConversationReadInput = {
  conversationId: ConversationId
  reader: MessagingActor
}

/**
 * Marks everything this reader had left in one thread. The WHERE clause states
 * `isUnreadBy`'s rule directly — not read and not sent by the reader — rather
 * than a second copy of it read back into application code.
 */
export async function markConversationRead(
  context: ActionContext,
  input: MarkConversationReadInput,
): Promise<number> {
  const { db, clock } = context

  const result = await db
    .updateTable('messages')
    .set({ readAt: toTimestamp(clock.now()) })
    .where('conversationId', '=', input.conversationId)
    .where('readAt', 'is', null)
    .where((eb) =>
      eb.not(
        eb.and([
          eb('senderType', '=', input.reader.type),
          eb('senderId', '=', input.reader.id),
        ]),
      ),
    )
    .executeTakeFirst()

  return Number(result.numUpdatedRows)
}
