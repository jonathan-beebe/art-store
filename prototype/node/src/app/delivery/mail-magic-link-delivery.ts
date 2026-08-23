import { NotImplementedError } from '../not-implemented-error.ts'
import type { Flash } from '../plugins/flash.ts'
import type { MagicLinkDelivery } from './magic-link-delivery.ts'

/** The seam a real mailer drops into. Selecting it without one loses links. */
export const mailMagicLinkDelivery: MagicLinkDelivery = {
  deliver(): Flash {
    throw new NotImplementedError('Email delivery is not implemented yet')
  },
}
