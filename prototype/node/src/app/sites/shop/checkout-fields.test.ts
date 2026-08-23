import { test } from 'node:test'
import assert from 'node:assert/strict'
import { missingFieldLabels, shippingFromForm } from './checkout-fields.ts'

test('it words a shipping part by its field label', () => {
  assert.deepEqual(missingFieldLabels(['name', 'postalCode']), ['Full name', 'Postal code'])
})

test('it words the email part by its own label', () => {
  assert.deepEqual(missingFieldLabels(['email']), ['Email address'])
})

test('an empty list of missing parts words to nothing', () => {
  assert.deepEqual(missingFieldLabels([]), [])
})

test('a part with no field behind it falls back to itself', () => {
  assert.deepEqual(missingFieldLabels(['unknown']), ['unknown'])
})

test('shippingFromForm still reads the submitted body by field name', () => {
  const shipping = shippingFromForm({ shipping_name: 'Ada Lovelace', shipping_city: 'London' })

  assert.equal(shipping.name, 'Ada Lovelace')
  assert.equal(shipping.city, 'London')
})
