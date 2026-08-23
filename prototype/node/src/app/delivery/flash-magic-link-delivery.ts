import type { Flash } from '../plugins/flash.ts'
import type { MagicLinkDelivery, MagicLinkMessage } from './magic-link-delivery.ts'

/**
 * Hands the link to the debug alert every layout renders, which is how the
 * prototype delivers a sign-in link with no mailbox anywhere.
 */
export const flashMagicLinkDelivery: MagicLinkDelivery = {
  deliver(message: MagicLinkMessage): Flash {
    return { debugMagicLink: message.url }
  },
}
