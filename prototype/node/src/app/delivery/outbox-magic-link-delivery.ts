import { signInLinkMessage } from '../core/notifications/notification-message.ts'
import type { Flash } from '../plugins/flash.ts'
import type { DeliveryContext } from './delivery-context.ts'
import type { MagicLinkDelivery, MagicLinkMessage } from './magic-link-delivery.ts'
import { enqueueOutboxMessage } from './outbox-message.ts'

/**
 * Queues the sign-in link for the outbox instead of handing it back to the
 * browser that asked. The flash is empty: the link has left the request.
 */
export const outboxMagicLinkDelivery: MagicLinkDelivery = {
  async deliver(context: DeliveryContext, message: MagicLinkMessage): Promise<Flash> {
    await enqueueOutboxMessage(context, {
      recipient: message.email,
      message: signInLinkMessage(message.url),
    })

    return {}
  },
}
