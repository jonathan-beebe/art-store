import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import type { Clock } from '../../../clock.ts'
import { buildTestApp, signInAsAdmin, TEST_INSTANT } from '../../../test/build-test-app.ts'
import { createCustomer, createSeller } from '../../../test/commerce-world.ts'

type TravellingClock = Clock & { travelTo(instant: Date): void }

function travellingClock(instant: Date): TravellingClock {
  let current = instant

  return {
    now: () => new Date(current),
    travelTo: (next: Date) => {
      current = next
    },
  }
}

test('the inbox lists this admin threads newest first with the unread count, and hides another admin thread', async (t) => {
  const clock = travellingClock(TEST_INSTANT)
  const testApp = await buildTestApp({ clock })
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const otherAdmin = await signInAsAdmin(testApp, 'annaschmunk@pm.me')
  const context = { db: testApp.db, clock: testApp.clock }

  const sellerA = await createSeller(context, 'Blue Kiln Studio')
  const sellerB = await createSeller(context, 'Second Seller')
  const sellerC = await createSeller(context, 'Third Seller')

  const conversationA = await openConversation(context, {
    kind: 'admin_seller',
    adminId: admin.id,
    sellerId: sellerA,
  })

  clock.travelTo(new Date(TEST_INSTANT.getTime() + 60 * 60 * 1000))

  const conversationB = await openConversation(context, {
    kind: 'admin_seller',
    adminId: admin.id,
    sellerId: sellerB,
  })
  await postMessage(context, {
    conversationId: conversationB.id,
    sender: { type: 'seller', id: sellerB },
    body: 'Any chance of a discount?',
  })

  await openConversation(context, { kind: 'admin_seller', adminId: otherAdmin.id, sellerId: sellerC })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/messages',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.ok(response.body.indexOf(`data-conversation="${conversationB.id}"`) <
    response.body.indexOf(`data-conversation="${conversationA.id}"`))
  assert.match(
    response.body,
    new RegExp(`data-conversation="${conversationB.id}"[^]*?data-unread-count="1"`),
  )
  assert.match(
    response.body,
    new RegExp(`data-conversation="${conversationA.id}"[^]*?data-unread-count="0"`),
  )
  assert.doesNotMatch(response.body, /Third Seller/)
})

test('the thread page renders the messages and clears the unread count on the next load', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context, 'Blue Kiln Studio')
  const conversation = await openConversation(context, {
    kind: 'admin_seller',
    adminId: admin.id,
    sellerId,
  })
  await postMessage(context, {
    conversationId: conversation.id,
    sender: { type: 'seller', id: sellerId },
    body: 'Is this available in a larger size?',
  })

  const before = await testApp.app.inject({
    method: 'GET',
    url: '/admin/messages',
    cookies: admin.cookies,
  })
  assert.match(before.body, /data-unread-messages="1"/)

  const thread = await testApp.app.inject({
    method: 'GET',
    url: `/admin/messages/${conversation.id}`,
    cookies: admin.cookies,
  })
  assert.equal(thread.statusCode, 200)
  assert.match(thread.body, /Is this available in a larger size\?/)
  assert.match(thread.body, /Blue Kiln Studio/)

  const after = await testApp.app.inject({
    method: 'GET',
    url: '/admin/messages',
    cookies: admin.cookies,
  })
  assert.doesNotMatch(after.body, /data-unread-messages/)
})

