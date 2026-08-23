import { test } from 'node:test'
import assert from 'node:assert/strict'
import { flashMagicLinkDelivery } from './flash-magic-link-delivery.ts'
import { mailMagicLinkDelivery } from './mail-magic-link-delivery.ts'
import { MAGIC_LINK_DELIVERIES, selectMagicLinkDelivery } from './magic-link-delivery.ts'

test('the prototype default flashes the link', () => {
  assert.equal(selectMagicLinkDelivery('flash'), flashMagicLinkDelivery)
})

test('mail is selected by name', () => {
  assert.equal(selectMagicLinkDelivery('mail'), mailMagicLinkDelivery)
})

test('every configurable name selects a delivery', () => {
  for (const name of MAGIC_LINK_DELIVERIES) {
    assert.equal(typeof selectMagicLinkDelivery(name).deliver, 'function')
  }
})
