import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseListingDraft, type ListingDraftErrors, type ListingDraftFields } from './listing-draft.ts'

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

function errorsOf(overrides: Partial<ListingDraftFields> = {}): ListingDraftErrors {
  const parsed = parseListingDraft(fields(overrides))

  return parsed.ok ? {} : parsed.errors
}

test('a complete form parses into a draft', () => {
  const parsed = parseListingDraft(fields())

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.title, 'Harbour at Dusk')
  assert.equal(parsed.value.priceCents, 24_900)
  assert.equal(parsed.value.quantity, 2)
})

test('a title is required', () => {
  assert.equal(errorsOf({ title: '   ' }).title, 'Enter a title.')
})

test('a title has a length limit', () => {
  assert.equal(errorsOf({ title: 'a'.repeat(256) }).title, 'Keep the title under 255 characters.')
})

test('a description has a length limit', () => {
  assert.equal(
    errorsOf({ description: 'a'.repeat(5001) }).description,
    'Keep the description under 5000 characters.',
  )
})

test('the medium has a length limit', () => {
  assert.equal(errorsOf({ medium: 'a'.repeat(256) }).medium, 'Keep the medium under 255 characters.')
})

test('the dimensions have a length limit', () => {
  assert.equal(
    errorsOf({ dimensions: 'a'.repeat(256) }).dimensions,
    'Keep the dimensions under 255 characters.',
  )
})

test('the price is an amount in dollars', () => {
  const message = 'The price is an amount in dollars, like 249.00.'
  assert.equal(errorsOf({ price: 'free' }).price, message)
  assert.equal(errorsOf({ price: '249.005' }).price, message)
  assert.equal(errorsOf({ price: '12.345' }).price, message)
  assert.equal(errorsOf({ price: '' }).price, message)
})

// One grammar: what the field accepts is exactly what parseDollars parses, so
// a price the form lets through can never reach parseDollars' throw.
test('the price accepts every form parseDollars parses', () => {
  assert.deepEqual(errorsOf({ price: '249' }), {})
  assert.deepEqual(errorsOf({ price: '$249' }), {})
  assert.deepEqual(errorsOf({ price: '1,234.00' }), {})
  assert.deepEqual(errorsOf({ price: '0' }), {})
})

test('a price written with a currency symbol and separators converts to cents', () => {
  const parsed = parseListingDraft(fields({ price: '$1,234.00' }))

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.priceCents, 123_400)
})

test('the quantity is a whole number within range', () => {
  const message = 'The quantity is a whole number from 0 to 999.'
  assert.equal(errorsOf({ quantity: '-1' }).quantity, message)
  assert.equal(errorsOf({ quantity: '1.5' }).quantity, message)
  assert.equal(errorsOf({ quantity: '1000' }).quantity, message)
})

test('a sold out edition may be zero', () => {
  assert.deepEqual(errorsOf({ quantity: '0' }), {})
})

test('the top of the quantity range is allowed', () => {
  assert.deepEqual(errorsOf({ quantity: '999' }), {})
})

test('an upload whose bytes sniffed as no known format is refused', () => {
  assert.equal(errorsOf({ imageFormat: 'unrecognized' }).image, 'Upload an image file.')
})

test('an image upload sniffed as a known format is accepted', () => {
  assert.deepEqual(errorsOf({ imageFormat: 'png' }), {})
  assert.deepEqual(errorsOf({ imageFormat: 'jpeg' }), {})
  assert.deepEqual(errorsOf({ imageFormat: 'gif' }), {})
  assert.deepEqual(errorsOf({ imageFormat: 'webp' }), {})
})

test('a form with no upload asks for none', () => {
  assert.deepEqual(errorsOf({ imageFormat: null }), {})
  assert.deepEqual(errorsOf({}), {})
})

test('a wholly empty submission names every required field and yields no draft', () => {
  const parsed = parseListingDraft({})

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, {
    title: 'Enter a title.',
    price: 'The price is an amount in dollars, like 249.00.',
    quantity: 'The quantity is a whole number from 0 to 999.',
  })
})

test('it trims the text a seller typed', () => {
  const parsed = parseListingDraft(fields({ title: '  Harbour at Dusk  ' }))

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.title, 'Harbour at Dusk')
})

test('a field left blank is stored as nothing', () => {
  const parsed = parseListingDraft(fields({ medium: '', dimensions: '   ' }))

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.medium, null)
  assert.equal(parsed.value.dimensions, null)
})

test('a draft carries no status or slug', () => {
  const parsed = parseListingDraft(fields())

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal('status' in parsed.value, false)
  assert.equal('slug' in parsed.value, false)
})
