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

// Pinned, not fixed: the accent is dropped along with the space around it
// rather than transliterated (é is outside [a-z0-9] just like punctuation).
test('an accented letter is dropped, not transliterated', () => {
  assert.equal(slugBase('Café au Lait'), 'caf-au-lait')
})

test('a title of all digits slugs to itself', () => {
  assert.equal(slugBase('12345'), '12345')
})

test('a collision on the fallback word still counts up', () => {
  assert.equal(firstFreeSlug('—', ['listing', 'listing-2']), 'listing-3')
})

test('a slug is not truncated, however long the title', () => {
  const title = 'a'.repeat(300)

  assert.equal(slugBase(title), title)
})
