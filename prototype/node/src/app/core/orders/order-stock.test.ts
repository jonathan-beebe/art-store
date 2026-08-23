import { test } from 'node:test'
import assert from 'node:assert/strict'
import { holdsStock, stockChangeBetween } from './order-stock.ts'

test('an order awaiting payment holds its stock', () => {
  assert.equal(holdsStock('awaiting_payment'), true)
})

test('a failed payment holds nothing', () => {
  assert.equal(holdsStock('payment_failed'), false)
})

test('a cancelled order holds nothing', () => {
  assert.equal(holdsStock('cancelled'), false)
})

test('a declined card hands the stock back', () => {
  const change = stockChangeBetween({ from: 'awaiting_payment', to: 'payment_failed' })

  assert.equal(change, 'restore')
})

test('a retry claims the stock again', () => {
  const change = stockChangeBetween({ from: 'payment_failed', to: 'paid' })

  assert.equal(change, 'take')
})

test('a first payment leaves the stock placement already took', () => {
  const change = stockChangeBetween({ from: 'awaiting_payment', to: 'paid' })

  assert.equal(change, 'keep')
})

test('a retry that is declined again changes nothing', () => {
  const change = stockChangeBetween({ from: 'payment_failed', to: 'payment_failed' })

  assert.equal(change, 'keep')
})
