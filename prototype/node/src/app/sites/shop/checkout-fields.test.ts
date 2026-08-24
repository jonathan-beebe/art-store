import { test } from 'node:test'
import assert from 'node:assert/strict'
import { shippingFromForm } from './checkout-fields.ts'

test('shippingFromForm still reads the submitted body by field name', () => {
  const shipping = shippingFromForm({ shipping_name: 'Ada Lovelace', shipping_city: 'London' })

  assert.equal(shipping.name, 'Ada Lovelace')
  assert.equal(shipping.city, 'London')
})
