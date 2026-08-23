import { test } from 'node:test'
import assert from 'node:assert/strict'
import { filterQuery, parseListingSearch, searchLikePattern } from './listing-search.ts'

test('it reads a term and a medium', () => {
  const search = parseListingSearch({ term: 'harbour', medium: 'Oil on canvas' })

  assert.equal(search.term, 'harbour')
  assert.equal(search.medium, 'Oil on canvas')
})

test('it treats blank input as no filter', () => {
  const search = parseListingSearch({ term: '   ', medium: undefined })

  assert.equal(search.term, null)
  assert.equal(search.medium, null)
})

test('it trims what the visitor typed', () => {
  assert.equal(parseListingSearch({ term: '  dusk  ' }).term, 'dusk')
})

test('it wraps the term in wildcards', () => {
  assert.equal(searchLikePattern(parseListingSearch({ term: 'harbour' })), '%harbour%')
})

test('it drops wildcards the visitor typed', () => {
  assert.equal(searchLikePattern(parseListingSearch({ term: 'a%_b' })), '%a b%')
})

test('it refuses a pattern without a term', () => {
  assert.throws(() => searchLikePattern(parseListingSearch({ medium: 'Oil' })), RangeError)
})

test('it repeats an empty search as an empty pair', () => {
  assert.equal(filterQuery(parseListingSearch({})), 'q=&medium=')
})

test('it repeats a search back as a query string', () => {
  assert.equal(filterQuery(parseListingSearch({ term: 'sea & sky', medium: 'Oil' })), 'q=sea%20%26%20sky&medium=Oil')
})
