import { test } from 'node:test'
import assert from 'node:assert/strict'
import { placeholderImageSvg } from '../core/listings/placeholder-image.ts'
import { buildTestApp } from '../test/build-test-app.ts'

test('a listing title renders its generated svg with a long cache lifetime', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/placeholders/Blue%20Heron' })

  assert.equal(response.statusCode, 200)
  assert.equal(response.body, placeholderImageSvg('Blue Heron'))
  assert.match(response.headers['content-type'] ?? '', /^image\/svg\+xml/)
  assert.equal(response.headers['cache-control'], 'public, max-age=604800, immutable')
})

test('a slash in the title round-trips through the encoded path', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({
    method: 'GET',
    url: `/placeholders/${encodeURIComponent('Sea / Sky')}`,
  })

  assert.equal(response.statusCode, 200)
  assert.equal(response.body, placeholderImageSvg('Sea / Sky'))
})

test('a hostile title cannot inject markup into the response', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({
    method: 'GET',
    url: `/placeholders/${encodeURIComponent('<script>alert(1)</script>')}`,
  })

  assert.equal(response.statusCode, 200)
  assert.equal(response.body.includes('<script>'), false)
})

test('the response carries the app-wide security headers', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/placeholders/Blue%20Heron' })

  assert.equal(response.headers['x-content-type-options'], 'nosniff')
  assert.match(response.headers['content-security-policy'] ?? '', /default-src 'self'/)
})

test('an empty title is not a 200', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/placeholders/' })

  assert.notEqual(response.statusCode, 200)
})
