import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readdir } from 'node:fs/promises'
import { enqueueOutboxMessage } from '../../../delivery/outbox-message.ts'
import { outboxRows } from '../queries/outbox-rows.ts'
import { buildTestApp, signInAsAdmin, type TestApp } from '../../../test/build-test-app.ts'
import { fixtureId } from '../../../test/fixture-ids.ts'

async function queue(testApp: TestApp, subject: string, url: string | null): Promise<void> {
  await enqueueOutboxMessage(
    { db: testApp.db, clock: testApp.clock },
    { recipient: 'artist@example.com', message: { subject, body: `${subject}.`, url } },
  )
}

test('the outbox lists queued messages newest first', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  await queue(testApp, 'Item sold', null)
  await queue(testApp, 'Order shipped', null)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/outbox',
    cookies: admin.cookies,
  })

  const [newest, oldest] = await outboxRows({ db: testApp.db })

  assert.equal(response.statusCode, 200)
  assert.equal(newest?.subject, 'Order shipped')
  assert.equal(
    response.body.indexOf(`data-outbox-message="${newest?.id}"`) <
      response.body.indexOf(`data-outbox-message="${oldest?.id}"`),
    true,
  )
  assert.match(response.body, /Pending/)
})

test('an empty outbox says so', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/outbox',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /Nothing has been queued yet\./)
})

test('a message page shows the rendered message escaped, with its link clickable', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  await queue(testApp, 'Your Art Store sign-in link', 'http://localhost:4000/auth/magic/abc')
  const [queued] = await outboxRows({ db: testApp.db })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/outbox/${queued?.id}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /<a href="http:\/\/localhost:4000\/auth\/magic\/abc" data-message-link/)
  assert.match(
    response.body,
    new RegExp(`Message-ID: &lt;outbox-${queued?.id}@art-store\\.example&gt;`),
  )
  assert.match(response.body, /Subject: Your Art Store sign-in link/)
})

test('a message id naming nothing is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/outbox/${fixtureId('obx', 404)}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('a message id that is not an outbox id is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/outbox/abc',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('draining writes the files, stamps the rows, and says what it sent', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  await queue(testApp, 'Item sold', null)
  const [queued] = await outboxRows({ db: testApp.db })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/outbox/drain',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/admin/outbox')
  assert.match(flashNotice(testApp, response), /^Wrote 1 message\(s\) to /)

  const outboxDir = testApp.app.config.outboxDir
  assert.deepEqual(await readdir(outboxDir), [`${queued?.id}.eml`])

  const row = await testApp.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.notEqual(row.deliveredAt, null)
})

test('draining an empty outbox says nothing was waiting', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/outbox/drain',
    cookies: admin.cookies,
  })

  assert.equal(flashNotice(testApp, response), 'The outbox had nothing waiting to send.')
})

test('the outbox is behind the admin guard', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/outbox' })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location?.startsWith('/admin/login'), true)
})

function flashNotice(
  testApp: TestApp,
  response: { cookies: Array<{ name: string; value?: unknown }> },
): string {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')
  if (cookie === undefined) throw new Error('no flash cookie set')

  const unsigned = testApp.app.unsignCookie(String(cookie.value))
  const flash: unknown = JSON.parse(unsigned.value ?? '{}')

  return (flash as { notice?: string }).notice ?? ''
}
