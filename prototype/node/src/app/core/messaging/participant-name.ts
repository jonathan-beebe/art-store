import type { ActorType } from '../auth/actor-type.ts'
import { otherParticipants, type ConversationParticipant, type ConversationParticipants } from './conversation-access.ts'
import type { ActorId, CustomerId } from '../ids/entity-ids.ts'

/**
 * How the customer on the other side of a thread reads. A storefront visitor
 * has neither a name nor an address until they verify one, so an unnamed
 * customer is named by the row they already are.
 */
export function customerName(customer: {
  id: CustomerId
  name: string | null
  email: string | null
}): string {
  const named = (customer.name ?? '').trim()
  if (named.length > 0) return named

  const address = (customer.email ?? '').trim()

  return address.length > 0 ? address : `Guest ${customer.id}`
}

/** What each side of a thread is called, keyed by actor type and id. */
export type ParticipantNames = Readonly<Record<ActorType, ReadonlyMap<ActorId, string>>>

/** What a thread with only one side left shows where the counterpart goes. */
export const ABSENT_COUNTERPART = 'Art Store'

/** Who this actor is talking to in this conversation. */
export function counterpartName(
  conversation: ConversationParticipants,
  actor: ConversationParticipant,
  names: ParticipantNames,
): string {
  const other = otherParticipants(conversation, actor)[0]
  if (other === undefined) return ABSENT_COUNTERPART

  return names[other.type].get(other.id) ?? ABSENT_COUNTERPART
}

/** What one message's sender is called. */
export function senderName(
  sender: { senderType: ActorType; senderId: ActorId },
  names: ParticipantNames,
): string {
  return names[sender.senderType].get(sender.senderId) ?? ABSENT_COUNTERPART
}
