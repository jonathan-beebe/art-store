import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from './build-test-app.ts'

test('every site serves its own page and they all share one stylesheet', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const storefront = await app.inject({ method: 'GET', url: '/' })
  const portal = await app.inject({ method: 'GET', url: '/seller' })
  const admin = await app.inject({ method: 'GET', url: '/admin' })

  assert.equal(storefront.statusCode, 200)
  assert.equal(portal.statusCode, 200)
  assert.equal(admin.statusCode, 200)
  assert.match(storefront.body, /Art Store<\/title>/)
  assert.match(portal.body, /Seller portal<\/title>/)
  assert.match(admin.body, /Admin<\/title>/)

  // The stylesheet every layout links is built by the entrypoint, so a serving
  // container answers for it.
  const stylesheet = await app.inject({ method: 'GET', url: '/app.css' })

  assert.equal(stylesheet.statusCode, 200)
  assert.match(stylesheet.headers['content-type'] ?? '', /text\/css/)
})
