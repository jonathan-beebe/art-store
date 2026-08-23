import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, readFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { saveUploadedListingImage } from './listing-image-upload.ts'

test('saveUploadedListingImage writes the buffer under the uploads directory, named for the sniffed format', async (t) => {
  const uploadsDir = await mkdtemp(path.join(tmpdir(), 'listing-image-'))
  t.after(() => import('node:fs/promises').then((fs) => fs.rm(uploadsDir, { recursive: true, force: true })))

  const imagePath = await saveUploadedListingImage(uploadsDir, Buffer.from('fake-png-bytes'), 'png')

  assert.match(imagePath, /^\/uploads\/[0-9a-f-]+\.png$/)
  const written = await readFile(path.join(uploadsDir, path.basename(imagePath)))
  assert.equal(written.toString(), 'fake-png-bytes')
})

test('saveUploadedListingImage names the file by the format, not any filename', async (t) => {
  const uploadsDir = await mkdtemp(path.join(tmpdir(), 'listing-image-'))
  t.after(() => import('node:fs/promises').then((fs) => fs.rm(uploadsDir, { recursive: true, force: true })))

  const imagePath = await saveUploadedListingImage(uploadsDir, Buffer.from('fake-jpeg-bytes'), 'jpeg')

  assert.match(imagePath, /^\/uploads\/[0-9a-f-]+\.jpg$/)
})

test('saveUploadedListingImage creates the uploads directory when it does not exist', async (t) => {
  const parent = await mkdtemp(path.join(tmpdir(), 'listing-image-'))
  t.after(() => import('node:fs/promises').then((fs) => fs.rm(parent, { recursive: true, force: true })))
  const uploadsDir = path.join(parent, 'nested', 'uploads')

  const imagePath = await saveUploadedListingImage(uploadsDir, Buffer.from('fake-gif-bytes'), 'gif')

  const written = await readFile(path.join(uploadsDir, path.basename(imagePath)))
  assert.equal(written.toString(), 'fake-gif-bytes')
})
