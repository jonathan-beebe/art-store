import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../../../test/build-test-app.ts'

test('the storefront home page renders in the storefront layout', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/' })

  assert.equal(response.statusCode, 200)
  assert.match(response.headers['content-type'] ?? '', /text\/html/)
  assert.match(response.body, /<title>Original art — Art Store<\/title>/)
  assert.match(response.body, /href="\/app\.css"/)
})

test('the storefront layout prints a flashed magic link in the debug alert', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)
  const link = 'http://localhost:3000/auth/magic/token-from-the-flash'

  const response = await app.inject({
    method: 'GET',
    url: '/',
    cookies: { flash: app.signCookie(JSON.stringify({ debugMagicLink: link })) },
  })

  assert.match(response.body, /role="alert"/)
  assert.match(response.body, new RegExp(link))
})
