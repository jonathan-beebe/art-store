import { test } from 'node:test'
import assert from 'node:assert/strict'
import { ACTOR_TYPES, ACTOR_SITES } from './actor-type.ts'

test('the three actor types exist in order', () => {
  assert.deepEqual(ACTOR_TYPES, ['seller', 'customer', 'admin'])
})

test('each actor signs in, lands, and signs out on its own site', () => {
  assert.deepEqual(ACTOR_SITES.seller, {
    homePath: '/seller',
    loginPath: '/seller/login',
    signedOutPath: '/seller/login',
  })
  assert.deepEqual(ACTOR_SITES.admin, {
    homePath: '/admin',
    loginPath: '/admin/login',
    signedOutPath: '/admin/login',
  })
})

test('a customer signing out lands on the storefront rather than a sign-in form', () => {
  assert.deepEqual(ACTOR_SITES.customer, {
    homePath: '/account',
    loginPath: '/login',
    signedOutPath: '/',
  })
})

test('every actor type has a site entry', () => {
  for (const actorType of ACTOR_TYPES) {
    assert.ok(ACTOR_SITES[actorType])
  }
})
