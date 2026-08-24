import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { LightMyRequestResponse } from 'fastify'
import { digestMagicLinkToken } from '../../core/auth/magic-link-token.ts'
import { seedAdmins } from '../../db/seed-admins.ts'
import { newId } from '../../ids.ts'
import {
  buildTestApp,
  TEST_INSTANT,
  takeDebugMagicLink,
  type TestApp,
} from '../../test/build-test-app.ts'
import { buildLoggedTestApp } from '../../test/log-lines.ts'

type Cookies = Record<string, string>

function cookiesOf(response: LightMyRequestResponse): Cookies {
  return Object.fromEntries(response.cookies.map((cookie) => [cookie.name, cookie.value]))
}

function pathOf(url: string): string {
  return new URL(url).pathname
}

/** Reads the id a signed identity cookie carries, the way the app does. */
function identityId({ app }: TestApp, cookies: Cookies, name: string): string | null {
  const cookie = cookies[name]
  if (cookie === undefined) return null

  const unsigned = app.unsignCookie(cookie)

  return unsigned.valid ? unsigned.value : null
}

async function askForLink(
  testApp: TestApp,
  loginPath: string,
  email: string,
  cookies: Cookies = {},
): Promise<{ link: string; cookies: Cookies }> {
  const response = await testApp.app.inject({
    method: 'POST',
    url: loginPath,
    payload: { email },
    cookies,
  })

  return { link: takeDebugMagicLink(testApp, response), cookies: { ...cookies, ...cookiesOf(response) } }
}

async function follow(
  testApp: TestApp,
  link: string,
  cookies: Cookies = {},
): Promise<LightMyRequestResponse> {
  return await testApp.app.inject({ method: 'GET', url: pathOf(link), cookies })
}

test('a seller signs in through the link and lands on the portal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const asked = await askForLink(testApp, '/seller/login', 'newcomer@example.com')
  const followed = await follow(testApp, asked.link, asked.cookies)

  assert.equal(followed.statusCode, 302)
  assert.equal(followed.headers.location, '/seller')

  const seller = await testApp.db.selectFrom('sellers').selectAll().executeTakeFirstOrThrow()

  assert.equal(seller.email, 'newcomer@example.com')
  assert.equal(identityId(testApp, cookiesOf(followed), 'seller_id'), seller.id)
})

test('following a seller link creates no customer row', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const asked = await askForLink(testApp, '/seller/login', 'newcomer@example.com')
  await follow(testApp, asked.link, asked.cookies)

  assert.equal((await testApp.db.selectFrom('customers').selectAll().execute()).length, 0)
})

test('a first storefront request hands the visitor an anonymous customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/' })

  const customer = await testApp.db.selectFrom('customers').selectAll().executeTakeFirstOrThrow()

  assert.equal(customer.email, null)
  assert.equal(identityId(testApp, cookiesOf(response), 'customer_id'), customer.id)
})

test('verifying from an anonymous session claims that row in place', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const browsing = await testApp.app.inject({ method: 'GET', url: '/' })
  const anonymousId = identityId(testApp, cookiesOf(browsing), 'customer_id')

  const asked = await askForLink(testApp, '/login', 'buyer@example.com', cookiesOf(browsing))
  const followed = await follow(testApp, asked.link, asked.cookies)

  assert.equal(followed.headers.location, '/account')
  assert.equal((await testApp.db.selectFrom('customers').selectAll().execute()).length, 1)

  const customer = await testApp.db.selectFrom('customers').selectAll().executeTakeFirstOrThrow()

  assert.equal(customer.id, anonymousId)
  assert.equal(customer.email, 'buyer@example.com')
})

