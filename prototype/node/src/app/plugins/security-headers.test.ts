import { test } from 'node:test'
import assert from 'node:assert/strict'
import { placeholderImageDataUri } from '../core/listings/placeholder-image.ts'
import { buildTestApp } from '../test/build-test-app.ts'

const EXPECTED_HEADERS = {
  'x-content-type-options': 'nosniff',
  'x-frame-options': 'DENY',
  'referrer-policy': 'strict-origin-when-cross-origin',
  'content-security-policy':
    "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; " +
    "form-action 'self'; frame-ancestors 'none'",
}

test('a storefront page carries every security header', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/' })

  assert.equal(response.statusCode, 200)
  for (const [name, value] of Object.entries(EXPECTED_HEADERS)) {
    assert.equal(response.headers[name], value)
  }
})

test('the health check carries them too', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/health' })

  assert.equal(response.headers['x-content-type-options'], 'nosniff')
  assert.equal(response.headers['content-security-policy'], EXPECTED_HEADERS['content-security-policy'])
})

test('a route that matches nothing carries them too', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/nothing-here' })

  assert.equal(response.statusCode, 404)
  assert.equal(response.headers['x-frame-options'], 'DENY')
  assert.equal(response.headers['referrer-policy'], 'strict-origin-when-cross-origin')
})

test('the placeholder a listing with no photograph renders is allowed by the policy', () => {
  assert.match(placeholderImageDataUri('Night Freight'), /^data:image\/svg\+xml;base64,/)
  assert.match(EXPECTED_HEADERS['content-security-policy'], /img-src 'self' data:/)
})
