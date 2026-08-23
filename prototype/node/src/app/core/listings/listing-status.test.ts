import { test } from 'node:test'
import assert from 'node:assert/strict'
import { TransitionError } from '../transition-error.ts'
import {
  LISTING_STATUSES,
  LISTING_STATUS_TRANSITIONS,
  availableListingTransitions,
  canTransitionListing,
  isBlockedByRemoval,
  transitionListing,
} from './listing-status.ts'

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

test('with no active removal, every status offers its plain transition list', () => {
  for (const status of LISTING_STATUSES) {
    assert.deepEqual(availableListingTransitions(status, false), LISTING_STATUS_TRANSITIONS[status])
  }
})

test('an active removal takes for_sale off a draft', () => {
  assert.deepEqual(availableListingTransitions('draft', true), ['archived'])
})

test('an active removal takes for_sale off a sold listing', () => {
  assert.deepEqual(availableListingTransitions('sold', true), [])
})

test("an active removal leaves for_sale's own transitions alone", () => {
  assert.deepEqual(availableListingTransitions('for_sale', true), ['sold', 'archived'])
})

test('an archived listing offers no transitions either way', () => {
  assert.deepEqual(availableListingTransitions('archived', false), [])
  assert.deepEqual(availableListingTransitions('archived', true), [])
})

test('a removal blocks only a request to move to for_sale', () => {
  assert.equal(isBlockedByRemoval('for_sale', true), true)
  assert.equal(isBlockedByRemoval('sold', true), false)
  assert.equal(isBlockedByRemoval('archived', true), false)
})

test('with no active removal, nothing is blocked', () => {
  for (const status of LISTING_STATUSES) {
    assert.equal(isBlockedByRemoval(status, false), false)
  }
})