test('verifying an address that already has an account folds the anonymous one in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const existing = await testApp.db
    .insertInto('customers')
    .values({
      id: newId('cus', new Date()),
      email: 'buyer@example.com',
      name: null,
      emailVerifiedAt: TEST_INSTANT.toISOString(),
      createdAt: TEST_INSTANT.toISOString(),
    })
    .returning('id')
    .executeTakeFirstOrThrow()

  const browsing = await testApp.app.inject({ method: 'GET', url: '/' })
  const anonymousId = identityId(testApp, cookiesOf(browsing), 'customer_id')

  const asked = await askForLink(testApp, '/login', 'buyer@example.com', cookiesOf(browsing))
  const followed = await follow(testApp, asked.link, asked.cookies)

  assert.equal(followed.headers.location, '/account')
  assert.equal(identityId(testApp, cookiesOf(followed), 'customer_id'), existing.id)

  const merges = await testApp.db.selectFrom('customerMerges').selectAll().execute()

  assert.equal(merges.length, 1)
  assert.equal(merges[0]?.anonymousCustomerId, anonymousId)
  assert.equal(merges[0]?.customerId, existing.id)
})

test('a cookie left holding a merged id resolves forward on the next visit', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  await testApp.db
    .insertInto('customers')
    .values({
      id: newId('cus', new Date()),
      email: 'buyer@example.com',
      name: null,
      emailVerifiedAt: TEST_INSTANT.toISOString(),
      createdAt: TEST_INSTANT.toISOString(),
    })
    .execute()

  const browsing = await testApp.app.inject({ method: 'GET', url: '/' })
  const staleCookies = cookiesOf(browsing)

  const asked = await askForLink(testApp, '/login', 'buyer@example.com', staleCookies)
  await follow(testApp, asked.link, asked.cookies)
  const customersBefore = (await testApp.db.selectFrom('customers').selectAll().execute()).length

  const returning = await testApp.app.inject({ method: 'GET', url: '/', cookies: staleCookies })

  const verified = await testApp.db
    .selectFrom('customers')
    .selectAll()
    .where('email', '=', 'buyer@example.com')
    .executeTakeFirstOrThrow()

  assert.equal(identityId(testApp, cookiesOf(returning), 'customer_id'), verified.id)
  assert.equal((await testApp.db.selectFrom('customers').selectAll().execute()).length, customersBefore)
})

test('a seeded admin signs in through the link and lands on the admin site', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  await seedAdmins(testApp)

  const asked = await askForLink(testApp, '/admin/login', 'annaschmunk@pm.me')
  const followed = await follow(testApp, asked.link, asked.cookies)

  assert.equal(followed.headers.location, '/admin')

  const admin = await testApp.db
    .selectFrom('admins')
    .selectAll()
    .where('email', '=', 'annaschmunk@pm.me')
    .executeTakeFirstOrThrow()

  assert.equal(identityId(testApp, cookiesOf(followed), 'admin_id'), admin.id)
})

test('a link works once and says so the second time', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const asked = await askForLink(testApp, '/seller/login', 'artist@example.com')
  await follow(testApp, asked.link, asked.cookies)
  const again = await follow(testApp, asked.link)

  assert.equal(again.headers.location, '/seller/login')

  const refusal = await testApp.app.inject({
    method: 'GET',
    url: '/seller/login',
    cookies: cookiesOf(again),
  })

  assert.match(refusal.body, /That sign-in link has already been used\./)
})

test('a link past its expiry signs nobody in and says so', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = '1'.repeat(64)
  const expired = new Date(TEST_INSTANT.getTime() - 60 * 1000).toISOString()

  await testApp.db
    .insertInto('magicLinks')
    .values({
      id: newId('mlk', new Date()),
      tokenDigest: digestMagicLinkToken(token),
      email: 'artist@example.com',
      actorType: 'seller',
      redirectTo: null,
      expiresAt: expired,
      consumedAt: null,
      createdAt: expired,
    })
    .execute()

  const followed = await testApp.app.inject({ method: 'GET', url: `/auth/magic/${token}` })

  assert.equal(followed.headers.location, '/seller/login')
  assert.equal((await testApp.db.selectFrom('sellers').selectAll().execute()).length, 0)

  const refusal = await testApp.app.inject({
    method: 'GET',
    url: '/seller/login',
    cookies: cookiesOf(followed),
  })

  assert.match(refusal.body, /That sign-in link has expired\./)
})

test('a token no link was issued for signs nobody in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const followed = await testApp.app.inject({ method: 'GET', url: `/auth/magic/${'0'.repeat(64)}` })

  assert.equal(followed.headers.location, '/login')

  const refusal = await testApp.app.inject({
    method: 'GET',
    url: '/login',
    cookies: cookiesOf(followed),
  })

  assert.match(refusal.body, /That sign-in link is not valid\./)
})

