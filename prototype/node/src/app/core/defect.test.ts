import { test } from 'node:test'
import assert from 'node:assert/strict'
import { Defect, MissingDataError, BadConfigError, BrokenContractError } from './defect.ts'

test('a missing data error names its reason, message, and data', () => {
  const error = new MissingDataError('row_not_found', 'No order matches ord_01J.', {
    order_id: 'ord_00000000000000000000000001',
  })

  assert.equal(error.name, 'MissingDataError')
  assert.equal(error.reason, 'row_not_found')
  assert.equal(error.message, 'No order matches ord_01J.')
  assert.deepEqual(error.data, { order_id: 'ord_00000000000000000000000001' })
  assert.ok(error instanceof Defect)
  assert.ok(error instanceof Error)
})

test('a bad config error carries its own class name', () => {
  const error = new BadConfigError('missing_env', 'STRIPE_SECRET_KEY is not set.')

  assert.equal(error.name, 'BadConfigError')
})

test('a broken contract error carries its own class name', () => {
  const error = new BrokenContractError('missing_argument', 'listing_id is required.')

  assert.equal(error.name, 'BrokenContractError')
})

test('a defect built without data has no data at all', () => {
  const error = new MissingDataError('row_not_found', 'No listing matches lst_01J.')

  assert.equal(error.data, undefined)
})
