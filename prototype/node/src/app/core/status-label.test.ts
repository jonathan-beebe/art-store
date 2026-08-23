import { test } from 'node:test'
import assert from 'node:assert/strict'
import { statusButtons, statusLabel } from './status-label.ts'

test('a single-word status reads as a sentence', () => {
  assert.equal(statusLabel('draft'), 'Draft')
  assert.equal(statusLabel('paid'), 'Paid')
})

test('the underscores of a multi-word status become spaces', () => {
  assert.equal(statusLabel('for_sale'), 'For sale')
  assert.equal(statusLabel('awaiting_shipment'), 'Awaiting shipment')
  assert.equal(statusLabel('pending_verification'), 'Pending verification')
  assert.equal(statusLabel('paid_out'), 'Paid out')
})

test('an empty status reads as nothing', () => {
  assert.equal(statusLabel(''), '')
})

test('each transition becomes a button naming the status it switches to', () => {
  assert.deepEqual(statusButtons(['for_sale', 'archived']), [
    { status: 'for_sale', label: 'Mark for sale' },
    { status: 'archived', label: 'Mark archived' },
  ])
})

test('no legal transitions means no buttons', () => {
  assert.deepEqual(statusButtons([]), [])
})
