import { test } from 'node:test'
import assert from 'node:assert/strict'
import path from 'node:path'
import type { LightMyRequestResponse } from 'fastify'
import { loadAssetManifest } from '../http/asset-manifest.ts'
import { buildLoggedTestApp, type LogLine } from '../test/log-lines.ts'
import { signInAsSeller, type SignedInActor, type TestApp } from '../test/build-test-app.ts'

const PUBLIC_DIR = path.join(import.meta.dirname, '..', '..', 'public')

// Reads the manifest static-assets.test.ts (or `npm run assets`) already
// built — not rebuilt here, so this file has nothing to race with another
// test file also touching the shared public dir at import time.
const manifest = loadAssetManifest(PUBLIC_DIR)

/** The `sid` cookie a response wrote back, or undefined when it wrote none. */
function sessionCookie(response: LightMyRequestResponse): string | undefined {
  const cookie = response.cookies.find((candidate) => candidate.name === 'sid')

  return cookie === undefined ? undefined : String(cookie.value)
}

test('a request line carries the incoming x-request-id and echoes it back', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/',
    headers: { 'x-request-id': 'trace-42_A-b' },
  })

  assert.equal(response.headers['x-request-id'], 'trace-42_A-b')
  assert.equal(testApp.logLines.line('http.request', 'will').request_id, 'trace-42_A-b')
})

test('an x-request-id outside the accepted shape is replaced rather than echoed', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/',
    headers: { 'x-request-id': 'trace 42; drop table' },
  })

  const minted = testApp.logLines.line('http.request', 'will').request_id
  assert.notEqual(minted, 'trace 42; drop table')
  assert.match(String(minted), /^[A-Za-z0-9_-]{1,64}$/)
  assert.equal(response.headers['x-request-id'], minted)
})

test('the first response a browser gets mints a sid cookie that lasts a year', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/' })

  const cookie = response.cookies.find((candidate) => candidate.name === 'sid')
  assert.notEqual(cookie, undefined)
  assert.match(String(cookie?.value), /^ses_[0-9A-HJKMNP-TV-Z]{26}$/)
  assert.equal(cookie?.maxAge, 365 * 24 * 60 * 60)
  assert.equal(cookie?.path, '/')
  assert.equal(cookie?.httpOnly, true)
  assert.equal(cookie?.sameSite, 'Lax')
  assert.equal(testApp.logLines.line('http.request', 'will').session_id, cookie?.value)
})

test('a browser presenting a sid keeps it rather than being given another', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const first = await testApp.app.inject({ method: 'GET', url: '/' })
  const sid = sessionCookie(first)

  const second = await testApp.app.inject({ method: 'GET', url: '/', cookies: { sid: sid ?? '' } })

  assert.equal(sessionCookie(second), undefined)
  assert.deepEqual(new Set(testApp.logLines.linesFor('http.request').map((line) => line.session_id)), new Set([sid]))
})

test('a sid that is not a session id is replaced', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/',
    cookies: { sid: 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE' },
  })

  assert.match(String(sessionCookie(response)), /^ses_/)
})

test('signing in and signing out leave the sid alone', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const opened = await testApp.app.inject({ method: 'GET', url: '/seller/login' })
  const sid = sessionCookie(opened)
  assert.match(String(sid), /^ses_/)

  const seller = await signInAsSeller(testApp)
  const signedIn = await testApp.app.inject({
    method: 'GET',
    url: '/seller',
    cookies: { sid: sid ?? '', ...seller.cookies },
  })
  const signedOut = await testApp.app.inject({
    method: 'POST',
    url: '/seller/logout',
    cookies: { sid: sid ?? '', ...seller.cookies },
  })

  assert.equal(sessionCookie(signedIn), undefined)
  assert.equal(sessionCookie(signedOut), undefined)
  assert.deepEqual(
    new Set(testApp.logLines.linesFor('http.request').map((line) => line.session_id)),
    new Set([sid]),
  )
})

test('the path decides which identity a request is made as', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  await testApp.app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })

  const line = testApp.logLines.line('http.request', 'will')
  assert.equal(line.actor_type, 'seller')
  assert.equal(line.actor_id, seller.id)
})

test('a request nobody has an identity for names no actor', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  await testApp.app.inject({ method: 'GET', url: '/seller/login' })

  const line = testApp.logLines.line('http.request', 'will')
  assert.equal(line.actor_type, undefined)
  assert.equal(line.actor_id, undefined)
})

test('a response closes the story with did, its status, and how long it took', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  await testApp.app.inject({ method: 'GET', url: '/' })

  const did = testApp.logLines.line('http.request', 'did')
  assert.deepEqual(did.data, { status: 200 })
  assert.equal(typeof did.duration_ms, 'number')
  assert.equal(did.msg, '🟢 GET / 200')
  assert.equal(testApp.logLines.line('http.request', 'will').msg, '🎬 GET /')
})

