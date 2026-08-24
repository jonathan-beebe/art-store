import type { ConversationKind } from './conversation-kind.ts'
import type { AdminId, CustomerId, FulfillmentId, ListingId, SellerId } from '../ids/entity-ids.ts'

/** The participant and subject columns of a conversation, as any row carries them. */
export type ConversationSubject = {
  kind: ConversationKind
  sellerId: SellerId | null
  customerId: CustomerId | null
  adminId: AdminId | null
  listingId: ListingId | null
  fulfillmentId: FulfillmentId | null
}

/**
 * What a caller supplies to open or find a conversation. Each kind names
 * exactly the participants and the subject it needs — `KIND_SHAPES`
 * (`conversation-kind.ts`) is the same shape enforced for the rows already in
 * the table.
 */
export type ConversationOpening =
  | { kind: 'admin_seller'; adminId: AdminId; sellerId: SellerId }
  | { kind: 'admin_customer'; adminId: AdminId; customerId: CustomerId }
  | { kind: 'fulfillment'; sellerId: SellerId; customerId: CustomerId; fulfillmentId: FulfillmentId }
  | { kind: 'listing_question'; sellerId: SellerId; customerId: CustomerId; listingId: ListingId }

/** Flattens an opening into the row shape, filling the columns this kind leaves out with null. */
export function conversationSubject(opening: ConversationOpening): ConversationSubject {
  switch (opening.kind) {
    case 'admin_seller':
      return {
        kind: opening.kind,
        sellerId: opening.sellerId,
        customerId: null,
        adminId: opening.adminId,
        listingId: null,
        fulfillmentId: null,
      }
    case 'admin_customer':
      return {
        kind: opening.kind,
        sellerId: null,
        customerId: opening.customerId,
        adminId: opening.adminId,
        listingId: null,
        fulfillmentId: null,
      }
    case 'fulfillment':
      return {
        kind: opening.kind,
        sellerId: opening.sellerId,
        customerId: opening.customerId,
        adminId: null,
        listingId: null,
        fulfillmentId: opening.fulfillmentId,
      }
    case 'listing_question':
      return {
        kind: opening.kind,
        sellerId: opening.sellerId,
        customerId: opening.customerId,
        adminId: null,
        listingId: opening.listingId,
        fulfillmentId: null,
      }
  }
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

/** The letter `subjectKey` writes for each column, in the fixed order it walks them. */
const SUBJECT_KEY_COLUMNS = {
  sellerId: 's',
  customerId: 'c',
  adminId: 'a',
  listingId: 'l',
  fulfillmentId: 'f',
} as const satisfies Record<Exclude<keyof ConversationSubject, 'kind'>, string>

/**
 * The one string `conversations.subject_key`'s unique index guards:
 * `kind` followed by a `<letter>:<id>` token for every column this subject
 * fills, walked in a fixed order and skipping the columns it leaves null.
 *
 * Two calls on subjects `isSameConversationSubject` reads as equal always
 * return the same string, because equal subjects fill the same columns with
 * the same values. Two calls on subjects it reads as different never
 * collide: a different `kind` changes the first token, and two subjects of
 * the same kind fill the same set of columns (`KIND_SHAPES`), so a subject
 * that differs must differ in the value of one of them — and a prefixed id
 * never equals another column's id, so that token alone changes the string.
 */
export function subjectKey(subject: ConversationSubject): string {
  const parts: string[] = [subject.kind]

  for (const column of Object.keys(SUBJECT_KEY_COLUMNS) as (keyof typeof SUBJECT_KEY_COLUMNS)[]) {
    const value = subject[column]
    if (value !== null) parts.push(`${SUBJECT_KEY_COLUMNS[column]}:${value}`)
  }

  return parts.join(':')
}
