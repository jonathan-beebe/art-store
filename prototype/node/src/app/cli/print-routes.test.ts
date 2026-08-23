import { test } from 'node:test'
import assert from 'node:assert/strict'
import { Writable } from 'node:stream'
import { main, routeReport } from './print-routes.ts'

/** The prefix each site is registered under, and a route only that site serves. */
const SITE_ROUTES = [
  ['auth', 'auth/magic/:token'],
  ['shop', 'checkout'],
  ['seller', 'seller'],
  ['admin', 'admin'],
] as const

test('the report names every site prefix and a route inside it', async () => {
  const report = await routeReport()

  for (const [site, route] of SITE_ROUTES) {
    assert.ok(report.includes(route), `${site} is missing ${route}`)
  }
})

test('the report names the four site plugins and the root plugins beside them', async () => {
  const report = await routeReport()

  for (const plugin of ['authSite', 'shopSite', 'sellerSite', 'adminSite']) {
    assert.ok(report.includes(plugin), `the plugin tree is missing ${plugin}`)
  }

  for (const plugin of ['errorPages', 'securityHeaders', 'flashCookie', 'identityCookies']) {
    assert.ok(report.includes(plugin), `the plugin tree is missing ${plugin}`)
  }
})

test('main writes the report to the stream it was given', async () => {
  const chunks: string[] = []
  const out = new Writable({
    write(chunk: Buffer, _encoding, done) {
      chunks.push(chunk.toString())
      done()
    },
  })

  await main(out)

  assert.match(chunks.join(''), /^Routes\n/)
  assert.ok(chunks.join('').includes('health'))
})
