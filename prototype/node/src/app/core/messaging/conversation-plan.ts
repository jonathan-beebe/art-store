import { type ConversationSubject, isSameConversationSubject } from './conversation-subject.ts'
import type { ConversationId } from '../ids/entity-ids.ts'

export type ConversationPlan<Row extends ConversationSubject & { id: ConversationId }> =
  | { outcome: 'reuse'; conversation: Row }
  | { outcome: 'open'; subject: ConversationSubject }

/**
 * Whether a conversation on this subject already exists. One thread per subject
 * is what makes "message this seller" reach the same place every time, so the
 * decision is a match over the rows rather than an insert that may collide.
 */
export function planConversation<Row extends ConversationSubject & { id: ConversationId }>(
  existing: readonly Row[],
  subject: ConversationSubject,
): ConversationPlan<Row> {
  const reused = existing.find((row) => isSameConversationSubject(row, subject))
  return reused === undefined ? { outcome: 'open', subject } : { outcome: 'reuse', conversation: reused }
}
