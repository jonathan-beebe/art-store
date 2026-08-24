import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { LightMyRequestResponse } from 'fastify'
import type { ActorType } from '../../core/auth/actor-type.ts'
import { seedAdmins } from '../../db/seed-admins.ts'
import { outboxMagicLinkDelivery } from '../../delivery/outbox-magic-link-delivery.ts'
import { flashSchema, type Flash } from '../../plugins/flash.ts'
import {
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  takeDebugMagicLink,
  type SignedInActor,
  type TestApp,
} from '../../test/build-test-app.ts'
import { buildLoggedTestApp } from '../../test/log-lines.ts'

/** The full flash a response set, or `{}` for a response that flashed nothing. */
function flashOf({ app }: TestApp, response: LightMyRequestResponse): Flash {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')
  if (cookie === undefined) return {}

  const unsigned = app.unsignCookie(String(cookie.value))
  return flashSchema.parse(JSON.parse(unsigned.value ?? '{}'))
}

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

    assert.equal(response.statusCode, 422)
    assert.match(response.body, /data-field-error="email"[^>]*>Enter an email address to sign in\./)
    assert.match(response.body, /id="email"[^>]*value="not-an-address"/)
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

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="email"[^>]*>Enter an email address to sign in\./)
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

test('the admin sign-in answers byte-identically for an admitted and an unknown address', async (t) => {
  // Outbox delivery, not flash: the debug bar's own difference (it can only
  // print a link that exists) is a development-only surface this comparison
  // is not about — see the ticket's Working notes.
  const testApp = await buildTestApp({ magicLinkDelivery: outboxMagicLinkDelivery })
  await seedAdmins(testApp)
  t.after(testApp.close)

  const admitted = await testApp.app.inject({
    method: 'POST',
    url: '/admin/login',
    payload: { email: 'jonathan-beebe@outlook.com' },
  })
  const unknown = await testApp.app.inject({
    method: 'POST',
    url: '/admin/login',
    payload: { email: 'nobody-runs-this@example.com' },
  })

  assert.equal(admitted.statusCode, unknown.statusCode)
  assert.equal(admitted.headers.location, unknown.headers.location)
  assert.equal(admitted.body, unknown.body)

  const links = await testApp.db.selectFrom('magicLinks').selectAll().execute()
  assert.equal(links.length, 1)
  assert.equal(links[0]?.email, 'jonathan-beebe@outlook.com')
})

test('an unadmitted address is flashed the same notice an admitted one gets, never an alert', async (t) => {
  const testApp = await buildTestApp({ magicLinkDelivery: outboxMagicLinkDelivery })
  await seedAdmins(testApp)
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/login',
    payload: { email: 'nobody-runs-this@example.com' },
  })

  const flash = flashOf(testApp, response)
  assert.equal(flash.notice, 'Sign-in link sent to nobody-runs-this@example.com.')
  assert.equal(flash.alert, undefined)
  assert.equal(flash.debugMagicLink, undefined)
})

test('refusing an unadmitted address tells magic_link.request without the address', async (t) => {
  const testApp = await buildLoggedTestApp({ magicLinkDelivery: outboxMagicLinkDelivery })
  const log = testApp.logLines
  await seedAdmins(testApp)
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/admin/login',
    payload: { email: 'nobody-runs-this@example.com' },
  })

  const refused = log.data('magic_link.request', 'refused')
  assert.equal(refused.reason, 'not_admitted')
  assert.equal(refused.actor_type, 'admin')
  assert.equal(log.text().includes('nobody-runs-this@example.com'), false)
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

test('a seller-site sign-in drops a redirect_to onto an admin path', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: 'artist@example.com', redirect_to: '/admin/orders' },
  })

  const link = await testApp.db.selectFrom('magicLinks').selectAll().executeTakeFirstOrThrow()
  assert.equal(link.redirectTo, null)
})

test('a customer sign-in drops a redirect_to onto a seller or an admin path', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com', redirect_to: '/seller/listings' },
  })
  await testApp.app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer2@example.com', redirect_to: '/admin/orders' },
  })

  const links = await testApp.db.selectFrom('magicLinks').selectAll().execute()
  assert.equal(links.length, 2)
  assert.ok(links.every((link) => link.redirectTo === null))
})

test('an admin sign-in drops a redirect_to onto a seller path', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/admin/login',
    payload: { email: 'jonathan-beebe@outlook.com', redirect_to: '/seller/listings' },
  })

  const link = await testApp.db.selectFrom('magicLinks').selectAll().executeTakeFirstOrThrow()
  assert.equal(link.redirectTo, null)
})

test('a seller-site sign-in keeps a redirect_to onto a path no site owns', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: 'artist@example.com', redirect_to: '/orders/7' },
  })

  const link = await testApp.db.selectFrom('magicLinks').selectAll().executeTakeFirstOrThrow()
  assert.equal(link.redirectTo, '/orders/7')
})

test('an admin sign-in keeps a redirect_to onto its own admin path', async (t) => {
  const testApp = await buildSignedUpApp()
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/admin/login',
    payload: { email: 'jonathan-beebe@outlook.com', redirect_to: '/admin/orders' },
  })

  const link = await testApp.db.selectFrom('magicLinks').selectAll().executeTakeFirstOrThrow()
  assert.equal(link.redirectTo, '/admin/orders')
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

test('asking for a link tells magic_link.request without the address or the link', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)

  await testApp.app.inject({
    method: 'POST',
    url: '/seller/login',
    payload: { email: 'artist@example.com' },
  })

  const did = log.data('magic_link.request', 'did')
  assert.equal(did.actor_type, 'seller')
  assert.match(String(did.magic_link_id), /^mlk_/)
  assert.equal(log.data('magic_link.request', 'will').magic_link_id, did.magic_link_id)

  assert.equal(log.text().includes('artist@example.com'), false)
  assert.doesNotMatch(log.text(), /\/auth\/magic\//)
})
