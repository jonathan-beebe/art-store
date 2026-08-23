import { test } from 'node:test'
import assert from 'node:assert/strict'
import { SHIPPING_ADDRESS_PARTS, missingAddressParts, type ShippingAddress } from './shipping-address.ts'

function address(overrides: Partial<ShippingAddress> = {}): ShippingAddress {
  return {
    name: 'Ada Lovelace',
    line1: '12 Analytical Way',
    line2: null,
    city: 'London',
    region: 'Greater London',
    postalCode: 'EC1A 1BB',
    country: 'GB',
    ...overrides,
  }
}

test('SHIPPING_ADDRESS_PARTS names every part an order copies', () => {
  assert.deepEqual(SHIPPING_ADDRESS_PARTS, ['name', 'line1', 'line2', 'city', 'region', 'postalCode', 'country'])
})

test('a complete address is missing nothing', () => {
  assert.deepEqual(missingAddressParts(address()), [])
})

test('line2 is not required', () => {
  assert.deepEqual(missingAddressParts(address({ line2: null })), [])
})

test('a blank required part is reported missing', () => {
  assert.deepEqual(missingAddressParts(address({ city: '' })), ['city'])
})

test('every blank required part is reported', () => {
  assert.deepEqual(missingAddressParts(address({ name: '', postalCode: '  ' })), ['name', 'postalCode'])
})
