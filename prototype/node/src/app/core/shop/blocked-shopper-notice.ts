import type { CustomerStanding } from '../moderation/customer-standing.ts'

const BLOCKED = 'Your account is on hold, so you cannot add to a cart or check out.'

/** What a blocked customer is told when they try to buy. Browsing is untouched,
 * so the notice names only what the hold takes away. */
export function blockedShopperNotice(standing: CustomerStanding): string {
  return standing.reason === null ? BLOCKED : `${BLOCKED} ${standing.reason}`
}
