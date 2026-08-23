import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../../test/build-test-app.ts'

test('a url the storefront does not serve answers 404 with the storefront page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/definitely-not-a-route' })

  assert.equal(response.statusCode, 404)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /<title>Not found — Art Store<\/title>/)
  assert.match(response.body, /Nothing here/)
})

test('a mistyped url mints no customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const before = await customerCount(testApp)
  const response = await testApp.app.inject({ method: 'GET', url: '/definitely-not-a-route' })

  assert.equal(response.statusCode, 404)
  assert.equal(await customerCount(testApp), before)
})

test('a slug that names no listing answers the same 404 page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/art/nothing-here' })

  assert.equal(response.statusCode, 404)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /Nothing here/)
})

async function customerCount({ db }: Awaited<ReturnType<typeof buildTestApp>>): Promise<number> {
  const rows = await db.selectFrom('customers').select('id').execute()

  return rows.length
}
