import type { ActorId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import type { ConversationParticipants } from '../../core/messaging/conversation-access.ts'
import { customerName, type ParticipantNames } from '../../core/messaging/participant-name.ts'
import { shopName } from '../../core/shop/shop-name.ts'

export type { ParticipantNames } from '../../core/messaging/participant-name.ts'

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

  const sellers =
    sellerIds.length === 0
      ? []
      : await db
          .selectFrom('sellers')
          .select(['id', 'shopName', 'email'])
          .where('id', 'in', sellerIds)
          .execute()

  const customers =
    customerIds.length === 0
      ? []
      : await db
          .selectFrom('customers')
          .select(['id', 'name', 'email'])
          .where('id', 'in', customerIds)
          .execute()

  const admins =
    adminIds.length === 0
      ? []
      : await db.selectFrom('admins').select(['id', 'name']).where('id', 'in', adminIds).execute()

  return {
    seller: new Map(sellers.map((seller) => [seller.id, shopName(seller)])),
    customer: new Map(customers.map((customer) => [customer.id, customerName(customer)])),
    admin: new Map(admins.map((admin) => [admin.id, admin.name])),
  }
}

function idsOf<Id extends ActorId>(
  conversations: readonly ConversationParticipants[],
  column: (conversation: ConversationParticipants) => Id | null,
): readonly Id[] {
  return [...new Set(conversations.map(column).filter((id) => id !== null))]
}
