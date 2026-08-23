import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingDraftErrors, parseListingDraft, type ListingDraftFields } from './listing-draft.ts'

function fields(overrides: Partial<ListingDraftFields> = {}): ListingDraftFields {
  return {
    title: 'Harbour at Dusk',
    description: 'Oil on canvas.',
    medium: 'Oil',
    dimensions: '40 x 60 cm',
    price: '249.00',
    quantity: '2',
    ...overrides,
  }
}

test('a complete form has no errors', () => {
  assert.deepEqual(listingDraftErrors(fields()), {})
})

test('a title is required', () => {
  assert.equal(listingDraftErrors(fields({ title: '   ' })).title, 'Enter a title.')
})

test('a title has a length limit', () => {
  const errors = listingDraftErrors(fields({ title: 'a'.repeat(256) }))
  assert.equal(errors.title, 'Keep the title under 255 characters.')
})

test('a description has a length limit', () => {
  const errors = listingDraftErrors(fields({ description: 'a'.repeat(5001) }))
  assert.equal(errors.description, 'Keep the description under 5000 characters.')
})

test('the price is an amount in dollars', () => {
  const message = 'The price is an amount in dollars, like 249.00.'
  assert.equal(listingDraftErrors(fields({ price: 'free' })).price, message)
  assert.equal(listingDraftErrors(fields({ price: '$249' })).price, message)
  assert.equal(listingDraftErrors(fields({ price: '249.005' })).price, message)
  assert.equal(listingDraftErrors(fields({ price: '' })).price, message)
})

test('a whole dollar price needs no decimals', () => {
  assert.deepEqual(listingDraftErrors(fields({ price: '249' })), {})
})

test('the quantity is a whole number within range', () => {
  const message = 'The quantity is a whole number from 0 to 999.'
  assert.equal(listingDraftErrors(fields({ quantity: '-1' })).quantity, message)
  assert.equal(listingDraftErrors(fields({ quantity: '1.5' })).quantity, message)
  assert.equal(listingDraftErrors(fields({ quantity: '1000' })).quantity, message)
})

test('a sold out edition may be zero', () => {
  assert.deepEqual(listingDraftErrors(fields({ quantity: '0' })), {})
})

test('an upload that is not an image is refused', () => {
  const errors = listingDraftErrors(fields({ imageContentType: 'application/pdf' }))
  assert.equal(errors.image, 'Upload an image file.')
})

test('an image upload is accepted', () => {
  assert.deepEqual(listingDraftErrors(fields({ imageContentType: 'image/png' })), {})
})

test('a form with no upload asks for none', () => {
  assert.deepEqual(listingDraftErrors(fields({ imageContentType: null })), {})
})

test('it converts the price to cents', () => {
  assert.equal(parseListingDraft(fields()).priceCents, 24_900)
})

test('it trims the text a seller typed', () => {
  const draft = parseListingDraft(fields({ title: '  Harbour at Dusk  ' }))
  assert.equal(draft.title, 'Harbour at Dusk')
})

test('a field left blank is stored as nothing', () => {
  const draft = parseListingDraft(fields({ medium: '', dimensions: '   ' }))
  assert.equal(draft.medium, null)
  assert.equal(draft.dimensions, null)
})

test('it carries no status or slug', () => {
  const draft = parseListingDraft(fields())
  assert.equal(draft.title, 'Harbour at Dusk')
  assert.equal(draft.priceCents, 24_900)
  assert.equal(draft.quantity, 2)
  assert.equal('status' in draft, false)
  assert.equal('slug' in draft, false)
})
