import { test } from 'node:test'
import assert from 'node:assert/strict'
import { postedMessage, postMessage } from '../../../actions/messaging/post-message.ts'
import type {
  ConversationId,
  CustomerId,
} from '../../../core/ids/entity-ids.ts'
import { parsePrefixedId } from '../../../core/ids/prefixed-id.ts'
import { cents } from '../../../core/money.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  type TestApp,
} from '../../../test/build-test-app.ts'
import {
  blockCustomer,
  cartWithArtwork,
  listArtwork,
  payForOrder,
  placeCustomerOrder,
} from '../storefront-fixtures.ts'

function conversationIdFrom(location: string | undefined): ConversationId {
  const parsed = parsePrefixedId('cnv', location?.split('/').pop() ?? '')
  if (parsed.outcome !== 'id') throw new Error(`no conversation id in redirect "${location}"`)

  return parsed.id
}

async function orderWithFulfillment(
  testApp: TestApp,
  customerId: CustomerId,
  title = 'Harbour at dusk',
) {
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const listing = await listArtwork(testApp, { sellerId: seller.id, title, priceCents: cents(24_000) })
  const cartId = await cartWithArtwork(testApp, { customerId, listingId: listing.id })
  const order = await placeCustomerOrder(testApp, { cartId, customerId })
  await payForOrder(testApp, { orderId: order.id })

  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  return { seller, listing, order, fulfillment }
}

async function askAQuestion(
  testApp: TestApp,
  input: { slug: string; body: string; cookies: Record<string, string> },
) {
  const response = await testApp.app.inject({
    method: 'POST',
    url: `/art/${input.slug}/questions`,
    payload: { body: input.body },
    cookies: input.cookies,
  })

  return { response, conversationId: conversationIdFrom(response.headers.location) }
}

test('the inbox lists the customer’s threads newest first with the unread count, and nothing belonging to another customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp, 'mine@example.test')
  const stranger = await signInAsCustomer(testApp, 'stranger@example.test')
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await listArtwork(testApp, { sellerId: seller.id, title: 'Kiln study' })

  await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: customer.cookies,
  })
  const { conversationId: kilnConversationId } = await askAQuestion(testApp, {
    slug: 'kiln-study',
    body: 'What clay is this?',
    cookies: customer.cookies,
  })
  postedMessage(
    await postMessage(testApp, {
      conversationId: kilnConversationId,
      sender: { type: 'seller', id: seller.id },
      body: 'Stoneware.',
    }),
  )

  const strangerResponse = await testApp.app.inject({
    method: 'GET',
    url: '/messages',
    cookies: stranger.cookies,
  })
  assert.doesNotMatch(strangerResponse.body, /Kiln study/)
  assert.doesNotMatch(strangerResponse.body, /Harbour at dusk/)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/messages',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 200)
  const kilnAt = response.body.indexOf('Kiln study')
  const harbourAt = response.body.indexOf('Harbour at dusk')
  assert.ok(kilnAt !== -1 && harbourAt !== -1)
  assert.ok(kilnAt < harbourAt, 'the conversation with the newer message prints first')
  assert.match(response.body, /data-unread-count="1"/)
})

test('the thread page renders the messages and clears the unread count on the next load', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const { conversationId } = await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: customer.cookies,
  })
  postedMessage(
    await postMessage(testApp, {
      conversationId,
      sender: { type: 'seller', id: seller.id },
      body: 'No, it ships unframed.',
    }),
  )

  const threadResponse = await testApp.app.inject({
    method: 'GET',
    url: `/messages/${conversationId}`,
    cookies: customer.cookies,
  })

  assert.equal(threadResponse.statusCode, 200)
  assert.match(threadResponse.body, /Is this framed\?/)
  assert.match(threadResponse.body, /No, it ships unframed\./)

  const inboxResponse = await testApp.app.inject({
    method: 'GET',
    url: '/messages',
    cookies: customer.cookies,
  })
  assert.match(inboxResponse.body, /data-unread-count="0"/)
})

