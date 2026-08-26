import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import type { ListingId, SellerId } from '../../../core/ids/entity-ids.ts'
import { mustSucceed } from '../../../core/refusal.ts'
import {
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  type TestApp,
} from '../../../test/build-test-app.ts'
import { createForSaleListing } from '../test-fixtures.ts'

async function openListingQuestion(testApp: TestApp, sellerId: SellerId, listingId: ListingId) {
  const { db, clock } = testApp
  const buyer = await signInAsCustomer(testApp, 'buyer@example.com')
  const conversation = await openConversation(
    { db, clock },
    { kind: 'listing_question', sellerId, customerId: buyer.id, listingId },
  )
  mustSucceed(
    await postMessage(
      { db, clock },
      { conversationId: conversation.id, sender: { type: 'customer', id: buyer.id }, body: 'Is this framed?' },
    ),
  )

  return conversation
}

test("the inbox lists the seller's threads newest first with the unread count, and hides other sellers'", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const { db, clock } = testApp
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const admin = await signInAsAdmin(testApp)
  const listing = await createForSaleListing(testApp, seller.id)

  const older = await openConversation({ db, clock }, { kind: 'admin_seller', sellerId: seller.id, adminId: admin.id })
  const newer = await openListingQuestion(testApp, seller.id, listing.id)
  await openConversation({ db, clock }, { kind: 'admin_seller', sellerId: rival.id, adminId: admin.id })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/messages', cookies: seller.cookies })

  assert.equal(response.statusCode, 200)
  assert.equal((response.body.match(/data-conversation="/g) ?? []).length, 2)
  const newerIndex = response.body.indexOf(`data-conversation="${newer.id}"`)
  const olderIndex = response.body.indexOf(`data-conversation="${older.id}"`)
  assert.ok(newerIndex < olderIndex, 'the most recently opened thread renders first')
  assert.match(response.body, new RegExp(`data-conversation="${newer.id}"[\\s\\S]*?data-unread-count="1"`))
})

test('an empty inbox shows nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/messages', cookies: seller.cookies })

  assert.doesNotMatch(response.body, /data-conversation="/)
  assert.match(response.body, /Nothing yet\./)
})

test('the thread page renders the messages and clears the unread count on the next load', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  const conversation = await openListingQuestion(testApp, seller.id, listing.id)

  const first = await testApp.app.inject({
    method: 'GET',
    url: `/seller/messages/${conversation.id}`,
    cookies: seller.cookies,
  })
  assert.equal(first.statusCode, 200)
  assert.match(first.body, /Is this framed\?/)
  assert.match(first.body, /data-unread/)

  const second = await testApp.app.inject({
    method: 'GET',
    url: `/seller/messages/${conversation.id}`,
    cookies: seller.cookies,
  })
  assert.doesNotMatch(second.body, /data-unread/)

  const index = await testApp.app.inject({ method: 'GET', url: '/seller/messages', cookies: seller.cookies })
  assert.doesNotMatch(index.body, /data-unread-count/)
})

test('posting a reply appends the message and redirects to the thread', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  const conversation = await openListingQuestion(testApp, seller.id, listing.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/messages/${conversation.id}`,
    cookies: seller.cookies,
    payload: { body: 'Sure, framed in oak.' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/seller/messages/${conversation.id}`)

  const message = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .where('senderType', '=', 'seller')
    .executeTakeFirstOrThrow()
  assert.equal(message.body, 'Sure, framed in oak.')
})

test('an empty reply re-renders the thread with a field error and appends nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  const conversation = await openListingQuestion(testApp, seller.id, listing.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/messages/${conversation.id}`,
    cookies: seller.cookies,
    payload: { body: '   ' },
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="body"[^>]*>Write a message before sending\./)
  const messages = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversation.id)
    .where('senderType', '=', 'seller')
    .execute()
  assert.equal(messages.length, 0)
})

test('a conversation id belonging to another seller answers 404 on the GET and the POST', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const listing = await createForSaleListing(testApp, rival.id)
  const rivalConversation = await openListingQuestion(testApp, rival.id, listing.id)

  const show = await testApp.app.inject({
    method: 'GET',
    url: `/seller/messages/${rivalConversation.id}`,
    cookies: seller.cookies,
  })
  assert.equal(show.statusCode, 404)

  const post = await testApp.app.inject({
    method: 'POST',
    url: `/seller/messages/${rivalConversation.id}`,
    cookies: seller.cookies,
    payload: { body: 'Hello' },
  })
  assert.equal(post.statusCode, 404)
})

test('a non-numeric conversation id answers 404 on the GET and the POST', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const show = await testApp.app.inject({
    method: 'GET',
    url: '/seller/messages/not-a-number',
    cookies: seller.cookies,
  })
  assert.equal(show.statusCode, 404)

  const post = await testApp.app.inject({
    method: 'POST',
    url: '/seller/messages/not-a-number',
    cookies: seller.cookies,
    payload: { body: 'Hello' },
  })
  assert.equal(post.statusCode, 404)
})

test('/seller/support opens the admin thread and reuses it on a second visit', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await signInAsAdmin(testApp)

  const first = await testApp.app.inject({ method: 'GET', url: '/seller/support', cookies: seller.cookies })
  assert.equal(first.statusCode, 302)
  assert.match(first.headers.location ?? '', /^\/seller\/messages\/cnv_[0-9A-HJKMNP-TV-Z]{26}$/)

  const second = await testApp.app.inject({ method: 'GET', url: '/seller/support', cookies: seller.cookies })
  assert.equal(second.headers.location, first.headers.location)
})

test('/seller/support with no admin seeded flashes and redirects home', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/support', cookies: seller.cookies })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/seller')
})
