import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingDraftFieldsFrom, uploadedImagePart, type MultipartBody } from './listing-form.ts'

function textField(value: string): MultipartBody[string] {
  return { type: 'field', value }
}

function filePart(filename: string, mimetype = 'image/png'): MultipartBody[string] {
  return { type: 'file', filename, mimetype, toBuffer: async () => Buffer.from('') }
}

test('listingDraftFieldsFrom reads every text field', () => {
  const body: MultipartBody = {
    title: textField('Harbour at Dusk'),
    description: textField('Oil on canvas.'),
    medium: textField('Oil'),
    dimensions: textField('40 x 60 cm'),
    price: textField('249.00'),
    quantity: textField('2'),
  }

  assert.deepEqual(listingDraftFieldsFrom(body), {
    title: 'Harbour at Dusk',
    description: 'Oil on canvas.',
    medium: 'Oil',
    dimensions: '40 x 60 cm',
    price: '249.00',
    quantity: '2',
    imageContentType: null,
  })
})

test('a field left out of the body reads as an empty string', () => {
  const fields = listingDraftFieldsFrom({})

  assert.equal(fields.title, '')
})

test('uploadedImagePart reads a file part with a filename', () => {
  const part = uploadedImagePart({ image: filePart('harbour.png') })

  assert.notEqual(part, null)
  assert.equal(part?.filename, 'harbour.png')
})

test('uploadedImagePart reads an untouched file input as no image', () => {
  assert.equal(uploadedImagePart({ image: filePart('') }), null)
})

test('uploadedImagePart reads a missing field as no image', () => {
  assert.equal(uploadedImagePart({}), null)
})

test('listingDraftFieldsFrom carries the uploaded image content type', () => {
  const fields = listingDraftFieldsFrom({ image: filePart('harbour.png', 'image/png') })

  assert.equal(fields.imageContentType, 'image/png')
})
