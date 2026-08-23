import { test } from 'node:test'
import assert from 'node:assert/strict'
import { FAVORITE_CHANGES, favoriteChangeFor, listingEventForFavoriteChange } from './favorite-change.ts'

test('FAVORITE_CHANGES names both changes', () => {
  assert.deepEqual(FAVORITE_CHANGES, ['added', 'removed'])
})

test('a listing nobody saved gets favorited', () => {
  assert.equal(favoriteChangeFor(false), 'added')
})

test('a saved listing gets dropped', () => {
  assert.equal(favoriteChangeFor(true), 'removed')
})

test('each change records its own event', () => {
  assert.equal(listingEventForFavoriteChange('added'), 'favorite')
  assert.equal(listingEventForFavoriteChange('removed'), 'unfavorite')
})
