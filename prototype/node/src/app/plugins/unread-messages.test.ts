import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openConversation } from '../actions/messaging/open-conversation.ts'
import { postMessage } from '../actions/messaging/post-message.ts'
import type { AdminId, CustomerId, SellerId } from '../core/ids/entity-ids.ts'
import {
  buildTestApp,
  browseAsAnonymousCustomer,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  type TestApp,
} from '../test/build-test-app.ts'

/** The operator writing to one side of the marketplace, so that side has something waiting. */
async function messageFromAdmin(
  testApp: TestApp,
  adminId: AdminId,
  opening: { kind: 'admin_seller'; sellerId: SellerId } | { kind: 'admin_customer'; customerId: CustomerId },
): Promise<void> {
  const context = { db: testApp.db, clock: testApp.clock }
  const conversation = await openConversation(context, { ...opening, adminId })

  await postMessage(context, {
    conversationId: conversation.id,
    sender: { type: 'admin', id: adminId },
    body: 'A quick question about your account.',
  })
}

test('the seller portal nav carries what the seller has waiting', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)

  await messageFromAdmin(testApp, operator.id, { kind: 'admin_seller', sellerId: seller.id })
  const response = await testApp.app.inject({ url: '/seller', cookies: seller.cookies })

  assert.match(response.body, /data-unread-messages="1"/)
})

test('the storefront nav carries what the customer has waiting', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const operator = await signInAsAdmin(testApp)

  await messageFromAdmin(testApp, operator.id, { kind: 'admin_customer', customerId: customer.id })
  const response = await testApp.app.inject({ url: '/', cookies: customer.cookies })

  assert.match(response.body, /data-unread-messages="1"/)
})

test('the admin nav carries what the operator has waiting', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const conversation = await openConversation(context, {
    kind: 'admin_seller',
    adminId: operator.id,
    sellerId: seller.id,
  })
  await postMessage(context, {
    conversationId: conversation.id,
    sender: { type: 'seller', id: seller.id },
    body: 'Thanks, that answers it.',
  })

  const response = await testApp.app.inject({ url: '/admin', cookies: operator.cookies })

  assert.match(response.body, /data-unread-messages="1"/)
})

test('a nav with nothing waiting carries no badge', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({ url: '/seller', cookies: seller.cookies })

  assert.doesNotMatch(response.body, /data-unread-messages/)
})

// The storefront's sign-in pages sit outside the hook that resolves a customer,
// so the count is read from the cookie rather than going missing there.
test('the account page counts an anonymous visitor the same as any other page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const visitor = await browseAsAnonymousCustomer(testApp)
  const operator = await signInAsAdmin(testApp)

  await messageFromAdmin(testApp, operator.id, { kind: 'admin_customer', customerId: visitor.id })
  const response = await testApp.app.inject({ url: '/login', cookies: visitor.cookies })

  assert.match(response.body, /data-unread-messages="1"/)
})
