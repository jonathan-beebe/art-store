/**
 * The shared log vocabulary: `docs/alignment.md` §2.3 is the table this file
 * spells, and all three prototypes emit the same dotted names so one log format
 * reads the same whichever stack wrote it.
 *
 * `<subject>.<verb>` in the imperative — `order.place`, never `order.placed`.
 * Tense lives in `phase`, which says whether the line is the intent, a step, or
 * the outcome.
 *
 * Five names below belong to features this prototype does not have yet:
 * `order.sweep` and the sweep's own `order.cancel`, `fulfillment.decline`,
 * `refund.issue`, and `rate_limit.exceed`. They stay in the vocabulary so the
 * table reads whole; the code that emits them arrives with the features.
 */

export const LOG_EVENTS = [
  'http.request',
  'magic_link.request',
  'magic_link.consume',
  'customer.merge',
  'listing.create',
  'listing.update',
  'listing.publish',
  'listing.transition',
  'listing.view',
  'cart.add',
  'cart.update',
  'cart.remove',
  'order.place',
  'order.pay',
  'order.cancel',
  'order.sweep',
  'fulfillment.ship',
  'fulfillment.deliver',
  'fulfillment.decline',
  'refund.issue',
  'ledger.write',
  'payout.run',
  'payout.pay',
  'conversation.open',
  'message.post',
  'faq.publish',
  'faq.unpublish',
  'notification.write',
  'notification.deliver',
  'moderation.remove_listing',
  'moderation.lift_listing_removal',
  'moderation.block_customer',
  'moderation.lift_customer_block',
  'rate_limit.exceed',
  'migrate.run',
  'migrate.apply',
  'seed.run',
  'app.boot',
  'app.shutdown',
] as const

/** Every event name a log line is allowed to carry. */
export type LogEvent = (typeof LOG_EVENTS)[number]

/**
 * Where in an action's story a line sits. `will` opens it, `doing` marks a long
 * step inside the unit of work, and exactly one of `did`, `refused`, or
 * `failed` closes it: the world changed, the domain said no, or something
 * nobody expected went wrong.
 */
export const LOG_PHASES = ['will', 'doing', 'did', 'refused', 'failed'] as const

export type LogPhase = (typeof LOG_PHASES)[number]

/** The four levels a line is written at. `failed` is always `error`. */
export const LOG_LINE_LEVELS = ['debug', 'info', 'warn', 'error'] as const

export type LogLineLevel = (typeof LOG_LINE_LEVELS)[number]
