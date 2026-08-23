import { test } from 'node:test'
import assert from 'node:assert/strict'
import { keepLocalRedirect, resolveLocalRedirect } from './local-redirect.ts'

const origin = 'http://localhost:4000'
const fallback = '/account'

test('resolveLocalRedirect falls back for null', () => {
  assert.equal(resolveLocalRedirect(null, { fallback, origin }), fallback)
})

test('resolveLocalRedirect falls back for a blank string', () => {
  assert.equal(resolveLocalRedirect('   ', { fallback, origin }), fallback)
})

test('resolveLocalRedirect keeps a root-relative path', () => {
  assert.equal(resolveLocalRedirect('/checkout?step=2', { fallback, origin }), '/checkout?step=2')
})

test('resolveLocalRedirect keeps an origin-prefixed URL', () => {
  assert.equal(
    resolveLocalRedirect('http://localhost:4000/checkout', { fallback, origin }),
    'http://localhost:4000/checkout',
  )
})

test('resolveLocalRedirect keeps the origin itself', () => {
  assert.equal(resolveLocalRedirect(origin, { fallback, origin }), origin)
})

test('resolveLocalRedirect falls back for a foreign host', () => {
  assert.equal(resolveLocalRedirect('http://evil.example/steal', { fallback, origin }), fallback)
})

test('resolveLocalRedirect falls back for a host that merely starts with the origin', () => {
  assert.equal(
    resolveLocalRedirect('http://localhost:4000.evil.example/steal', { fallback, origin }),
    fallback,
  )
})

test('resolveLocalRedirect falls back for a protocol-relative URL', () => {
  assert.equal(resolveLocalRedirect('//evil.example/steal', { fallback, origin }), fallback)
})

test('resolveLocalRedirect falls back for a backslash-prefixed path', () => {
  assert.equal(resolveLocalRedirect('/\\evil.example/steal', { fallback, origin }), fallback)
})

test('resolveLocalRedirect falls back for an embedded control character', () => {
  assert.equal(
    resolveLocalRedirect('/checkout\nSet-Cookie: x=1', { fallback, origin }),
    fallback,
  )
})

test('keepLocalRedirect keeps a root-relative path', () => {
  assert.equal(keepLocalRedirect('/checkout', origin), '/checkout')
})

test('keepLocalRedirect refuses a foreign host', () => {
  assert.equal(keepLocalRedirect('http://evil.example/steal', origin), null)
})
