/**
 * The seven limits `docs/alignment.md` §3 fixes, in the order its table lists
 * them. A name here is both the `rate_limit_windows.name` a counter is filed
 * under and the `data.limit` a `rate_limit.exceed` line names.
 */
export const RATE_LIMIT_NAMES = [
  'magic_link_request',
  'magic_link_consume',
  'message_post',
  'conversation_open',
  'checkout',
  'payment_attempt',
  'listing_write',
] as const

export type RateLimitName = (typeof RATE_LIMIT_NAMES)[number]
