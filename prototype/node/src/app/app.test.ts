import { test } from 'node:test'
import assert from 'node:assert/strict'
import { tmpdir } from 'node:os'
import path from 'node:path'
import type { LightMyRequestResponse } from 'fastify'
import type { AppConfig } from './config.ts'
import { flashMagicLinkDelivery } from './delivery/flash-magic-link-delivery.ts'
import { buildTestApp, TEST_CONFIG, type TestApp } from './test/build-test-app.ts'

/**
 * What `loadConfig` yields for a deployment behind TLS. The flash delivery is
 * still wired underneath so the assertions below prove the view and the cookie
 * writers hold on their own, not that the delivery happened to stay silent.
 */
const PRODUCTION_CONFIG: AppConfig = {
  ...TEST_CONFIG,
  environment: 'production',
  uploadsDir: path.join(tmpdir(), 'art-store-production-test-uploads-unused'),
  publicUrl: 'https://art-store.example.com',
  secureCookies: true,
  showsDebugMagicLinks: false,
}

function buildProductionApp(): Promise<TestApp> {
  return buildTestApp({ config: PRODUCTION_CONFIG, magicLinkDelivery: flashMagicLinkDelivery })
}

function named(
  response: LightMyRequestResponse,
  name: string,
): LightMyRequestResponse['cookies'][number] | undefined {
  return response.cookies.find((cookie) => cookie.name === name)
}

/** The cookie a sign-in request left for the page the browser lands on. */
function flashCookie(response: LightMyRequestResponse): string {
  return String(named(response, 'flash')?.value ?? '')
}

test('a production page prints no sign-in link even when one is in the flash', async (t) => {
  const { app, close } = await buildProductionApp()
  t.after(close)

  const requested = await app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com' },
  })
  const page = await app.inject({
    method: 'GET',
    url: '/login',
    cookies: { flash: flashCookie(requested) },
  })

  assert.equal(page.statusCode, 200)
  assert.doesNotMatch(page.body, /\/auth\/magic\//)
  assert.doesNotMatch(page.body, /Use this sign-in link/)
})

test('a development page prints the sign-in link the flash carries', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const requested = await app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com' },
  })
  const page = await app.inject({
    method: 'GET',
    url: '/login',
    cookies: { flash: flashCookie(requested) },
  })

  assert.match(page.body, /Use this sign-in link/)
  assert.match(page.body, /\/auth\/magic\//)
})

test('production sets Secure on the identity and flash cookies', async (t) => {
  const { app, close } = await buildProductionApp()
  t.after(close)

  // The storefront hands every visitor a customer cookie; the sign-in request
  // leaves a flash for the page it redirects to.
  const browsed = await app.inject({ method: 'GET', url: '/' })
  const requested = await app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com' },
  })

  assert.equal(named(browsed, 'customer_id')?.secure, true)
  assert.equal(named(requested, 'flash')?.secure, true)
})

test('development leaves Secure off, so the cookies survive plain http', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const browsed = await app.inject({ method: 'GET', url: '/' })

  assert.notEqual(named(browsed, 'customer_id')?.secure, true)
})

test('a magic link is built from the public url, not the Host header the request carried', async (t) => {
  const { app, close } = await buildProductionApp()
  t.after(close)

  const requested = await app.inject({
    method: 'POST',
    url: '/login',
    payload: { email: 'buyer@example.com' },
    headers: { host: 'attacker.example' },
  })

  const flash = app.unsignCookie(flashCookie(requested))
  const link: unknown = JSON.parse(flash.value ?? '{}')
  assert.ok(link !== null && typeof link === 'object' && 'debugMagicLink' in link)
  assert.match(String(link.debugMagicLink), /^https:\/\/art-store\.example\.com\/auth\/magic\//)
})
