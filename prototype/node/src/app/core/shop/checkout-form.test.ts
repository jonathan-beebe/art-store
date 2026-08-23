import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseCheckoutForm } from './checkout-form.ts'

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

test('a filled form parses into a complete value', () => {
  const parsed = parseCheckoutForm({ email: ' Ada@Example.Test ', shipping: shipping() })

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.email, 'Ada@Example.Test')
  assert.equal(parsed.value.shipping.city, 'London')
})

test('the second address line is optional', () => {
  const parsed = parseCheckoutForm({ email: 'ada@example.test', shipping: shipping({ line2: '' }) })

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.shipping.line2, null)
})

test('a blank shipping part is an error, not a value', () => {
  const parsed = parseCheckoutForm({
    email: 'ada@example.test',
    shipping: shipping({ city: '   ', country: null }),
  })

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, ['city', 'country'])
})

test('an address that is not an email is an error', () => {
  const parsed = parseCheckoutForm({ email: 'ada', shipping: shipping() })

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, ['email'])
})

test('an absent shipping part is an error', () => {
  const parsed = parseCheckoutForm({ email: 'ada@example.test', shipping: {} })

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, ['name', 'line1', 'city', 'region', 'postalCode', 'country'])
})

test('a refused form hands back what was entered, trimmed, for the page to show again', () => {
  const parsed = parseCheckoutForm({
    email: '  ada@example.test  ',
    shipping: shipping({ city: '  London  ', country: '' }),
  })

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.equal(parsed.entered.email, 'ada@example.test')
  assert.equal(parsed.entered.shipping.city, 'London')
  assert.equal(parsed.entered.shipping.country, '')
})
