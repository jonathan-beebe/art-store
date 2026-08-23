import type { ActorType } from '../auth/actor-type.ts'
import { participantColumn } from './conversation-kind.ts'

/** Who is asking, and whether a moderation block stands against them. Only a
 * customer can be blocked; the flag is absent for the other two. */
export type ConversationActor = { type: ActorType; id: number; isBlocked?: boolean }

/** Participant columns as any conversation row carries them. */
export type ConversationParticipants = {
  sellerId: number | null
  customerId: number | null
  adminId: number | null
}

export type ConversationAccess = { mayRead: boolean; mayPost: boolean }

export function isConversationParticipant(
  participants: ConversationParticipants,
  actor: ConversationActor,
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
  return { mayRead, mayPost: mayRead && actor.isBlocked !== true }
}

const PARTICIPANT_TYPES: readonly ActorType[] = ['seller', 'customer', 'admin']

/** The participant on the other side of the thread from this one, if there is one. */
export function otherParticipants(
  participants: ConversationParticipants,
  sender: ConversationActor,
): readonly ConversationActor[] {
  return PARTICIPANT_TYPES.flatMap((type) => {
    const id = participants[participantColumn(type)]
    const isSender = type === sender.type && id === sender.id
    return id === null || isSender ? [] : [{ type, id }]
  })
}
