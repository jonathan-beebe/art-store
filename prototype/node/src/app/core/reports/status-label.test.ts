import { test } from 'node:test'
import assert from 'node:assert/strict'
import { statusLabel } from './status-label.ts'

test('a single-word status reads as a sentence', () => {
  assert.equal(statusLabel('draft'), 'Draft')
})

test('the underscores of a multi-word status become spaces', () => {
  assert.equal(statusLabel('for_sale'), 'For sale')
  assert.equal(statusLabel('awaiting_shipment'), 'Awaiting shipment')
  assert.equal(statusLabel('pending_verification'), 'Pending verification')
  assert.equal(statusLabel('paid_out'), 'Paid out')
})
