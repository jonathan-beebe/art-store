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

test('the medium has a length limit', () => {
  const errors = listingDraftErrors(fields({ medium: 'a'.repeat(256) }))
  assert.equal(errors.medium, 'Keep the medium under 255 characters.')
})

test('the dimensions have a length limit', () => {
  const errors = listingDraftErrors(fields({ dimensions: 'a'.repeat(256) }))
  assert.equal(errors.dimensions, 'Keep the dimensions under 255 characters.')
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

test('a zero price passes validation', () => {
  assert.deepEqual(listingDraftErrors(fields({ price: '0' })), {})
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

test('the top of the quantity range is allowed', () => {
  assert.deepEqual(listingDraftErrors(fields({ quantity: '999' })), {})
})

test('an upload whose bytes sniffed as no known format is refused', () => {
  const errors = listingDraftErrors(fields({ imageFormat: 'unrecognized' }))
  assert.equal(errors.image, 'Upload an image file.')
})

test('an image upload sniffed as a known format is accepted', () => {
  assert.deepEqual(listingDraftErrors(fields({ imageFormat: 'png' })), {})
  assert.deepEqual(listingDraftErrors(fields({ imageFormat: 'jpeg' })), {})
  assert.deepEqual(listingDraftErrors(fields({ imageFormat: 'gif' })), {})
  assert.deepEqual(listingDraftErrors(fields({ imageFormat: 'webp' })), {})
})

test('a form with no upload asks for none', () => {
  assert.deepEqual(listingDraftErrors(fields({ imageFormat: null })), {})
  assert.deepEqual(listingDraftErrors(fields({})), {})
})

test('a wholly empty submission names every required field', () => {
  assert.deepEqual(listingDraftErrors({}), {
    title: 'Enter a title.',
    price: 'The price is an amount in dollars, like 249.00.',
    quantity: 'The quantity is a whole number from 0 to 999.',
  })
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

// Pinned, not fixed: an empty price fails parseDollars' pattern the same way
// it would for any caller. RFCTR-003 owns reconciling this grammar with
// priceError's, which is looser (it accepts a bare '0').
test('parseListingDraft rejects a wholly empty submission on the price', () => {
  assert.throws(() => parseListingDraft({}), /parseDollars/)
})

test('it carries no status or slug', () => {
  const draft = parseListingDraft(fields())
  assert.equal(draft.title, 'Harbour at Dusk')
  assert.equal(draft.priceCents, 24_900)
  assert.equal(draft.quantity, 2)
  assert.equal('status' in draft, false)
  assert.equal('slug' in draft, false)
})
