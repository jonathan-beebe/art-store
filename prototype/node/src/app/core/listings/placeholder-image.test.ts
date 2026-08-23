import { test } from 'node:test'
import assert from 'node:assert/strict'
import { placeholderImageSvg, placeholderImageDataUri, listingImageSource } from './placeholder-image.ts'

test('the same title renders the same svg', () => {
  assert.equal(placeholderImageSvg('Blue Heron'), placeholderImageSvg('Blue Heron'))
})

test('different titles render different svgs', () => {
  assert.notEqual(placeholderImageSvg('Blue Heron'), placeholderImageSvg('Red Fox'))
})

test('the svg carries the title as an accessible label', () => {
  const svg = placeholderImageSvg('Mug & Bowl')
  assert.match(svg, /aria-label="Mug &amp; Bowl"/)
  assert.equal(svg.trimStart().startsWith('<svg'), true)
})

test('the label is escaped so a hostile title cannot inject markup', () => {
  const svg = placeholderImageSvg('<script>alert(1)</script>')
  assert.equal(svg.includes('<script>'), false)
})

test('the data uri is base64 svg', () => {
  const uri = placeholderImageDataUri('Blue Heron')
  const prefix = 'data:image/svg+xml;base64,'
  assert.equal(uri.startsWith(prefix), true)
  const decoded = Buffer.from(uri.slice(prefix.length), 'base64').toString('utf8')
  assert.equal(decoded, placeholderImageSvg('Blue Heron'))
})

test('listingImageSource prefers an uploaded path', () => {
  assert.equal(listingImageSource('/uploads/foo.png', 'Blue Heron'), '/uploads/foo.png')
})

test('listingImageSource falls back to the placeholder when there is no path', () => {
  assert.equal(listingImageSource(null, 'Blue Heron'), placeholderImageDataUri('Blue Heron'))
})
