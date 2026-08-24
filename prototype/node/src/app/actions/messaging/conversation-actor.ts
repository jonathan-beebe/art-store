import type { ActionContext } from '../action-context.ts'
import { activeCustomerBlock } from '../moderation/active-customer-block.ts'
import type {
  ConversationActor,
  ConversationParticipant,
} from '../../core/messaging/conversation-access.ts'

/** Who is asking, before their standing is known. */
export type MessagingActor = ConversationParticipant

/**
 * The actor `conversationAccess` needs, with the moderation block filled in.
 * Only a customer can carry one, so the other two sides never pay for the read.
 */
export async function conversationActor(
  { db }: Pick<ActionContext, 'db'>,
  actor: MessagingActor,
): Promise<ConversationActor> {
  if (actor.type !== 'customer') return actor

  return {
    type: 'customer',
    id: actor.id,
    isBlocked: (await activeCustomerBlock({ db }, actor.id)) !== null,
  }
}
