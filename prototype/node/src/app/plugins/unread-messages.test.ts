import { test } from 'node:test'
import assert from 'node:assert/strict'
import { CamelCasePlugin, Kysely } from 'kysely'
import { openConversation } from '../actions/messaging/open-conversation.ts'
import { postMessage } from '../actions/messaging/post-message.ts'
import type { AdminId, CustomerId, SellerId } from '../core/ids/entity-ids.ts'
import { IN_MEMORY_DATABASE } from '../db/database.ts'
import { NodeSqliteDialect } from '../db/node-sqlite-dialect.ts'
import type { Database } from '../db/schema.ts'
import {
  buildTestApp,
  browseAsAnonymousCustomer,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  type TestApp,
} from '../test/build-test-app.ts'

type QueryLoggingTestApp = TestApp & { statements: string[] }

/** A test app whose every SQL statement lands in `statements`, for a test that
 * counts how many times a route touches a given table. */
async function buildQueryLoggingTestApp(): Promise<QueryLoggingTestApp> {
  const statements: string[] = []
  const db = new Kysely<Database>({
    dialect: new NodeSqliteDialect(IN_MEMORY_DATABASE),
    plugins: [new CamelCasePlugin()],
    log(event) {
      if (event.level === 'query') statements.push(event.query.sql)
    },
  })

  const testApp = await buildTestApp({ db })

  return { ...testApp, statements }
}

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

test('a storefront page resolves the customer cookie once', async (t) => {
  const testApp = await buildQueryLoggingTestApp()
  t.after(testApp.close)
  const visitor = await browseAsAnonymousCustomer(testApp)
  testApp.statements.length = 0

  const response = await testApp.app.inject({ url: '/', cookies: visitor.cookies })

  assert.equal(response.statusCode, 200)
  const mergeLookups = testApp.statements.filter((sql) => sql.includes('customer_merges'))
  assert.equal(mergeLookups.length, 1)
})

test('the sign-in page resolves the customer cookie once', async (t) => {
  const testApp = await buildQueryLoggingTestApp()
  t.after(testApp.close)
  const visitor = await browseAsAnonymousCustomer(testApp)
  testApp.statements.length = 0

  const response = await testApp.app.inject({ url: '/login', cookies: visitor.cookies })

  assert.equal(response.statusCode, 200)
  const mergeLookups = testApp.statements.filter((sql) => sql.includes('customer_merges'))
  assert.equal(mergeLookups.length, 1)
})
