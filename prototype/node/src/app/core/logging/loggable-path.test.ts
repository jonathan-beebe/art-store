import { test } from 'node:test'
import assert from 'node:assert/strict'
import { loggablePath, pathnameOf } from './loggable-path.ts'

test('pathnameOf drops the query string and keeps a url that has none', () => {
  assert.equal(pathnameOf('/art/blue-kiln?from=home'), '/art/blue-kiln')
  assert.equal(pathnameOf('/art/blue-kiln'), '/art/blue-kiln')
})

test('a url with no secret in it is logged as it arrived', () => {
  assert.equal(loggablePath('/art/blue-kiln?from=home', '/art/:slug'), '/art/blue-kiln')
  assert.equal(loggablePath('/checkout', '/checkout'), '/checkout')
})

test('a url that matched no route is still logged by its path', () => {
  assert.equal(loggablePath('/nothing/here', undefined), '/nothing/here')
})

test('the magic-link route is logged as its pattern, so the token never appears', () => {
  assert.equal(loggablePath(`/auth/magic/${'a'.repeat(64)}`, '/auth/magic/:token'), '/auth/magic/:token')
})
