import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingFormFieldsView } from './listing-form-fields-view.ts'

const FIELDS = { title: 'Harbour at dusk', description: '', medium: '', dimensions: '', price: '240', quantity: '1' }

test('a field with no error carries its value and no errorId', () => {
  const view = listingFormFieldsView(FIELDS, {})

  assert.equal(view.title.value, 'Harbour at dusk')
  assert.equal(view.title.errorId, null)
  assert.equal(view.description.errorId, null)
})

test('a field with an error carries the id field-error.ejs gives its message', () => {
  const view = listingFormFieldsView(FIELDS, { title: 'Enter a title.' })

  assert.equal(view.title.errorId, 'listing_title-error')
})

test('the image field carries only an errorId, no value', () => {
  const view = listingFormFieldsView(FIELDS, { image: 'Upload an image file.' })

  assert.deepEqual(view.image, { errorId: 'listing_image-error' })
})

test('a missing field reads as an empty value', () => {
  const view = listingFormFieldsView({}, {})

  assert.equal(view.price.value, '')
  assert.equal(view.quantity.value, '')
})

test('every one of the seven controls gets its own errorId when it errors', () => {
  const errors = {
    title: 'a',
    description: 'b',
    medium: 'c',
    dimensions: 'd',
    price: 'e',
    quantity: 'f',
    image: 'g',
  }
  const view = listingFormFieldsView(FIELDS, errors)

  assert.equal(view.title.errorId, 'listing_title-error')
  assert.equal(view.description.errorId, 'listing_description-error')
  assert.equal(view.medium.errorId, 'listing_medium-error')
  assert.equal(view.dimensions.errorId, 'listing_dimensions-error')
  assert.equal(view.price.errorId, 'listing_price-error')
  assert.equal(view.quantity.errorId, 'listing_quantity-error')
  assert.equal(view.image.errorId, 'listing_image-error')
})