test('a request that throws closes the story with failed instead of did', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)

  // The storefront reads listings on the way in, so a missing table is a
  // failure where no route expects one.
  await testApp.db.schema.dropTable('listings').execute()

  const response = await testApp.app.inject({ method: 'GET', url: '/' })

  assert.equal(response.statusCode, 500)
  const failed = log.line('http.request', 'failed')
  assert.equal(failed.level, 'error')
  assert.deepEqual(failed.data, { status: 500 })
  assert.equal(typeof failed.duration_ms, 'number')
  assert.equal(typeof (failed.error as { message?: string }).message, 'string')
  assert.match(String(failed.msg), /^❌ /)
  assert.equal(
    log.linesFor('http.request').some((line) => line.phase === 'did'),
    false,
  )
})

test('a test app is still a whole app', async (t) => {
  const testApp: TestApp = await buildLoggedTestApp()
  t.after(testApp.close)

  assert.equal((await testApp.app.inject({ method: 'GET', url: '/' })).statusCode, 200)
})

test('a request for an unhashed asset writes no log lines, cookie, or request id', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/app.css' })

  assert.equal(response.statusCode, 200)
  assert.deepEqual(testApp.logLines.lines(), [])
  assert.equal(sessionCookie(response), undefined)
  assert.equal(response.headers['x-request-id'], undefined)
})

test('a request for the hashed stylesheet writes no log lines', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: manifest['app.css'] })

  assert.equal(response.statusCode, 200)
  assert.deepEqual(testApp.logLines.lines(), [])
})

test('a missing upload writes no log lines even though it 404s', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/uploads/nothing.png' })

  assert.equal(response.statusCode, 404)
  assert.deepEqual(testApp.logLines.lines(), [])
})

test('a page route that matches nothing still tells its story', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/nothing-here' })

  assert.equal(response.statusCode, 404)
  assert.deepEqual(testApp.logLines.story(), ['http.request will', 'http.request did'])
})

/** Cookie header a real `fetch()` needs to carry one signed-in actor. */
function actorCookieHeader({ cookies }: SignedInActor): string {
  return Object.entries(cookies)
    .map(([name, value]) => `${name}=${value}`)
    .join('; ')
}

/** The `will` line naming a path, among lines a test app has captured so far. */
function willLineFor(lines: LogLine[], path: string): LogLine | undefined {
  return lines.find(
    (line) => line.phase === 'will' && typeof line.msg === 'string' && line.msg.includes(path),
  )
}

/**
 * Every `http.request` line sharing a request id, other than the `will` that
 * opened its story — polled for, since a closing line written from an
 * `onResponse` or abort hook lands asynchronously after the client aborts.
 */
async function closingLinesFor(
  testApp: TestApp & { logLines: { linesFor: (event: 'http.request') => LogLine[] } },
  requestId: unknown,
  timeoutMs: number,
): Promise<LogLine[]> {
  const deadline = Date.now() + timeoutMs

  for (;;) {
    const closing = testApp.logLines
      .linesFor('http.request')
      .filter((line) => line.request_id === requestId && line.phase !== 'will')
    if (closing.length > 0 || Date.now() > deadline) return closing
    await new Promise((resolve) => setTimeout(resolve, 10))
  }
}

test('a client abort on an open stream still closes the story with exactly one did', async (t) => {
  const testApp = await buildLoggedTestApp()
  t.after(testApp.close)
  const baseUrl = await testApp.app.listen({ host: '127.0.0.1', port: 0 })
  const seller = await signInAsSeller(testApp)

  const controller = new AbortController()
  const response = await fetch(`${baseUrl}/seller/events`, {
    headers: { cookie: actorCookieHeader(seller) },
    signal: controller.signal,
  })
  assert.equal(response.status, 200)
  assert.ok(response.body !== null)

  const reader: ReadableStreamDefaultReader<Uint8Array> = response.body.getReader()
  const decoder = new TextDecoder()
  let received = ''
  try {
    while (!received.includes('event: unread')) {
      const chunk = await reader.read()
      if (chunk.done) break
      received += decoder.decode(chunk.value, { stream: true })
    }
  } catch {
    // The stream ended or aborted before the first frame; the assertions
    // below fail on their own if no `will` line ever named this request.
  }

  const opened = willLineFor(testApp.logLines.linesFor('http.request'), '/seller/events')
  assert.notEqual(opened, undefined, 'expected a will line naming /seller/events')
  const requestId = opened?.request_id

  controller.abort()

  const closing = await closingLinesFor(testApp, requestId, 2000)

  assert.equal(
    closing.length,
    1,
    `expected exactly one closing line for request ${String(requestId)}, got ${JSON.stringify(closing)}`,
  )
  const [line] = closing
  assert.ok(line !== undefined)
  assert.equal(line.phase, 'did')
  assert.deepEqual(line.data, { status: 200, disconnected: true })
  assert.equal(typeof line.duration_ms, 'number')
  const durationMs = line.duration_ms as number
  assert.ok(durationMs >= 0 && durationMs < 10000, `duration_ms ${durationMs} is out of range`)
})
