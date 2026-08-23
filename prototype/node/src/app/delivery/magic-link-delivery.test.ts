import { test } from 'node:test'
import assert from 'node:assert/strict'
import { flashMagicLinkDelivery } from './flash-magic-link-delivery.ts'
import { outboxMagicLinkDelivery } from './outbox-magic-link-delivery.ts'
import { MAGIC_LINK_DELIVERIES, selectMagicLinkDelivery } from './magic-link-delivery.ts'

test('the prototype default flashes the link', () => {
  assert.equal(selectMagicLinkDelivery('flash'), flashMagicLinkDelivery)
})

test('outbox is selected by name', () => {
  assert.equal(selectMagicLinkDelivery('outbox'), outboxMagicLinkDelivery)
})

test('every configurable name selects a delivery', () => {
  for (const name of MAGIC_LINK_DELIVERIES) {
    assert.equal(typeof selectMagicLinkDelivery(name).deliver, 'function')
  }
})
