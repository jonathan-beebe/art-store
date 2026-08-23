import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isCheckoutComplete, missingCheckoutParts, parseCheckoutForm } from './checkout-form.ts'

function shipping(overrides: Record<string, string | null> = {}): Record<string, string | null> {
  return {
    name: 'Ada Lovelace',
    line1: '12 Analytical Way',
    line2: 'Flat 3',
    city: 'London',
    region: 'Greater London',
    postalCode: 'EC1A 1BB',
    country: 'GB',
    ...overrides,
  }
}

test('a filled form is complete', () => {
  const form = parseCheckoutForm({ email: ' Ada@Example.Test ', shipping: shipping() })

  assert.ok(isCheckoutComplete(form))
  assert.deepEqual(missingCheckoutParts(form), [])
  assert.equal(form.email, 'Ada@Example.Test')
  assert.equal(form.shipping.city, 'London')
})

test('the second address line is optional', () => {
  const form = parseCheckoutForm({ email: 'ada@example.test', shipping: shipping({ line2: '' }) })

  assert.ok(isCheckoutComplete(form))
  assert.equal(form.shipping.line2, null)
})

test('a blank shipping part is missing', () => {
  const form = parseCheckoutForm({
    email: 'ada@example.test',
    shipping: shipping({ city: '   ', country: null }),
  })

  assert.equal(isCheckoutComplete(form), false)
  assert.deepEqual(missingCheckoutParts(form), ['city', 'country'])
})

test('an address that is not an email is missing', () => {
  const form = parseCheckoutForm({ email: 'ada', shipping: shipping() })

  assert.equal(isCheckoutComplete(form), false)
  assert.deepEqual(missingCheckoutParts(form), ['email'])
})

test('an absent shipping part is missing', () => {
  const form = parseCheckoutForm({ email: 'ada@example.test', shipping: {} })

  assert.deepEqual(missingCheckoutParts(form), [
    'name',
    'line1',
    'city',
    'region',
    'postalCode',
    'country',
  ])
})
