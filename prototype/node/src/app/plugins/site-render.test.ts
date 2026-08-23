import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../test/build-test-app.ts'

test('each site renders the page name it was asked for from its own templates', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const storefront = await app.inject({ method: 'GET', url: '/' })
  const admin = await app.inject({ method: 'GET', url: '/admin' })

  assert.match(storefront.body, /Original art — Art Store<\/title>/)
  assert.match(admin.body, /Overview — Admin<\/title>/)
})

test('a page renders inside its own site layout and no other', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const storefront = await app.inject({ method: 'GET', url: '/' })
  const admin = await app.inject({ method: 'GET', url: '/admin' })

  assert.match(storefront.body, /Original work from independent artists/)
  assert.doesNotMatch(admin.body, /Original work from independent artists/)
  assert.match(admin.body, /Art Store admin/)
  assert.doesNotMatch(storefront.body, /Art Store admin/)
})

test('every site layout is handed the flash', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)
  const cookies = { flash: app.signCookie(JSON.stringify({ notice: 'Saved' })) }

  const storefront = await app.inject({ method: 'GET', url: '/', cookies })
  const portal = await app.inject({ method: 'GET', url: '/seller', cookies })
  const admin = await app.inject({ method: 'GET', url: '/admin', cookies })

  assert.match(storefront.body, /Saved/)
  assert.match(portal.body, /Saved/)
  assert.match(admin.body, /Saved/)
})
