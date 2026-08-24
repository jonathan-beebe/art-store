import { test } from 'node:test'
import assert from 'node:assert/strict'
import { requestActor, siteActorType, type RequestIdentities } from './request-actor.ts'
import type { AdminId, CustomerId, SellerId } from '../ids/entity-ids.ts'

const SELLER: SellerId = 'sel_01J5X3M9A2K8YB7Q4R6T1V0WZE'
const CUSTOMER: CustomerId = 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZF'
const ADMIN: AdminId = 'adm_01J5X3M9A2K8YB7Q4R6T1V0WZG'

const NOBODY: RequestIdentities = { seller: null, customer: null, admin: null }

test('the path names the side of the marketplace serving it', () => {
  assert.equal(siteActorType('/admin'), 'admin')
  assert.equal(siteActorType('/admin/listings/lst_1'), 'admin')
  assert.equal(siteActorType('/seller'), 'seller')
  assert.equal(siteActorType('/seller/orders'), 'seller')
  assert.equal(siteActorType('/'), 'customer')
  assert.equal(siteActorType('/art/blue-kiln'), 'customer')
  // A path that only starts with the letters is the storefront's.
  assert.equal(siteActorType('/sellers-we-love'), 'customer')
})

test('a request is made as the identity belonging to the side it visits', () => {
  const all: RequestIdentities = { seller: SELLER, customer: CUSTOMER, admin: ADMIN }

  assert.deepEqual(requestActor('/seller/orders', all), { actorType: 'seller', actorId: SELLER })
  assert.deepEqual(requestActor('/admin/payouts', all), { actorType: 'admin', actorId: ADMIN })
  assert.deepEqual(requestActor('/cart', all), { actorType: 'customer', actorId: CUSTOMER })
})

test('a portal request with no identity of its own names whoever the browser is', () => {
  assert.deepEqual(requestActor('/seller/login', { ...NOBODY, customer: CUSTOMER }), {
    actorType: 'customer',
    actorId: CUSTOMER,
  })
  assert.deepEqual(requestActor('/seller/login', { ...NOBODY, admin: ADMIN }), {
    actorType: 'admin',
    actorId: ADMIN,
  })
  assert.deepEqual(requestActor('/admin/login', { ...NOBODY, seller: SELLER }), {
    actorType: 'seller',
    actorId: SELLER,
  })
})

test('a browser that has named nobody names nobody', () => {
  assert.equal(requestActor('/', NOBODY), null)
  assert.equal(requestActor('/admin', NOBODY), null)
})
