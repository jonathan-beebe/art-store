import { test } from 'node:test'
import assert from 'node:assert/strict'
import { placeholderImagePath, placeholderImageSvg, listingImageSource } from './placeholder-image.ts'

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

test('the same title produces the same placeholder path', () => {
  assert.equal(placeholderImagePath('Blue Heron'), placeholderImagePath('Blue Heron'))
})

test('the path percent-encodes spaces and unicode in the title', () => {
  assert.equal(placeholderImagePath('Blue Heron'), '/placeholders/Blue%20Heron')
  assert.equal(placeholderImagePath('Café Nuit'), '/placeholders/Caf%C3%A9%20Nuit')
})

test('the path percent-encodes a slash in the title so it stays one segment', () => {
  assert.equal(placeholderImagePath('Sea / Sky'), '/placeholders/Sea%20%2F%20Sky')
})

test('listingImageSource prefers an uploaded path', () => {
  assert.equal(listingImageSource('/uploads/foo.png', 'Blue Heron'), '/uploads/foo.png')
})

test('listingImageSource falls back to the placeholder path when there is no path', () => {
  assert.equal(listingImageSource(null, 'Blue Heron'), placeholderImagePath('Blue Heron'))
})
