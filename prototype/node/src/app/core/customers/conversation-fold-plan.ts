import type { ConversationId, CustomerId } from '../ids/entity-ids.ts'
import { isSameConversationSubject, type ConversationSubject } from '../messaging/conversation-subject.ts'

export type ConversationFoldRow = ConversationSubject & { id: ConversationId }

export type ConversationFoldPlan =
  /** No thread on this subject stands under the verified customer yet: re-point this one in place. */
  | { outcome: 'move'; conversationId: ConversationId }
  /** The verified customer already holds this subject: fold this thread's messages onto that one and drop it. */
  | { outcome: 'absorb'; movingId: ConversationId; standingId: ConversationId }

/**
 * What becomes of one of the anonymous customer's conversations when its
 * `customerId` is about to become the verified customer's. `verifiedConversations`
 * is that customer's own rows before the merge touches anything, so a subject
 * they already hold a thread on folds onto it instead of duplicating it — the
 * same one-thread-per-subject rule `planConversation` enforces on an ordinary
 * open.
 */
export function planConversationFold(
  moving: ConversationFoldRow,
  verifiedCustomerId: CustomerId,
  verifiedConversations: readonly ConversationFoldRow[],
): ConversationFoldPlan {
  const movedSubject: ConversationSubject = { ...moving, customerId: verifiedCustomerId }
  const standing = verifiedConversations.find((row) => isSameConversationSubject(row, movedSubject))

  return standing === undefined
    ? { outcome: 'move', conversationId: moving.id }
    : { outcome: 'absorb', movingId: moving.id, standingId: standing.id }
}
