import { test } from 'node:test'
import assert from 'node:assert/strict'
import { z } from 'zod'
import { buildTestApp, signInAsAdmin } from '../test/build-test-app.ts'
import { errorPageView, failureStatusCode, isRefusedRouteParams } from './error-pages.ts'

/** A `ZodError` as a handler's `.parse` throws one. */
function zodError(): unknown {
  return z.object({ email: z.string() }).safeParse({ email: ['a@b.com', 'c@d.com'] }).error
}

test('a rejected parse is a bad request', () => {
  assert.equal(failureStatusCode(zodError()), 400)
})

test('an error carrying a client status keeps it', () => {
  assert.equal(failureStatusCode(Object.assign(new Error('too large'), { statusCode: 413 })), 413)
  assert.equal(failureStatusCode(Object.assign(new Error('media'), { statusCode: 415 })), 415)
})

test('anything else is a server fault', () => {
  assert.equal(failureStatusCode(new Error('boom')), 500)
  assert.equal(failureStatusCode(Object.assign(new Error('gone'), { statusCode: 503 })), 500)
  assert.equal(failureStatusCode(Object.assign(new Error('odd'), { statusCode: '400' })), 500)
  assert.equal(failureStatusCode('not an error at all'), 500)
})

test('a client failure names the request, a server failure names nothing', () => {
  const client = errorPageView(400)
  const server = errorPageView(413)
  const fault = errorPageView(500)

  assert.match(client.title, /request/i)
  assert.deepEqual(server, client)
  assert.notEqual(fault.title, client.title)
})

test('only a failure the params schema raised is read as a url that names nothing', () => {
  assert.equal(isRefusedRouteParams({ validationContext: 'params' }), true)
  assert.equal(isRefusedRouteParams({ validationContext: 'body' }), false)
  assert.equal(isRefusedRouteParams({ validationContext: 'querystring' }), false)
  assert.equal(isRefusedRouteParams(new Error('boom')), false)
  assert.equal(isRefusedRouteParams(null), false)
  assert.equal(isRefusedRouteParams('params'), false)
})

test("a url segment the params schema refuses answers the site's not-found page", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/sellers/not-a-number',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
  assert.match(response.body, /Not found/)
  assert.doesNotMatch(response.body, /That request did not work/)
})

test('a refused query string is a bad request, not a page that does not exist', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/orders?status=nonsense',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
  assert.match(response.body, /That request did not work/)
})

test('a duplicated form field answers 400 with the storefront page, not the schema', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/login',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    payload: 'email=a%40b.com&email=c%40d.com',
  })

  assert.equal(response.statusCode, 400)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /— Art Store<\/title>/)
  assert.doesNotMatch(response.body, /invalid_type/)
  assert.doesNotMatch(response.body, /"expected"/)
})

test('a body Fastify refuses keeps its own status and gets the page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/login',
    headers: { 'content-type': 'application/xml' },
    payload: '<email/>',
  })

  assert.equal(response.statusCode, 415)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /— Art Store<\/title>/)
})

test('/health still answers json', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/health' })

  assert.equal(response.statusCode, 200)
  assert.match(String(response.headers['content-type']), /application\/json/)
  assert.equal(response.json<{ status: string }>().status, 'ok')
})

test('a failure on a route with no layout answers plain text, not a schema dump', async (t) => {
  const testApp = await buildTestApp()
  t.after(async () => {
    await testApp.app.close()
  })

  await testApp.db.destroy()

  const response = await testApp.app.inject({ method: 'GET', url: '/health' })

  assert.equal(response.statusCode, 500)
  assert.match(String(response.headers['content-type']), /text\/plain/)
  assert.equal(response.body, 'Something went wrong')
})

test('an unexpected failure answers 500 with a page that leaks no detail', async (t) => {
  const testApp = await buildTestApp()
  t.after(async () => {
    await testApp.app.close()
  })

  // A closed database is the shortest route to a failure no handler expects:
  // the identity hook's query throws before any page renders.
  await testApp.db.destroy()

  const response = await testApp.app.inject({ method: 'GET', url: '/' })

  assert.equal(response.statusCode, 500)
  assert.match(String(response.headers['content-type']), /text\/html/)
  assert.match(response.body, /— Art Store<\/title>/)
  assert.doesNotMatch(response.body, /at .*\.ts:/)
  assert.doesNotMatch(response.body, /statusCode/)
})
