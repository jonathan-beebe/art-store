import { test } from 'node:test'
import assert from 'node:assert/strict'
import { TransitionError } from '../transition-error.ts'
import { LISTING_STATUSES, LISTING_STATUS_TRANSITIONS, canTransitionListing, transitionListing } from './listing-status.ts'

test('LISTING_STATUSES names every status', () => {
  assert.deepEqual(LISTING_STATUSES, ['draft', 'for_sale', 'sold', 'archived'])
})

test('a draft goes on sale', () => {
  assert.equal(canTransitionListing('draft', 'for_sale'), true)
})

test('a listing on sale sells', () => {
  assert.equal(canTransitionListing('for_sale', 'sold'), true)
})

test('a sold listing returns to the storefront', () => {
  assert.equal(canTransitionListing('sold', 'for_sale'), true)
})

test('a draft and a listing on sale both archive', () => {
  assert.equal(canTransitionListing('draft', 'archived'), true)
  assert.equal(canTransitionListing('for_sale', 'archived'), true)
})

test('an archived listing goes nowhere', () => {
  assert.deepEqual(LISTING_STATUS_TRANSITIONS.archived, [])
})

test('a sold listing does not archive', () => {
  assert.equal(canTransitionListing('sold', 'archived'), false)
})

test('a draft does not sell', () => {
  assert.equal(canTransitionListing('draft', 'sold'), false)
})

test('transition returns the next status', () => {
  assert.equal(transitionListing('for_sale', 'sold'), 'sold')
})

test('transition refuses a move the table does not allow', () => {
  assert.throws(
    () => transitionListing('draft', 'sold'),
    (error: unknown) => error instanceof TransitionError && error.message === 'A listing cannot move from draft to sold.',
  )
})

test('transition refuses a status it does not know', () => {
  assert.throws(
    () => transitionListing('wishlisted' as never, 'sold'),
    TransitionError,
  )
})

test('every status has a transition list', () => {
  assert.deepEqual(Object.keys(LISTING_STATUS_TRANSITIONS).sort(), [...LISTING_STATUSES].sort())
})
