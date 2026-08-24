import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseCartQuantity } from './cart-quantity.ts'

test('an absent quantity defaults to one', () => {
  assert.deepEqual(parseCartQuantity(undefined, 5), { ok: true, value: 1 })
})

test('a blank quantity defaults to one', () => {
  assert.deepEqual(parseCartQuantity('   ', 5), { ok: true, value: 1 })
})

test('a non-numeric quantity is refused', () => {
  assert.deepEqual(parseCartQuantity('lots', 5), {
    ok: false,
    errors: { quantity: 'Choose a quantity from 1 to 5.' },
  })
})

test('zero is refused', () => {
  assert.deepEqual(parseCartQuantity('0', 5), {
    ok: false,
    errors: { quantity: 'Choose a quantity from 1 to 5.' },
  })
})

test('a negative quantity is refused', () => {
  assert.deepEqual(parseCartQuantity('-1', 5), {
    ok: false,
    errors: { quantity: 'Choose a quantity from 1 to 5.' },
  })
})

test('a non-integer quantity is refused', () => {
  assert.deepEqual(parseCartQuantity('1.5', 5), {
    ok: false,
    errors: { quantity: 'Choose a quantity from 1 to 5.' },
  })
})

test('one is accepted', () => {
  assert.deepEqual(parseCartQuantity('1', 5), { ok: true, value: 1 })
})

test('a mid-range quantity is accepted', () => {
  assert.deepEqual(parseCartQuantity('3', 5), { ok: true, value: 3 })
})

test('a quantity padded with whitespace is trimmed before parsing', () => {
  assert.deepEqual(parseCartQuantity('  3  ', 5), { ok: true, value: 3 })
})

test('exactly the stock on hand is accepted', () => {
  assert.deepEqual(parseCartQuantity('5', 5), { ok: true, value: 5 })
})

test('one over the stock on hand is refused', () => {
  assert.deepEqual(parseCartQuantity('6', 5), {
    ok: false,
    errors: { quantity: 'Choose a quantity from 1 to 5.' },
  })
})

test('the error names the actual stock on hand, not a fixed number', () => {
  assert.deepEqual(parseCartQuantity('11', 10), {
    ok: false,
    errors: { quantity: 'Choose a quantity from 1 to 10.' },
  })
})
