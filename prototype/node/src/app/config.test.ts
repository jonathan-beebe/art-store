import { test } from 'node:test'
import assert from 'node:assert/strict'
import path from 'node:path'
import { loadConfig } from './config.ts'

// Mirrors the PUBLIC_ROOT convention config.ts and app.ts both resolve from
// their own directory, which is this file's directory too.
const DEFAULT_UPLOADS_DIR = path.join(import.meta.dirname, '..', 'public', 'uploads')

/** The least a production boot has to say for itself to be allowed to start. */
const PRODUCTION_ENVIRONMENT = {
  NODE_ENV: 'production',
  COOKIE_SECRET: 'a-secret-long-enough-to-sign',
  MAGIC_LINK_DELIVERY: 'outbox',
}

test('an empty environment yields the development defaults', () => {
  const config = loadConfig({})

  assert.deepEqual(config, {
    environment: 'development',
    host: '0.0.0.0',
    port: 4000,
    databaseFile: 'storage/development.sqlite3',
    cookieSecret: 'art-store-prototype-cookie-secret',
    logLevel: 'info',
    magicLinkDelivery: 'flash',
    uploadsDir: DEFAULT_UPLOADS_DIR,
    outboxDir: 'storage/outbox',
    staleOrderHours: 24,
    publicUrl: null,
    trustProxy: false,
    secureCookies: false,
    showsDebugMagicLinks: true,
  })
})

test('the environment overrides every default', () => {
  const config = loadConfig({
    NODE_ENV: 'test',
    HOST: '127.0.0.1',
    PORT: '4100',
    DATABASE_FILE: 'storage/test.sqlite3',
    COOKIE_SECRET: 'a-secret-long-enough-to-sign',
    LOG_LEVEL: 'silent',
    MAGIC_LINK_DELIVERY: 'outbox',
    UPLOADS_DIR: '/var/data/uploads',
    OUTBOX_DIR: '/var/data/outbox',
    PUBLIC_URL: 'https://art-store.example.com',
    TRUST_PROXY: 'true',
  })

  assert.equal(config.environment, 'test')
  assert.equal(config.host, '127.0.0.1')
  assert.equal(config.port, 4100)
  assert.equal(config.databaseFile, 'storage/test.sqlite3')
  assert.equal(config.cookieSecret, 'a-secret-long-enough-to-sign')
  assert.equal(config.logLevel, 'silent')
  assert.equal(config.magicLinkDelivery, 'outbox')
  assert.equal(config.uploadsDir, '/var/data/uploads')
  assert.equal(config.outboxDir, '/var/data/outbox')
  assert.equal(config.publicUrl, 'https://art-store.example.com')
  assert.equal(config.trustProxy, true)
})

test('unrelated environment variables are ignored', () => {
  const config = loadConfig({ PATH: '/usr/bin', UNRELATED: 'noise' })

  assert.equal(config.port, 4000)
})

test('a port that is not a positive integer is refused', () => {
  assert.throws(() => loadConfig({ PORT: 'http' }))
  assert.throws(() => loadConfig({ PORT: '0' }))
})

test('a cookie secret too short to sign is refused', () => {
  assert.throws(() => loadConfig({ COOKIE_SECRET: 'short' }))
})

test('an unknown log level is refused', () => {
  assert.throws(() => loadConfig({ LOG_LEVEL: 'chatty' }))
})

test('an unknown magic link delivery is refused rather than silently dropping links', () => {
  assert.throws(() => loadConfig({ MAGIC_LINK_DELIVERY: 'carrier-pigeon' }))
})

test('an environment name outside the three the app knows is refused', () => {
  assert.throws(() => loadConfig({ NODE_ENV: 'staging' }))
})

test('production refuses to boot without a cookie secret', () => {
  assert.throws(
    () => loadConfig({ NODE_ENV: 'production', MAGIC_LINK_DELIVERY: 'outbox' }),
    /COOKIE_SECRET is required when NODE_ENV=production/,
  )
})

test('production refuses the delivery that prints sign-in links into the page', () => {
  assert.throws(
    () => loadConfig({ ...PRODUCTION_ENVIRONMENT, MAGIC_LINK_DELIVERY: 'flash' }),
    /development-only delivery/,
  )
})

test('production boots with a secret and a delivery that leaves the application', () => {
  const config = loadConfig(PRODUCTION_ENVIRONMENT)

  assert.equal(config.environment, 'production')
  assert.equal(config.cookieSecret, 'a-secret-long-enough-to-sign')
  assert.equal(config.showsDebugMagicLinks, false)
})

test('the debug alert is off for any delivery that carries the link out of the page', () => {
  assert.equal(loadConfig({ MAGIC_LINK_DELIVERY: 'outbox' }).showsDebugMagicLinks, false)
  assert.equal(loadConfig({ MAGIC_LINK_DELIVERY: 'flash' }).showsDebugMagicLinks, true)
})

test('cookies are secure in production and in development behind an https public url', () => {
  assert.equal(loadConfig(PRODUCTION_ENVIRONMENT).secureCookies, true)
  assert.equal(loadConfig({ PUBLIC_URL: 'https://art-store.example.com' }).secureCookies, true)
  assert.equal(loadConfig({ PUBLIC_URL: 'http://localhost:4000' }).secureCookies, false)
  assert.equal(loadConfig({}).secureCookies, false)
})

test('a public url is kept as an origin, whatever path or trailing slash it arrived with', () => {
  assert.equal(loadConfig({ PUBLIC_URL: 'https://art-store.example.com/' }).publicUrl, 'https://art-store.example.com')
  assert.equal(loadConfig({ PUBLIC_URL: 'https://art-store.example.com/shop?a=1' }).publicUrl, 'https://art-store.example.com')
})

test('a public url that is not a url is refused', () => {
  assert.throws(() => loadConfig({ PUBLIC_URL: 'art-store.example.com' }))
})

test('the proxy is trusted only when the environment says so', () => {
  assert.equal(loadConfig({}).trustProxy, false)
  assert.equal(loadConfig({ TRUST_PROXY: 'true' }).trustProxy, true)
  assert.equal(loadConfig({ TRUST_PROXY: 'false' }).trustProxy, false)
  assert.throws(() => loadConfig({ TRUST_PROXY: 'maybe' }))
})