test('an id naming somebody else’s thread and a non-numeric id both answer 404 on the GET and the POST', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const owner = await signInAsCustomer(testApp, 'owner@example.test')
  const intruder = await signInAsCustomer(testApp, 'intruder@example.test')
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  const { conversationId } = await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: owner.cookies,
  })

  const getForeign = await testApp.app.inject({
    method: 'GET',
    url: `/messages/${conversationId}`,
    cookies: intruder.cookies,
  })
  assert.equal(getForeign.statusCode, 404)

  const postForeign = await testApp.app.inject({
    method: 'POST',
    url: `/messages/${conversationId}`,
    payload: { body: 'Trying to butt in' },
    cookies: intruder.cookies,
  })
  assert.equal(postForeign.statusCode, 404)

  const getBad = await testApp.app.inject({
    method: 'GET',
    url: '/messages/not-a-number',
    cookies: owner.cookies,
  })
  assert.equal(getBad.statusCode, 404)

  const postBad = await testApp.app.inject({
    method: 'POST',
    url: '/messages/not-a-number',
    payload: { body: 'x' },
    cookies: owner.cookies,
  })
  assert.equal(postBad.statusCode, 404)
})

test('posting a reply appends and redirects; an empty body re-renders with a field error and appends nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  const { conversationId } = await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: customer.cookies,
  })

  const emptyResponse = await testApp.app.inject({
    method: 'POST',
    url: `/messages/${conversationId}`,
    payload: { body: '   ' },
    cookies: customer.cookies,
  })
  assert.equal(emptyResponse.statusCode, 422)
  assert.match(emptyResponse.body, /data-field-error="body"[^>]*>Write a message before sending\./)

  const afterEmpty = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversationId)
    .execute()
  assert.equal(afterEmpty.length, 1)

  const replyResponse = await testApp.app.inject({
    method: 'POST',
    url: `/messages/${conversationId}`,
    payload: { body: 'Following up on this' },
    cookies: customer.cookies,
  })
  assert.equal(replyResponse.statusCode, 302)

  const afterReply = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversationId)
    .execute()
  assert.equal(afterReply.length, 2)
})

test('a bodiless question POST re-renders the listing with a field error, and leaves no conversation behind', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/art/harbour-at-dusk/questions',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="question"[^>]*>Write a message before sending\./)

  const conversations = await testApp.db.selectFrom('conversations').selectAll().execute()
  assert.equal(conversations.length, 0)
})

test('a blocked customer asking a listing question is refused with a field-less error, and leaves no conversation behind', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await signInAsCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await blockCustomer(testApp, { customerId: customer.id, adminId: admin.id })

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/art/harbour-at-dusk/questions',
    payload: { body: 'Is this framed?' },
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-form-error/)
  assert.match(response.body, /This account is blocked and cannot send messages\./)
  assert.match(response.body, /id="question"[^>]*>Is this framed\?/)

  const conversations = await testApp.db.selectFrom('conversations').selectAll().execute()
  assert.equal(conversations.length, 0)
})

test('an anonymous customer can ask a question on a listing and read the thread', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const { response, conversationId } = await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: customer.cookies,
  })
  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/messages/${conversationId}`)

  const thread = await testApp.app.inject({
    method: 'GET',
    url: `/messages/${conversationId}`,
    cookies: customer.cookies,
  })
  assert.equal(thread.statusCode, 200)
  assert.match(thread.body, /Is this framed\?/)
})

test('a customer with an active block is refused when posting a reply, and no message row is written', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await signInAsCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  const { conversationId } = await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: customer.cookies,
  })
  await blockCustomer(testApp, { customerId: customer.id, adminId: admin.id })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/messages/${conversationId}`,
    payload: { body: 'Trying to reply' },
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 422)
  // The reply form itself is gone once the customer is blocked; the page's
  // own status message is what tells them why, not a form-level error beside
  // a form that no longer renders.
  assert.match(response.body, /role="status"[^>]*>[\s\S]*This account is blocked and cannot send messages\./)

  const messages = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversationId)
    .execute()
  assert.equal(messages.length, 1)
})

