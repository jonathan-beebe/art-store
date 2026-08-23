import type { ActorType } from '../auth/actor-type.ts'

export const CONVERSATION_KINDS = [
  'admin_seller',
  'admin_customer',
  'fulfillment',
  'listing_question',
] as const

export type ConversationKind = (typeof CONVERSATION_KINDS)[number]

/** The column on `conversations` that names each side of a pairing. */
export type ParticipantColumn = 'sellerId' | 'customerId' | 'adminId'

/** The column that names what a conversation is about, for the kinds that have one. */
export type SubjectColumn = 'listingId' | 'fulfillmentId'

const PARTICIPANT_COLUMNS: Readonly<Record<ActorType, ParticipantColumn>> = {
  seller: 'sellerId',
  customer: 'customerId',
  admin: 'adminId',
}

type KindShape = {
  participants: readonly ParticipantColumn[]
  subject: SubjectColumn | null
}

const KIND_SHAPES: Readonly<Record<ConversationKind, KindShape>> = {
  admin_seller: { participants: ['adminId', 'sellerId'], subject: null },
  admin_customer: { participants: ['adminId', 'customerId'], subject: null },
  fulfillment: { participants: ['sellerId', 'customerId'], subject: 'fulfillmentId' },
  listing_question: { participants: ['sellerId', 'customerId'], subject: 'listingId' },
}

/** Which column holds this actor's id, whatever kind of conversation it is. */
export function participantColumn(actorType: ActorType): ParticipantColumn {
  return PARTICIPANT_COLUMNS[actorType]
}

/** The two columns a conversation of this kind must fill to have both sides. */
export function participantColumnsOf(kind: ConversationKind): readonly ParticipantColumn[] {
  return KIND_SHAPES[kind].participants
}

/** The column naming what the conversation is about, or null when it is about nothing. */
export function subjectColumnOf(kind: ConversationKind): SubjectColumn | null {
  return KIND_SHAPES[kind].subject
}
