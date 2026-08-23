import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, readFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { extensionForImage, saveUploadedListingImage } from './listing-image-upload.ts'

test('extensionForImage reads a known content type', () => {
  assert.equal(extensionForImage('image/png', 'anything'), 'png')
  assert.equal(extensionForImage('image/jpeg', 'anything'), 'jpg')
  assert.equal(extensionForImage('image/webp', 'anything'), 'webp')
})

test('extensionForImage falls back to the filename for an unrecognized type', () => {
  assert.equal(extensionForImage('application/octet-stream', 'harbour.gif'), 'gif')
})

test('extensionForImage falls back to a default when neither names one', () => {
  assert.equal(extensionForImage('application/octet-stream', 'harbour'), 'bin')
})

test('saveUploadedListingImage writes the buffer under the uploads directory', async (t) => {
  const uploadsDir = await mkdtemp(path.join(tmpdir(), 'listing-image-'))
  t.after(() => import('node:fs/promises').then((fs) => fs.rm(uploadsDir, { recursive: true, force: true })))

  const imagePath = await saveUploadedListingImage(uploadsDir, Buffer.from('fake-png-bytes'), 'image/png', 'harbour.png')

  assert.match(imagePath, /^\/uploads\/[0-9a-f-]+\.png$/)
  const written = await readFile(path.join(uploadsDir, path.basename(imagePath)))
  assert.equal(written.toString(), 'fake-png-bytes')
})
