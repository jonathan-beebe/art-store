import type { CustomerId, SellerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { openConversation } from './open-conversation.ts'
import type { Conversation } from '../../db/commerce-schema.ts'

export type SupportRequester =
  | { actorType: 'seller'; actorId: SellerId }
  | { actorType: 'customer'; actorId: CustomerId }

export type OpenSupportConversationResult =
  | { outcome: 'opened'; conversation: Conversation }
  | { outcome: 'no-admin' }

/**
 * The support thread between this actor and the first admin by id — this
 * prototype has no assignment model. With no admin row at all there is nobody
 * to open a thread with, so the caller decides what the visitor sees instead.
 */
export async function openSupportConversation(
  context: ActionContext,
  requester: SupportRequester,
): Promise<OpenSupportConversationResult> {
  const admin = await context.db
    .selectFrom('admins')
    .select('id')
    .orderBy('createdAt')
    .orderBy('id')
    .limit(1)
    .executeTakeFirst()
  if (admin === undefined) return { outcome: 'no-admin' }

  const conversation = await openConversation(
    context,
    requester.actorType === 'seller'
      ? { kind: 'admin_seller', sellerId: requester.actorId, adminId: admin.id }
      : { kind: 'admin_customer', customerId: requester.actorId, adminId: admin.id },
  )

  return { outcome: 'opened', conversation }
}
