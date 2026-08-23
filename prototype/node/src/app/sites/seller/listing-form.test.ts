import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingDraftFieldsFrom, parseListingFormBody, uploadedImagePart } from './listing-form.ts'

function textField(value: string): unknown {
  return { type: 'field', value }
}

function filePart(filename: string, mimetype = 'image/png'): unknown {
  return { type: 'file', filename, mimetype, toBuffer: async () => Buffer.from('') }
}

test('listingDraftFieldsFrom reads every text field', () => {
  const body = parseListingFormBody({
    title: textField('Harbour at Dusk'),
    description: textField('Oil on canvas.'),
    medium: textField('Oil'),
    dimensions: textField('40 x 60 cm'),
    price: textField('249.00'),
    quantity: textField('2'),
  })

  assert.deepEqual(listingDraftFieldsFrom(body, null), {
    title: 'Harbour at Dusk',
    description: 'Oil on canvas.',
    medium: 'Oil',
    dimensions: '40 x 60 cm',
    price: '249.00',
    quantity: '2',
    imageFormat: null,
  })
})

test('a field left out of the body reads as an empty string', () => {
  const fields = listingDraftFieldsFrom(parseListingFormBody({}), null)

  assert.equal(fields.title, '')
})

test('a text field submitted as something other than text reads as absent', () => {
  const fields = listingDraftFieldsFrom(parseListingFormBody({ title: { type: 'field', value: [1, 2] } }), null)

  assert.equal(fields.title, '')
})

test('a body that is not an object at all reads as an empty form', () => {
  const fields = listingDraftFieldsFrom(parseListingFormBody('nonsense'), null)

  assert.equal(fields.title, '')
  assert.equal(fields.price, '')
})

test('uploadedImagePart reads a file part with a filename', () => {
  const part = uploadedImagePart(parseListingFormBody({ image: filePart('harbour.png') }))

  assert.notEqual(part, null)
  assert.equal(part?.filename, 'harbour.png')
})

test('uploadedImagePart reads an untouched file input as no image', () => {
  assert.equal(uploadedImagePart(parseListingFormBody({ image: filePart('') })), null)
})

test('uploadedImagePart reads a missing field as no image', () => {
  assert.equal(uploadedImagePart(parseListingFormBody({})), null)
})

test('uploadedImagePart reads a text part under the image field as no image', () => {
  assert.equal(uploadedImagePart(parseListingFormBody({ image: textField('harbour.png') })), null)
})

test('listingDraftFieldsFrom carries the sniffed image format the caller passes, not the part itself', () => {
  const body = parseListingFormBody({ image: filePart('harbour.png', 'image/png') })

  assert.equal(listingDraftFieldsFrom(body, 'png').imageFormat, 'png')
})
