import type { ActionContext } from '../action-context.ts'
import type { MessagingActor } from './conversation-actor.ts'
import type { ActorType } from '../../core/auth/actor-type.ts'
import {
  otherParticipants,
  type ConversationParticipants,
} from '../../core/messaging/conversation-access.ts'
import { customerName } from '../../core/messaging/participant-name.ts'
import { shopName } from '../../core/shop/shop-name.ts'

/** What each side of a thread is called, keyed by actor type and id. */
export type ParticipantNames = Readonly<Record<ActorType, ReadonlyMap<number, string>>>

/** What a thread with only one side left shows where the counterpart goes. */
const ABSENT_COUNTERPART = 'Art Store'

/**
 * The display name of everyone named in these conversations, read in one pass
 * per side so an inbox of any length costs three queries.
 */
export async function participantNames(
  { db }: Pick<ActionContext, 'db'>,
  conversations: readonly ConversationParticipants[],
): Promise<ParticipantNames> {
  const sellerIds = idsOf(conversations, (conversation) => conversation.sellerId)
  const customerIds = idsOf(conversations, (conversation) => conversation.customerId)
  const adminIds = idsOf(conversations, (conversation) => conversation.adminId)

  const [sellers, customers, admins] = await Promise.all([
    sellerIds.length === 0
      ? []
      : db
          .selectFrom('sellers')
          .select(['id', 'shopName', 'email'])
          .where('id', 'in', sellerIds)
          .execute(),
    customerIds.length === 0
      ? []
      : db
          .selectFrom('customers')
          .select(['id', 'name', 'email'])
          .where('id', 'in', customerIds)
          .execute(),
    adminIds.length === 0
      ? []
      : db.selectFrom('admins').select(['id', 'name']).where('id', 'in', adminIds).execute(),
  ])

  return {
    seller: new Map(sellers.map((seller) => [seller.id, shopName(seller)])),
    customer: new Map(customers.map((customer) => [customer.id, customerName(customer)])),
    admin: new Map(admins.map((admin) => [admin.id, admin.name])),
  }
}

/** Who this actor is talking to in this conversation. */
export function counterpartName(
  conversation: ConversationParticipants,
  actor: MessagingActor,
  names: ParticipantNames,
): string {
  const other = otherParticipants(conversation, actor)[0]
  if (other === undefined) return ABSENT_COUNTERPART

  return names[other.type].get(other.id) ?? ABSENT_COUNTERPART
}

/** What one message's sender is called. */
export function senderName(
  sender: { senderType: ActorType; senderId: number },
  names: ParticipantNames,
): string {
  return names[sender.senderType].get(sender.senderId) ?? ABSENT_COUNTERPART
}

function idsOf(
  conversations: readonly ConversationParticipants[],
  column: (conversation: ConversationParticipants) => number | null,
): readonly number[] {
  return [...new Set(conversations.map(column).filter((id) => id !== null))]
}
