import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsSeller } from '../../test/build-test-app.ts'

test('a url the seller portal does not serve answers 404 with the portal page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/nope',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /<title>Not found — Seller portal<\/title>/)
})

test('a listing id this seller does not own answers the portal 404 page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/listings/99999',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /<title>Not found — Seller portal<\/title>/)
})

test('an unmatched seller url answers the portal page with no seller cookie', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/nope' })

  assert.equal(response.statusCode, 404)
  assert.match(response.body, /<title>Not found — Seller portal<\/title>/)
})
