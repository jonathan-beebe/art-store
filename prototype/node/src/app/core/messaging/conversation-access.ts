import { participantColumn } from './conversation-kind.ts'
import type { AdminId, CustomerId, SellerId } from '../ids/entity-ids.ts'

/** Who somebody is, without regard to standing — enough to know whose column is theirs. */
export type ConversationParticipant =
  | { type: 'seller'; id: SellerId }
  | { type: 'customer'; id: CustomerId }
  | { type: 'admin'; id: AdminId }

/** Who is asking, and whether a moderation block stands against them. Only a
 * customer can be blocked, so the other two sides carry no flag to forget to set. */
export type ConversationActor =
  | { type: 'customer'; id: CustomerId; isBlocked: boolean }
  | { type: 'seller'; id: SellerId }
  | { type: 'admin'; id: AdminId }

/** Participant columns as any conversation row carries them. */
export type ConversationParticipants = {
  sellerId: SellerId | null
  customerId: CustomerId | null
  adminId: AdminId | null
}

export type ConversationAccess = { mayRead: boolean; mayPost: boolean }

export function isConversationParticipant(
  participants: ConversationParticipants,
  actor: ConversationParticipant,
): boolean {
  return participants[participantColumn(actor.type)] === actor.id
}

/**
 * What one actor may do in one conversation. Reading is being named in the
 * participant column for that actor's side; posting is reading plus standing.
 */
export function conversationAccess(
  participants: ConversationParticipants,
  actor: ConversationActor,
): ConversationAccess {
  const mayRead = isConversationParticipant(participants, actor)
  return { mayRead, mayPost: mayRead && (actor.type !== 'customer' || !actor.isBlocked) }
}

/** Everyone a conversation row names, in participant-column order. */
function participantsOf(participants: ConversationParticipants): ConversationParticipant[] {
  const named: ConversationParticipant[] = []

  if (participants.sellerId !== null) named.push({ type: 'seller', id: participants.sellerId })
  if (participants.customerId !== null) {
    named.push({ type: 'customer', id: participants.customerId })
  }
  if (participants.adminId !== null) named.push({ type: 'admin', id: participants.adminId })

  return named
}

/** The participant on the other side of the thread from this one, if there is one. */
export function otherParticipants(
  participants: ConversationParticipants,
  sender: ConversationParticipant,
): readonly ConversationParticipant[] {
  return participantsOf(participants).filter(
    (participant) => !(participant.type === sender.type && participant.id === sender.id),
  )
}
