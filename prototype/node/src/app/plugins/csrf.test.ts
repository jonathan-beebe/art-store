import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { InjectOptions } from 'fastify'
import { buildApp } from '../app.ts'
import { fixedClock } from '../clock.ts'
import { csrfToken } from '../core/security/csrf-token.ts'
import { IN_MEMORY_DATABASE, openDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'
import { flashMagicLinkDelivery } from '../delivery/flash-magic-link-delivery.ts'
import { buildTestApp, TEST_CONFIG, TEST_INSTANT } from '../test/build-test-app.ts'
import { isCsrfExempt } from './csrf.ts'

const STATE_CHANGING_METHODS: ReadonlySet<string> = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

/** The methods `registeredRoutes` can report, narrowed from Fastify's own
 * `string` so a probe request can be built without an `as` on its options. */
const KNOWN_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as const
type KnownMethod = (typeof KNOWN_METHODS)[number]

function isKnownMethod(value: string): value is KnownMethod {
  return (KNOWN_METHODS as readonly string[]).includes(value)
}

type Site = { name: string; logoutPath: string }

const SITES: readonly Site[] = [
  { name: 'the seller portal', logoutPath: '/seller/logout' },
  { name: 'the storefront', logoutPath: '/logout' },
  { name: 'the admin site', logoutPath: '/admin/logout' },
]

for (const site of SITES) {
  test(`${site.name} answers 403 with its own page when a POST carries no token`, async (t) => {
    const testApp = await buildTestApp()
    t.after(testApp.close)

    const response = await testApp.rawInject({ method: 'POST', url: site.logoutPath })

    assert.equal(response.statusCode, 403)
    assert.match(response.body, /That request could not be verified/)
  })

  test(`${site.name} answers 403 when a POST carries a token derived from another session`, async (t) => {
    const testApp = await buildTestApp()
    t.after(testApp.close)
    const foreign = csrfToken('ses_foreign00000000000000000', TEST_CONFIG.cookieSecret)

    const response = await testApp.rawInject({
      method: 'POST',
      url: site.logoutPath,
      cookies: { sid: 'ses_real000000000000000000000' },
      payload: { _csrf_token: foreign },
    })

    assert.equal(response.statusCode, 403)
    assert.match(response.body, /That request could not be verified/)
  })

  test(`${site.name} answers 403 when a POST carries a token that is simply wrong`, async (t) => {
    const testApp = await buildTestApp()
    t.after(testApp.close)

    const response = await testApp.rawInject({
      method: 'POST',
      url: site.logoutPath,
      cookies: { sid: 'ses_real000000000000000000000' },
      payload: { _csrf_token: 'not-a-real-token' },
    })

    assert.equal(response.statusCode, 403)
  })

  test(`${site.name} accepts a POST carrying the token derived from its own sid`, async (t) => {
    const testApp = await buildTestApp()
    t.after(testApp.close)

    // The wrapped `app.inject` mints a `sid` and attaches the token it
    // derives, the way a browser that loaded the page carries both.
    const response = await testApp.app.inject({ method: 'POST', url: site.logoutPath })

    assert.equal(response.statusCode, 302)
  })
}

test('a GET carries no CSRF check at all, token or not', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.rawInject({ method: 'GET', url: '/seller/login' })

  assert.equal(response.statusCode, 200)
})

/** Every route Fastify actually registers, method by method — read from the
 * framework's own `onRoute` hook rather than hand-kept, the same spirit as
 * `customer-owned-tables-manifest.test.ts` reading the schema itself. */
async function registeredRoutes(): Promise<readonly { method: string; url: string }[]> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const app = buildApp({
    db,
    clock: fixedClock(TEST_INSTANT),
    config: TEST_CONFIG,
    magicLinkDelivery: flashMagicLinkDelivery,
  })

  const routes: { method: string; url: string }[] = []
  app.addHook('onRoute', (routeOptions) => {
    const methods = Array.isArray(routeOptions.method) ? routeOptions.method : [routeOptions.method]
    for (const method of methods) routes.push({ method, url: routeOptions.url })
  })

  try {
    await app.ready()
    return routes
  } finally {
    await app.close()
    await db.destroy()
  }
}

/** A concrete, syntactically valid path for a route pattern — `:id`-style
 * segments and a trailing `*` both accept anything, so any placeholder
 * reaches the same route Fastify would have matched for a real one. Params
 * are read only after this guard runs, so what the placeholder actually says
 * plays no part in what the guard decides. */
function probePathFor(pattern: string): string {
  return pattern.replace(/\*$/, 'csrf-probe').replace(/:[^/]+/g, 'x')
}

test('every state-changing route the app registers is either covered by the CSRF guard or named, with why, in its allowlist', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const routes = (await registeredRoutes()).filter((route) => STATE_CHANGING_METHODS.has(route.method))
  assert.ok(routes.length > 10, 'expected to find the app’s state-changing routes')

  for (const route of routes) {
    if (!isKnownMethod(route.method)) throw new Error(`unexpected HTTP method ${route.method}`)

    const probe: InjectOptions = { method: route.method, url: probePathFor(route.url) }
    const response = await testApp.rawInject(probe)

    if (isCsrfExempt(route.method, route.url)) {
      assert.notEqual(
        response.statusCode,
        403,
        `${route.method} ${route.url} is on the CSRF allowlist but a tokenless request still got 403`,
      )
    } else {
      assert.equal(
        response.statusCode,
        403,
        `${route.method} ${route.url} is not on the CSRF allowlist and must answer 403 with no token`,
      )
    }
  }
})

test('isCsrfExempt refuses a route the allowlist never named', () => {
  assert.equal(isCsrfExempt('POST', '/does-not-exist'), false)
})
