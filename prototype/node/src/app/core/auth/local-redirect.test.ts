import { test } from 'node:test'
import assert from 'node:assert/strict'
import { allowsPath, keepLocalRedirect, resolveLocalRedirect } from './local-redirect.ts'

const origin = 'http://localhost:4000'
const fallback = '/account'

test('resolveLocalRedirect falls back for null', () => {
  assert.equal(resolveLocalRedirect(null, { actorType: 'customer', fallback, origin }), fallback)
})

test('resolveLocalRedirect falls back for a blank string', () => {
  assert.equal(resolveLocalRedirect('   ', { actorType: 'customer', fallback, origin }), fallback)
})

test('resolveLocalRedirect keeps a root-relative path', () => {
  assert.equal(
    resolveLocalRedirect('/checkout?step=2', { actorType: 'customer', fallback, origin }),
    '/checkout?step=2',
  )
})

test('resolveLocalRedirect keeps an origin-prefixed URL', () => {
  assert.equal(
    resolveLocalRedirect('http://localhost:4000/checkout', { actorType: 'customer', fallback, origin }),
    'http://localhost:4000/checkout',
  )
})

test('resolveLocalRedirect keeps the origin itself', () => {
  assert.equal(resolveLocalRedirect(origin, { actorType: 'customer', fallback, origin }), origin)
})

test('resolveLocalRedirect falls back for a foreign host', () => {
  assert.equal(
    resolveLocalRedirect('http://evil.example/steal', { actorType: 'customer', fallback, origin }),
    fallback,
  )
})

test('resolveLocalRedirect falls back for a host that merely starts with the origin', () => {
  assert.equal(
    resolveLocalRedirect('http://localhost:4000.evil.example/steal', {
      actorType: 'customer',
      fallback,
      origin,
    }),
    fallback,
  )
})

test('resolveLocalRedirect falls back for a protocol-relative URL', () => {
  assert.equal(
    resolveLocalRedirect('//evil.example/steal', { actorType: 'customer', fallback, origin }),
    fallback,
  )
})

test('resolveLocalRedirect falls back for a backslash-prefixed path', () => {
  assert.equal(
    resolveLocalRedirect('/\\evil.example/steal', { actorType: 'customer', fallback, origin }),
    fallback,
  )
})

test('resolveLocalRedirect falls back for an embedded control character', () => {
  assert.equal(
    resolveLocalRedirect('/checkout\nSet-Cookie: x=1', { actorType: 'customer', fallback, origin }),
    fallback,
  )
})

test('resolveLocalRedirect falls back for a path outside the actor own prefixes', () => {
  assert.equal(
    resolveLocalRedirect('/admin/orders', { actorType: 'customer', fallback, origin }),
    fallback,
  )
})

test('keepLocalRedirect keeps a root-relative path', () => {
  assert.equal(keepLocalRedirect('/checkout', 'customer', origin), '/checkout')
})

test('keepLocalRedirect refuses a foreign host', () => {
  assert.equal(keepLocalRedirect('http://evil.example/steal', 'customer', origin), null)
})

test('keepLocalRedirect refuses a seller-site redirect for a customer', () => {
  assert.equal(keepLocalRedirect('/seller/listings', 'customer', origin), null)
})

test('keepLocalRedirect refuses an admin-site redirect for a customer', () => {
  assert.equal(keepLocalRedirect('/admin/orders', 'customer', origin), null)
})

test('keepLocalRedirect refuses an admin-site redirect for a seller', () => {
  assert.equal(keepLocalRedirect('/admin/orders', 'seller', origin), null)
})

test('keepLocalRedirect refuses a seller-site redirect for an admin', () => {
  assert.equal(keepLocalRedirect('/seller/listings', 'admin', origin), null)
})

test('keepLocalRedirect keeps a customer path for a seller', () => {
  assert.equal(keepLocalRedirect('/orders/7', 'seller', origin), '/orders/7')
})

test('keepLocalRedirect keeps a customer path for an admin', () => {
  assert.equal(keepLocalRedirect('/orders/7', 'admin', origin), '/orders/7')
})

test('keepLocalRedirect keeps a seller path for a seller', () => {
  assert.equal(keepLocalRedirect('/seller/listings', 'seller', origin), '/seller/listings')
})

test('keepLocalRedirect keeps an admin path for an admin', () => {
  assert.equal(keepLocalRedirect('/admin/orders', 'admin', origin), '/admin/orders')
})

test('keepLocalRedirect checks the path of an origin-prefixed URL, not just its host', () => {
  assert.equal(keepLocalRedirect(`${origin}/admin/orders`, 'customer', origin), null)
})

test('keepLocalRedirect refuses a seller-site redirect for a customer with a query string', () => {
  assert.equal(keepLocalRedirect('/seller/listings?status=active', 'customer', origin), null)
})

test('keepLocalRedirect checks the path before any fragment, since a browser never sends one to the server', () => {
  assert.equal(keepLocalRedirect('/orders/7#/admin', 'customer', origin), '/orders/7#/admin')
  assert.equal(keepLocalRedirect('/admin/orders#/orders/7', 'customer', origin), null)
})

test('allowsPath: a seller may reach a seller path and a customer path, not an admin path', () => {
  assert.equal(allowsPath('seller', '/seller/listings'), true)
  assert.equal(allowsPath('seller', '/orders/7'), true)
  assert.equal(allowsPath('seller', '/admin'), false)
  assert.equal(allowsPath('seller', '/admin/orders'), false)
})

test('allowsPath: a customer may reach neither a seller nor an admin path', () => {
  assert.equal(allowsPath('customer', '/orders/7'), true)
  assert.equal(allowsPath('customer', '/seller'), false)
  assert.equal(allowsPath('customer', '/seller/listings'), false)
  assert.equal(allowsPath('customer', '/admin'), false)
  assert.equal(allowsPath('customer', '/admin/orders'), false)
})

test('allowsPath: an admin may reach an admin path and a customer path, not a seller path', () => {
  assert.equal(allowsPath('admin', '/admin/orders'), true)
  assert.equal(allowsPath('admin', '/orders/7'), true)
  assert.equal(allowsPath('admin', '/seller'), false)
  assert.equal(allowsPath('admin', '/seller/listings'), false)
})

test('allowsPath: a prefix match requires a path boundary, not merely a shared prefix', () => {
  assert.equal(allowsPath('customer', '/sellers'), true)
  assert.equal(allowsPath('customer', '/administration'), true)
})
