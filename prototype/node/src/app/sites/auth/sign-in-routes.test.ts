import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { ActorType } from '../../core/auth/actor-type.ts'
import { seedAdmins } from '../../db/seed-admins.ts'
import { outboxMagicLinkDelivery } from '../../delivery/outbox-magic-link-delivery.ts'
import {
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  takeDebugMagicLink,
  TEST_CONFIG,
  type SignedInActor,
  type TestApp,
} from '../../test/build-test-app.ts'
import { captureLogLines } from '../../test/log-lines.ts'

type Site = {
  actorType: ActorType
  name: string
  loginPath: string
  logoutPath: string
  accountPath: string
  homePath: string
  signedOutPath: string
  signIn(testApp: TestApp): Promise<SignedInActor>
}

const SITES: readonly Site[] = [
  {
    actorType: 'seller',
    name: 'the seller portal',
    loginPath: '/seller/login',
    logoutPath: '/seller/logout',
    accountPath: '/seller/account',
    homePath: '/seller',
    signedOutPath: '/seller/login',
    signIn: (testApp) => signInAsSeller(testApp),
  },
  {
    actorType: 'customer',
    name: 'the storefront',
    loginPath: '/login',
    logoutPath: '/logout',
    accountPath: '/account',
    homePath: '/account',
    signedOutPath: '/',
    signIn: (testApp) => signInAsCustomer(testApp),
  },
  {
    actorType: 'admin',
    name: 'the admin site',
    loginPath: '/admin/login',
    logoutPath: '/admin/logout',
    accountPath: '/admin/account',
    homePath: '/admin',
    signedOutPath: '/admin/login',
    signIn: (testApp) => signInAsAdmin(testApp),
  },
]

async function buildSignedUpApp(): Promise<TestApp> {
  const testApp = await buildTestApp()
  await seedAdmins(testApp)

  return testApp
}

/** The address each site will issue a link for, given who is allowed to ask. */
function addressFor(actorType: ActorType): string {
  return actorType === 'admin' ? 'jonathan-beebe@outlook.com' : 'newcomer@example.com'
}

for (const site of SITES) {
  test(`${site.name} asks for an email address and nothing else`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)

    const response = await testApp.app.inject({ method: 'GET', url: site.loginPath })

    assert.equal(response.statusCode, 200)
    assert.match(response.body, new RegExp(`action="${site.loginPath}"`))
    assert.match(response.body, /method="post"/)
    assert.match(response.body, /type="email"[^>]*name="email"|name="email"[^>]*type="email"/)
    assert.doesNotMatch(response.body, /type="password"/)
  })

  test(`${site.name} issues a link for its own side of the marketplace`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)

    const response = await testApp.app.inject({
      method: 'POST',
      url: site.loginPath,
      payload: { email: addressFor(site.actorType) },
    })

    assert.equal(response.statusCode, 302)
    assert.equal(response.headers.location, site.loginPath)

    const links = await testApp.db.selectFrom('magicLinks').selectAll().execute()

    assert.equal(links.length, 1)
    assert.equal(links[0]?.actorType, site.actorType)
    assert.equal(links[0]?.email, addressFor(site.actorType))
  })

  test(`${site.name} prints the link in the debug alert on the page it lands on`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)

    const asked = await testApp.app.inject({
      method: 'POST',
      url: site.loginPath,
      payload: { email: addressFor(site.actorType) },
    })
    const link = takeDebugMagicLink(testApp, asked)

    const landed = await testApp.app.inject({
      method: 'GET',
      url: site.loginPath,
      cookies: cookiesOf(asked),
    })

    assert.match(link, /^http:\/\/[^/]+\/auth\/magic\/[0-9a-f]{64}$/)
    assert.match(landed.body, new RegExp(`role="alert"`))
    assert.ok(landed.body.includes(link))
  })

  test(`${site.name} issues no link for something that is not an address`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)

    const response = await testApp.app.inject({
      method: 'POST',
      url: site.loginPath,
      payload: { email: 'not-an-address' },
    })

    assert.equal(response.statusCode, 302)
    assert.equal((await testApp.db.selectFrom('magicLinks').selectAll().execute()).length, 0)
  })

  test(`${site.name} sends someone already signed in to their home page`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)
    const actor = await site.signIn(testApp)

    const response = await testApp.app.inject({
      method: 'GET',
      url: site.loginPath,
      cookies: actor.cookies,
    })

    assert.equal(response.statusCode, 302)
    assert.equal(response.headers.location, site.homePath)
  })

  test(`${site.name} keeps its account page behind a sign-in`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)

    const response = await testApp.app.inject({ method: 'GET', url: site.accountPath })

    assert.equal(response.statusCode, 302)
    assert.equal(
      response.headers.location,
      `${site.loginPath}?redirect_to=${encodeURIComponent(site.accountPath)}`,
    )
  })

  test(`${site.name} shows the signed-in identity on its account page`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)
    const actor = await site.signIn(testApp)

    const response = await testApp.app.inject({
      method: 'GET',
      url: site.accountPath,
      cookies: actor.cookies,
    })

    assert.equal(response.statusCode, 200)
    assert.match(response.body, new RegExp(`action="${site.logoutPath}"`))
  })

  test(`${site.name} signs out to its own landing page`, async (t) => {
    const testApp = await buildSignedUpApp()
    t.after(testApp.close)
    const actor = await site.signIn(testApp)

    const response = await testApp.app.inject({
      method: 'POST',
      url: site.logoutPath,
      cookies: actor.cookies,
    })

    assert.equal(response.statusCode, 302)
    assert.equal(response.headers.location, site.signedOutPath)

    const stillSignedIn = await testApp.app.inject({
      method: 'GET',
      url: site.accountPath,
      cookies: cookiesOf(response),
    })

    assert.equal(stillSignedIn.statusCode, 302)
  })
}

