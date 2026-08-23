import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin, signInAsSeller } from '../test/build-test-app.ts'

test('each site renders the page name it was asked for from its own templates', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)
  // The admin site is behind its guard, so reaching its page means signing in.
  const operator = await signInAsAdmin(testApp)

  const storefront = await app.inject({ method: 'GET', url: '/' })
  const admin = await app.inject({ method: 'GET', url: '/admin', cookies: operator.cookies })

  assert.match(storefront.body, /Original art — Art Store<\/title>/)
  assert.match(admin.body, /Overview — Admin<\/title>/)
})

test('a page renders inside its own site layout and no other', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)
  const operator = await signInAsAdmin(testApp)

  const storefront = await app.inject({ method: 'GET', url: '/' })
  const admin = await app.inject({ method: 'GET', url: '/admin', cookies: operator.cookies })

  assert.match(storefront.body, /Original work from independent artists/)
  assert.doesNotMatch(admin.body, /Original work from independent artists/)
  assert.match(admin.body, /Art Store admin/)
  assert.doesNotMatch(storefront.body, /Art Store admin/)
})

test('every site layout is handed the flash', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)
  // The seller portal is behind its guard, so reaching it means signing in.
  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)
  const cookies = {
    ...seller.cookies,
    ...operator.cookies,
    flash: app.signCookie(JSON.stringify({ notice: 'Saved' })),
  }

  const storefront = await app.inject({ method: 'GET', url: '/', cookies })
  const portal = await app.inject({ method: 'GET', url: '/seller', cookies })
  const admin = await app.inject({ method: 'GET', url: '/admin', cookies })

  assert.match(storefront.body, /Saved/)
  assert.match(portal.body, /Saved/)
  assert.match(admin.body, /Saved/)
})
