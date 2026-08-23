import { test } from 'node:test'
import assert from 'node:assert/strict'
import path from 'node:path'
import { loadConfig } from './config.ts'

// Mirrors the PUBLIC_ROOT convention config.ts and app.ts both resolve from
// their own directory, which is this file's directory too.
const DEFAULT_UPLOADS_DIR = path.join(import.meta.dirname, '..', 'public', 'uploads')

test('an empty environment yields the development defaults', () => {
  const config = loadConfig({})

  assert.deepEqual(config, {
    host: '0.0.0.0',
    port: 4000,
    databaseFile: 'storage/development.sqlite3',
    cookieSecret: 'art-store-prototype-cookie-secret',
    logLevel: 'info',
    magicLinkDelivery: 'flash',
    uploadsDir: DEFAULT_UPLOADS_DIR,
  })
})

test('the environment overrides every default', () => {
  const config = loadConfig({
    HOST: '127.0.0.1',
    PORT: '4100',
    DATABASE_FILE: 'storage/test.sqlite3',
    COOKIE_SECRET: 'a-secret-long-enough-to-sign',
    LOG_LEVEL: 'silent',
    MAGIC_LINK_DELIVERY: 'mail',
    UPLOADS_DIR: '/var/data/uploads',
  })

  assert.equal(config.host, '127.0.0.1')
  assert.equal(config.port, 4100)
  assert.equal(config.databaseFile, 'storage/test.sqlite3')
  assert.equal(config.cookieSecret, 'a-secret-long-enough-to-sign')
  assert.equal(config.logLevel, 'silent')
  assert.equal(config.magicLinkDelivery, 'mail')
  assert.equal(config.uploadsDir, '/var/data/uploads')
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
