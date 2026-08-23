import { declineMessage } from '../../core/payments/decline-reason.ts'
import type { Payment } from '../../db/commerce-schema.ts'

/** What the last charge attempt tells the buyer, and nothing at all when no
 * card has been refused. */
export function declineNotice(payment: Payment | null): string | null {
  return payment === null || payment.declineReason === null
    ? null
    : declineMessage(payment.declineReason)
}
