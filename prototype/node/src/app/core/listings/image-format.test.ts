import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sniffImageFormat } from './image-format.ts'

test('sniffs a PNG from its signature', () => {
  const bytes = Uint8Array.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x00, 0x00])
  assert.equal(sniffImageFormat(bytes), 'png')
})

test('sniffs a JPEG from its signature', () => {
  const bytes = Uint8Array.from([0xff, 0xd8, 0xff, 0xe0, 0x00, 0x10])
  assert.equal(sniffImageFormat(bytes), 'jpeg')
})

test('sniffs a GIF87a from its signature', () => {
  const bytes = Uint8Array.from(Buffer.from('GIF87a rest of file', 'ascii'))
  assert.equal(sniffImageFormat(bytes), 'gif')
})

test('sniffs a GIF89a from its signature', () => {
  const bytes = Uint8Array.from(Buffer.from('GIF89a rest of file', 'ascii'))
  assert.equal(sniffImageFormat(bytes), 'gif')
})

test('sniffs a WebP from its RIFF/WEBP signature', () => {
  const bytes = Uint8Array.from(Buffer.from('RIFF____WEBPVP8 ', 'ascii'))
  assert.equal(sniffImageFormat(bytes), 'webp')
})

test('a RIFF file that is not WebP sniffs as nothing', () => {
  const bytes = Uint8Array.from(Buffer.from('RIFF____AVI rest', 'ascii'))
  assert.equal(sniffImageFormat(bytes), null)
})

test('SVG bytes sniff as nothing — text markup carries no magic bytes', () => {
  const bytes = Uint8Array.from(Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'utf8'))
  assert.equal(sniffImageFormat(bytes), null)
})

test('HTML bytes sniff as nothing', () => {
  const bytes = Uint8Array.from(Buffer.from('<html><body>evil</body></html>', 'utf8'))
  assert.equal(sniffImageFormat(bytes), null)
})

test('an empty buffer sniffs as nothing', () => {
  assert.equal(sniffImageFormat(Uint8Array.from([])), null)
})

test('a buffer shorter than any signature sniffs as nothing', () => {
  assert.equal(sniffImageFormat(Uint8Array.from([0x89, 0x50])), null)
})
