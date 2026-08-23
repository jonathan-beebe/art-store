import { test } from 'node:test'
import assert from 'node:assert/strict'
import { awaitsCard, isUnpaid, isPayable } from './order-payment.ts'

test('an order awaiting payment takes a card', () => {
  assert.equal(awaitsCard('awaiting_payment'), true)
})

test('a declined order takes another card', () => {
  assert.equal(awaitsCard('payment_failed'), true)
})

test('an unverified order takes no card yet', () => {
  assert.equal(awaitsCard('pending_verification'), false)
})

test('a paid order takes no card', () => {
  assert.equal(awaitsCard('paid'), false)
})

test('an unverified order is unpaid', () => {
  assert.equal(isUnpaid('pending_verification'), true)
})

test('a declined order is unpaid', () => {
  assert.equal(isUnpaid('payment_failed'), true)
})

test('a shipped order is not unpaid', () => {
  assert.equal(isUnpaid('shipped'), false)
})

test('a verified purchaser pays an order awaiting payment', () => {
  assert.equal(isPayable('awaiting_payment', true), true)
})

test('an unverified purchaser pays nothing', () => {
  assert.equal(isPayable('awaiting_payment', false), false)
})

test('a verified purchaser cannot pay a delivered order', () => {
  assert.equal(isPayable('delivered', true), false)
})