test('/support opens the admin thread and reuses the same one on a second visit', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customer = await signInAsCustomer(testApp)

  const first = await testApp.app.inject({
    method: 'GET',
    url: '/support',
    cookies: customer.cookies,
  })
  assert.equal(first.statusCode, 302)
  assert.match(first.headers.location ?? '', /^\/messages\/cnv_[0-9A-HJKMNP-TV-Z]{26}$/)

  const second = await testApp.app.inject({
    method: 'GET',
    url: '/support',
    cookies: customer.cookies,
  })
  assert.equal(second.headers.location, first.headers.location)

  const conversations = await testApp.db
    .selectFrom('conversations')
    .selectAll()
    .where('kind', '=', 'admin_customer')
    .execute()
  assert.equal(conversations.length, 1)
  assert.equal(conversations[0]?.adminId, admin.id)
  assert.equal(conversations[0]?.customerId, customer.id)
})

test('/support with no admin seeded flashes an alert and redirects to the account page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/support',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/account')
})

test('posting a fulfillment message opens the thread, and 404s for another customer’s order and a fulfillment on a different order', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'owner@example.test')
  const stranger = await signInAsCustomer(testApp, 'stranger@example.test')
  const { order, fulfillment, seller } = await orderWithFulfillment(
    testApp,
    customer.id,
    'Harbour at dusk',
  )
  const other = await orderWithFulfillment(testApp, stranger.id, 'Kiln study')

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${fulfillment.id}/messages`,
    cookies: customer.cookies,
  })
  assert.equal(response.statusCode, 302)
  assert.match(response.headers.location ?? '', /^\/messages\/cnv_[0-9A-HJKMNP-TV-Z]{26}$/)

  const conversation = await testApp.db
    .selectFrom('conversations')
    .selectAll()
    .where('id', '=', conversationIdFrom(response.headers.location))
    .executeTakeFirstOrThrow()
  assert.equal(conversation.kind, 'fulfillment')
  assert.equal(conversation.sellerId, seller.id)
  assert.equal(conversation.customerId, customer.id)
  assert.equal(conversation.fulfillmentId, fulfillment.id)

  const foreignOrder = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${fulfillment.id}/messages`,
    cookies: stranger.cookies,
  })
  assert.equal(foreignOrder.statusCode, 404)

  const crossedFulfillment = await testApp.app.inject({
    method: 'POST',
    url: `/orders/${order.id}/fulfillments/${other.fulfillment.id}/messages`,
    cookies: customer.cookies,
  })
  assert.equal(crossedFulfillment.statusCode, 404)
})

test('a bodiless reply POST re-renders with a field error instead of failing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  const { conversationId } = await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: customer.cookies,
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/messages/${conversationId}`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="body"[^>]*>Write a message before sending\./)

  const messages = await testApp.db
    .selectFrom('messages')
    .selectAll()
    .where('conversationId', '=', conversationId)
    .execute()
  assert.equal(messages.length, 1)
})

test('the customer thread shows a published FAQ answer with a link to the listing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  const { conversationId } = await askAQuestion(testApp, {
    slug: 'harbour-at-dusk',
    body: 'Is this framed?',
    cookies: customer.cookies,
  })
  const answer = postedMessage(
    await postMessage(testApp, {
      conversationId,
      sender: { type: 'seller', id: seller.id },
      body: 'Yes, in oak.',
    }),
  )
  const listing = await testApp.db
    .selectFrom('listings')
    .selectAll()
    .where('slug', '=', 'harbour-at-dusk')
    .executeTakeFirstOrThrow()
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.', source_message_id: String(answer.id) },
  })
  const faq = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', listing.id)
    .executeTakeFirstOrThrow()

  const thread = await testApp.app.inject({
    method: 'GET',
    url: `/messages/${conversationId}`,
    cookies: customer.cookies,
  })

  assert.equal(thread.statusCode, 200)
  assert.match(thread.body, /Published to FAQ/)
  assert.match(thread.body, new RegExp(`/art/harbour-at-dusk#faq-${faq.id}`))
})
