import { type ConversationKind, participantColumnsOf, subjectColumnOf } from './conversation-kind.ts'

/** The participant and subject columns of a conversation, as any row carries them. */
export type ConversationSubject = {
  kind: ConversationKind
  sellerId: number | null
  customerId: number | null
  adminId: number | null
  listingId: number | null
  fulfillmentId: number | null
}

export type ConversationOpening = {
  kind: ConversationKind
  sellerId?: number | null
  customerId?: number | null
  adminId?: number | null
  listingId?: number | null
  fulfillmentId?: number | null
}

/** Fills in the columns the caller left out, so every subject has the same shape. */
export function conversationSubject(opening: ConversationOpening): ConversationSubject {
  return {
    kind: opening.kind,
    sellerId: opening.sellerId ?? null,
    customerId: opening.customerId ?? null,
    adminId: opening.adminId ?? null,
    listingId: opening.listingId ?? null,
    fulfillmentId: opening.fulfillmentId ?? null,
  }
}

/**
 * The columns this kind needs that the subject does not fill. Empty means the
 * subject names both sides and whatever the thread is about.
 */
export function missingConversationParts(subject: ConversationSubject): readonly string[] {
  const subjectColumn = subjectColumnOf(subject.kind)
  const requiredColumns =
    subjectColumn === null
      ? participantColumnsOf(subject.kind)
      : [...participantColumnsOf(subject.kind), subjectColumn]

  return requiredColumns.filter((column) => subject[column] === null)
}

/** Whether two subjects name the same thread: same kind, same participants, same subject row. */
export function isSameConversationSubject(a: ConversationSubject, b: ConversationSubject): boolean {
  return (
    a.kind === b.kind &&
    a.sellerId === b.sellerId &&
    a.customerId === b.customerId &&
    a.adminId === b.adminId &&
    a.listingId === b.listingId &&
    a.fulfillmentId === b.fulfillmentId
  )
}