test('a bodiless POST to /login is refused instead of failing', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'POST', url: '/login' })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/login')
  assert.equal((await testApp.db.selectFrom('magicLinks').selectAll().execute()).length, 0)
})

test('an address with no admin row cannot obtain an admin link', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/login',
    payload: { email: 'stranger@example.com' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal((await testApp.db.selectFrom('magicLinks').selectAll().execute()).length, 0)
  assert.equal((await testApp.db.selectFrom('admins').selectAll().execute()).length, 2)
})

test('asking the storefront for a link leaves no customer row behind', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  await testApp.app.inject({ method: 'GET', url: '/login' })
  await testApp.app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com' },
  })

  assert.equal((await testApp.db.selectFrom('customers').selectAll().execute()).length, 0)
})

test('the storefront carries a destination through the sign-in form onto the link', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  const form = await testApp.app.inject({
    method: 'GET',
    url: '/login?redirect_to=%2Forders%2F7%2Fpay',
  })

  assert.match(form.body, /name="redirect_to"[^>]*value="\/orders\/7\/pay"/)

  await testApp.app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com', redirect_to: '/orders/7/pay' },
  })

  const link = await testApp.db.selectFrom('magicLinks').selectAll().executeTakeFirstOrThrow()

  assert.equal(link.redirectTo, '/orders/7/pay')
})

test('a destination on another host never reaches the link', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com', redirect_to: 'http://evil.example/steal' },
  })

  const link = await testApp.db.selectFrom('magicLinks').selectAll().executeTakeFirstOrThrow()

  assert.equal(link.redirectTo, null)
})

function cookiesOf(response: { cookies: readonly { name: string; value: string }[] }): Record<
  string,
  string
> {
  return Object.fromEntries(response.cookies.map((cookie) => [cookie.name, cookie.value]))
}

test('with the outbox delivery the link is queued and no page prints it', async (t) => {
  const testApp = await buildTestApp({ magicLinkDelivery: outboxMagicLinkDelivery })
  t.after(testApp.close)

  const asked = await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: 'artist@example.com' },
  })

  assert.equal(asked.statusCode, 302)
  assert.throws(() => takeDebugMagicLink(testApp, asked), /carries no magic link/)

  const queued = await testApp.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  assert.equal(queued.recipient, 'artist@example.com')
  assert.equal(queued.subject, 'Your Art Store sign-in link')
  assert.match(queued.url ?? '', /\/auth\/magic\/[0-9a-f]{64}$/)
})

test('the queued link signs the seller in when it is followed', async (t) => {
  const testApp = await buildTestApp({ magicLinkDelivery: outboxMagicLinkDelivery })
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: 'artist@example.com' },
  })

  const queued = await testApp.db.selectFrom('outboxMessages').selectAll().executeTakeFirstOrThrow()
  const followed = await testApp.app.inject({ method: 'GET', url: queued.url ?? '' })

  assert.equal(followed.statusCode, 302)
  assert.equal(followed.headers.location, '/seller')
  assert.equal(cookiesOf(followed).seller_id === undefined, false)
})

test('asking for a link logs a magic_link.requested event that never carries the link itself', async (t) => {
  const stream = captureLogLines()
  const testApp = await buildTestApp({
    config: { ...TEST_CONFIG, logLevel: 'info' },
    loggerStream: stream,
  })
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: 'artist@example.com' },
  })

  const line = stream.lines().find((entry) => entry.event === 'magic_link.requested')
  assert.equal(line?.actorType, 'seller')
  assert.equal(line?.email, 'artist@example.com')

  const rendered = JSON.stringify(stream.lines())
  assert.doesNotMatch(rendered, /\/auth\/magic\//)
})
