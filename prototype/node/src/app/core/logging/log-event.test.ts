import { test } from 'node:test'
import assert from 'node:assert/strict'
import { LOG_EVENTS, LOG_LINE_LEVELS, LOG_PHASES } from './log-event.ts'

/** `docs/alignment.md` §2.3, spelled out, so drift from the contract fails here. */
const CONTRACT_EVENTS = [
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
]

test('the vocabulary is the contract table, with nothing added or dropped', () => {
  assert.deepEqual([...LOG_EVENTS].sort(), [...CONTRACT_EVENTS].sort())
})

test('every event is <subject>.<verb> and named once', () => {
  for (const event of LOG_EVENTS) {
    assert.match(event, /^[a-z_]+\.[a-z_]+$/, event)
  }

  assert.equal(new Set(LOG_EVENTS).size, LOG_EVENTS.length)
})

test('the phases are the five docs/alignment.md §2.2 fixes', () => {
  assert.deepEqual([...LOG_PHASES], ['will', 'doing', 'did', 'refused', 'failed'])
})

test('the levels are the four a line may be written at', () => {
  assert.deepEqual([...LOG_LINE_LEVELS], ['debug', 'info', 'warn', 'error'])
})