test('an admin link outlives its admin row and still signs nobody in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  await seedAdmins(testApp)

  const asked = await askForLink(testApp, '/admin/login', 'annaschmunk@pm.me')
  await testApp.db.deleteFrom('admins').where('email', '=', 'annaschmunk@pm.me').execute()

  const followed = await follow(testApp, asked.link, asked.cookies)

  assert.equal(followed.headers.location, '/admin/login')
  assert.equal(identityId(testApp, cookiesOf(followed), 'admin_id'), null)
})

test('verification carries the visitor on to the destination the link holds', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const asked = await testApp.app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com', redirect_to: '/orders/7/pay' },
  })
  const followed = await follow(testApp, takeDebugMagicLink(testApp, asked), cookiesOf(asked))

  assert.equal(followed.headers.location, '/orders/7/pay')
})

test('a link carrying a redirect_to outside its own site falls back on consumption', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = '2'.repeat(64)

  // Crafted directly rather than through `POST /seller/login`, which already
  // refuses this at the door — this is the defence for a row that reaches
  // `magic_links` some other way, not a reachable path through the form.
  await testApp.db
    .insertInto('magicLinks')
    .values({
      id: newId('mlk', new Date()),
      tokenDigest: digestMagicLinkToken(token),
      email: 'artist@example.com',
      actorType: 'seller',
      redirectTo: '/admin/orders',
      expiresAt: new Date(TEST_INSTANT.getTime() + 60_000).toISOString(),
      consumedAt: null,
      createdAt: TEST_INSTANT.toISOString(),
    })
    .execute()

  const followed = await testApp.app.inject({ method: 'GET', url: `/auth/magic/${token}` })

  assert.equal(followed.headers.location, '/seller')
})

test('consuming a link closes magic_link.consume with who signed in', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)

  const asked = await askForLink(testApp, '/seller/login', 'newcomer@example.com')
  await follow(testApp, asked.link, asked.cookies)

  const seller = await testApp.db.selectFrom('sellers').selectAll().executeTakeFirstOrThrow()
  const did = log.data('magic_link.consume', 'did')
  assert.equal(did.actor_type, 'seller')
  assert.equal(did.actor_id, seller.id)
  assert.equal(log.line('magic_link.consume', 'did').txn_id, log.line('magic_link.consume', 'will').txn_id)
})

test('the sign-in flow puts neither the address nor the token in the log', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)

  const asked = await askForLink(testApp, '/seller/login', 'newcomer@example.com')
  await follow(testApp, asked.link, asked.cookies)

  const token = asked.link.slice(asked.link.lastIndexOf('/') + 1)
  assert.equal(log.text().includes('newcomer@example.com'), false)
  assert.equal(log.text().includes(token), false)
  assert.equal(log.data('http.request', 'will').path, '/seller/login')
})

test('a link past its expiry refuses magic_link.consume naming why', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)
  const token = '1'.repeat(64)
  const expired = new Date(TEST_INSTANT.getTime() - 60 * 1000).toISOString()

  await testApp.db
    .insertInto('magicLinks')
    .values({
      id: newId('mlk', new Date()),
      tokenDigest: digestMagicLinkToken(token),
      email: 'artist@example.com',
      actorType: 'seller',
      redirectTo: null,
      expiresAt: expired,
      consumedAt: null,
      createdAt: expired,
    })
    .execute()

  await testApp.app.inject({ method: 'GET', url: `/auth/magic/${token}` })

  const refused = log.line('magic_link.consume', 'refused')
  assert.equal(refused.level, 'info')
  assert.deepEqual(refused.data, { reason: 'expired', actor_type: 'seller' })
})

test('a token no link was issued for refuses with no actor, and the url shows no token', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)
  const token = '0'.repeat(64)

  await testApp.app.inject({ method: 'GET', url: `/auth/magic/${token}` })

  assert.deepEqual(log.data('magic_link.consume', 'refused'), { reason: 'unknown_token' })
  assert.equal(log.data('http.request', 'will').path, '/auth/magic/:token')
  assert.equal(log.text().includes(token), false)
})
