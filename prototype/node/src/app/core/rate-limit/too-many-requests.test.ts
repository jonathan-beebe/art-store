import { test } from 'node:test'
import assert from 'node:assert/strict'
import { tooManyRequestsMessage } from './too-many-requests.ts'

test('rounds up to whole minutes', () => {
  assert.equal(tooManyRequestsMessage(61), 'Too many requests — try again in 2 minutes.')
  assert.equal(tooManyRequestsMessage(120), 'Too many requests — try again in 2 minutes.')
})

test('never says fewer than one minute', () => {
  assert.equal(tooManyRequestsMessage(1), 'Too many requests — try again in 1 minutes.')
})
