import { test } from 'node:test'
import assert from 'node:assert/strict'
import { shopName } from './shop-name.ts'

test('a named shop reads by its name', () => {
  assert.equal(shopName({ shopName: 'Blue Kiln Studio', email: 'ada@example.test' }), 'Blue Kiln Studio')
})

test('an unnamed shop reads by the address', () => {
  assert.equal(shopName({ shopName: null, email: 'ada@example.test' }), 'ada')
})

test('a blank name reads by the address', () => {
  assert.equal(shopName({ shopName: '   ', email: 'ada@example.test' }), 'ada')
})
