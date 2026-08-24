import { normalizeEmail } from '../auth/email-address.ts'
import type { Purchaser } from '../orders/purchaser.ts'
import type { CustomerId } from '../ids/entity-ids.ts'

export type CheckoutIdentity = {
  customerId: CustomerId
  accountEmail: string | null
  isAccountVerified: boolean
  submittedEmail: string
}

/**
 * A signed-in customer buys under the address on their account, so a submitted
 * field cannot move an order onto someone else's identity. A guest buys under
 * the address they typed and verifies it afterwards.
 */
export function purchaserForCheckout(identity: CheckoutIdentity): Purchaser {
  if (identity.isAccountVerified && identity.accountEmail !== null) {
    return { id: identity.customerId, email: identity.accountEmail, isEmailVerified: true }
  }

  return {
    id: identity.customerId,
    email: normalizeEmail(identity.submittedEmail),
    isEmailVerified: false,
  }
}
