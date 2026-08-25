import { test } from 'node:test'
import assert from 'node:assert/strict'
import { describeError, isDomainRefusal } from './logged-error.ts'
import { TransitionError } from '../transition-error.ts'
import { MissingDataError } from '../defect.ts'

test('an Error is described by its name, its message, and its stack', () => {
  const described = describeError(new TypeError('not a number'))

  assert.equal(described.type, 'TypeError')
  assert.equal(described.message, 'not a number')
  assert.match(String(described.stack), /TypeError: not a number/)
})

test('a subclass is described by the name it set for itself', () => {
  assert.equal(describeError(new TransitionError('cannot ship twice')).type, 'TransitionError')
})

test('something thrown that is not an Error is still described', () => {
  assert.deepEqual(describeError('just a string'), { type: 'string', message: 'just a string' })
  assert.deepEqual(describeError(42), { type: 'number', message: '42' })
  assert.deepEqual(describeError(null), { type: 'object', message: 'null' })
})

test('a TransitionError is the domain saying no; anything else is a fault', () => {
  assert.equal(isDomainRefusal(new TransitionError('cannot ship twice')), true)
  assert.equal(isDomainRefusal(new Error('the kiln exploded')), false)
  assert.equal(isDomainRefusal('a string'), false)
})

test('a defect is described by its name, reason, message, data, and stack', () => {
  const described = describeError(
    new MissingDataError('row_not_found', 'No order matches ord_1.', { order_id: 'ord_1' }),
  )

  assert.equal(described.type, 'MissingDataError')
  assert.equal(described.reason, 'row_not_found')
  assert.equal(described.message, 'No order matches ord_1.')
  assert.deepEqual(described.data, { order_id: 'ord_1' })
  assert.equal(typeof described.stack, 'string')
})

test('a plain error carries no reason and no data', () => {
  const described = describeError(new Error('the kiln exploded'))

  assert.ok(!('reason' in described))
  assert.ok(!('data' in described))
})

test('a non-string or empty reason carried on an error is not described', () => {
  assert.ok(!('reason' in describeError(Object.assign(new Error('x'), { reason: 42 }))))
  assert.ok(!('reason' in describeError(Object.assign(new Error('x'), { reason: '' }))))
})

test('data carried on an error is described only when it is a plain object', () => {
  assert.ok(!('data' in describeError(Object.assign(new Error('x'), { data: null }))))
  assert.ok(!('data' in describeError(Object.assign(new Error('x'), { data: ['a', 'b'] }))))
  assert.deepEqual(
    describeError(Object.assign(new Error('x'), { data: { order_id: 'ord_1' } })).data,
    { order_id: 'ord_1' },
  )
})

test('a transition error is described with the reason and data it carries', () => {
  const described = describeError(
    new TransitionError('A listing cannot move from archived to for_sale.', {
      reason: 'stale_status',
      data: { listing_id: 'lst_1' },
    }),
  )

  assert.equal(described.reason, 'stale_status')
  assert.deepEqual(described.data, { listing_id: 'lst_1' })
})

test('a defect is a fault, not a domain refusal', () => {
  assert.equal(isDomainRefusal(new MissingDataError('row_not_found', 'No order matches ord_1.')), false)
})
