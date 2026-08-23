import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin } from '../../test/build-test-app.ts'

test('a url the admin site does not serve answers 404 with the admin page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/nope',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /<title>Not found — Admin<\/title>/)
})

test('every admin id that names nobody answers the same page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const paths = [
    '/admin/sellers/99999',
    '/admin/customers/99999',
    '/admin/listings/99999',
    '/admin/messages/99999',
  ]

  for (const url of paths) {
    const response = await testApp.app.inject({ method: 'GET', url, cookies: admin.cookies })

    assert.equal(response.statusCode, 404, url)
    assert.match(String(response.headers['content-type']), /text\/html/, url)
    assert.match(response.body, /<title>Not found — Admin<\/title>/, url)
  }
})
