import { test } from 'node:test'
import assert from 'node:assert/strict'
import { statusLabel } from './status-label.ts'

test('it reads a stored status as a sentence', () => {
  assert.equal(statusLabel('awaiting_shipment'), 'Awaiting shipment')
})

test('a single word status keeps its shape', () => {
  assert.equal(statusLabel('paid'), 'Paid')
})

test('an empty status reads as nothing', () => {
  assert.equal(statusLabel(''), '')
})