test('posting a reply appends the message and redirects; an empty body re-renders with a field error and appends nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const conversation = await openConversation(context, {
    kind: 'admin_seller',
    adminId: admin.id,
    sellerId,
  })

  const reply = await testApp.app.inject({
    method: 'POST',
    url: `/admin/messages/${conversation.id}`,
    cookies: admin.cookies,
    payload: { body: 'Thanks for reaching out.' },
  })

  assert.equal(reply.statusCode, 302)
  assert.equal(reply.headers.location, `/admin/messages/${conversation.id}`)

  const afterReply = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .execute()
  assert.equal(afterReply.length, 1)
  assert.equal(afterReply.at(0)?.body, 'Thanks for reaching out.')

  const blank = await testApp.app.inject({
    method: 'POST',
    url: `/admin/messages/${conversation.id}`,
    cookies: admin.cookies,
    payload: { body: '   ' },
  })
  assert.equal(blank.statusCode, 422)
  assert.match(blank.body, /data-field-error="body"[^>]*>Write a message before sending\./)

  const missing = await testApp.app.inject({
    method: 'POST',
    url: `/admin/messages/${conversation.id}`,
    cookies: admin.cookies,
    payload: {},
  })
  assert.equal(missing.statusCode, 422)
  assert.match(missing.body, /data-field-error="body"[^>]*>Write a message before sending\./)

  const afterBlanks = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .execute()
  assert.equal(afterBlanks.length, 1)
})

test('a thread belonging to another admin answers 404 on the GET and the POST', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const otherAdmin = await signInAsAdmin(testApp, 'annaschmunk@pm.me')
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const conversation = await openConversation(context, {
    kind: 'admin_seller',
    adminId: otherAdmin.id,
    sellerId,
  })

  const get = await testApp.app.inject({
    method: 'GET',
    url: `/admin/messages/${conversation.id}`,
    cookies: admin.cookies,
  })
  assert.equal(get.statusCode, 404)

  const post = await testApp.app.inject({
    method: 'POST',
    url: `/admin/messages/${conversation.id}`,
    cookies: admin.cookies,
    payload: { body: 'Hello' },
  })
  assert.equal(post.statusCode, 404)
})

test('a non-numeric conversation id answers 404 on the GET and the POST', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const get = await testApp.app.inject({
    method: 'GET',
    url: '/admin/messages/not-a-number',
    cookies: admin.cookies,
  })
  assert.equal(get.statusCode, 404)

  const post = await testApp.app.inject({
    method: 'POST',
    url: '/admin/messages/not-a-number',
    cookies: admin.cookies,
    payload: { body: 'Hello' },
  })
  assert.equal(post.statusCode, 404)
})

test('POST /admin/sellers/:id/messages opens a thread and a second post reuses it', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)

  const first = await testApp.app.inject({
    method: 'POST',
    url: `/admin/sellers/${sellerId}/messages`,
    cookies: admin.cookies,
    payload: {},
  })
  assert.equal(first.statusCode, 302)
  assert.match(first.headers.location as string, /^\/admin\/messages\/cnv_[0-9A-HJKMNP-TV-Z]{26}$/)

  const second = await testApp.app.inject({
    method: 'POST',
    url: `/admin/sellers/${sellerId}/messages`,
    cookies: admin.cookies,
    payload: {},
  })
  assert.equal(second.headers.location, first.headers.location)
})

test('POST /admin/sellers/:id/messages answers 404 for a seller id that names nobody', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/sellers/999999/messages',
    cookies: admin.cookies,
    payload: {},
  })
  assert.equal(response.statusCode, 404)
})

test('POST /admin/customers/:id/messages opens a thread and a second post reuses it', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const customerId = await createCustomer(context)

  const first = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/messages`,
    cookies: admin.cookies,
    payload: {},
  })
  assert.equal(first.statusCode, 302)
  assert.match(first.headers.location as string, /^\/admin\/messages\/cnv_[0-9A-HJKMNP-TV-Z]{26}$/)

  const second = await testApp.app.inject({
    method: 'POST',
    url: `/admin/customers/${customerId}/messages`,
    cookies: admin.cookies,
    payload: {},
  })
  assert.equal(second.headers.location, first.headers.location)
})

test('POST /admin/customers/:id/messages answers 404 for a customer id that names nobody', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/customers/999999/messages',
    cookies: admin.cookies,
    payload: {},
  })
  assert.equal(response.statusCode, 404)
})

test('the seller page renders a message-seller form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/sellers/${sellerId}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`action="/admin/sellers/${sellerId}/messages"`))
})

test('the customer page renders a message-customer form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const customerId = await createCustomer(context)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/customers/${customerId}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`action="/admin/customers/${customerId}/messages"`))
})
