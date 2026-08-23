import type { ActionContext } from '../action-context.ts'
import { openConversation } from './open-conversation.ts'
import type { Conversation } from '../../db/commerce-schema.ts'

export type SupportRequester = { actorType: 'seller' | 'customer'; actorId: number }

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
  { actorType, actorId }: SupportRequester,
): Promise<OpenSupportConversationResult> {
  const admin = await context.db.selectFrom('admins').select('id').orderBy('id').limit(1).executeTakeFirst()
  if (admin === undefined) return { outcome: 'no-admin' }

  const conversation = await openConversation(
    context,
    actorType === 'seller'
      ? { kind: 'admin_seller', sellerId: actorId, adminId: admin.id }
      : { kind: 'admin_customer', customerId: actorId, adminId: admin.id },
  )

  return { outcome: 'opened', conversation }
}
