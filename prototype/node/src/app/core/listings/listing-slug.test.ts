import { test } from 'node:test'
import assert from 'node:assert/strict'
import { slugBase, firstFreeSlug } from './listing-slug.ts'

test('it slugs the title', () => {
  assert.equal(firstFreeSlug('Harbour at Dusk', []), 'harbour-at-dusk')
})

test('it drops punctuation and edge separators', () => {
  assert.equal(slugBase('  Study, No. 4!  '), 'study-no-4')
})

test('it numbers a slug another listing already holds', () => {
  assert.equal(firstFreeSlug('Harbour at Dusk', ['harbour-at-dusk']), 'harbour-at-dusk-2')
})

test('it keeps counting past a numbered slug', () => {
  const taken = ['harbour-at-dusk', 'harbour-at-dusk-2', 'harbour-at-dusk-3']
  assert.equal(firstFreeSlug('Harbour at Dusk', taken), 'harbour-at-dusk-4')
})

test('it ignores slugs another title holds', () => {
  assert.equal(firstFreeSlug('Harbour at Dusk', ['morning-tide']), 'harbour-at-dusk')
})

test('it falls back to a word when the title slugs to nothing', () => {
  assert.equal(slugBase('—'), 'listing')
  assert.equal(firstFreeSlug('—', []), 'listing')
})

test('its base ignores what is already taken', () => {
  assert.equal(slugBase('Harbour at Dusk'), 'harbour-at-dusk')
})
