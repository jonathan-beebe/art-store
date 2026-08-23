import { test } from 'node:test'
import assert from 'node:assert/strict'
import { loadConfig } from './config.ts'

test('an empty environment yields the development defaults', () => {
  const config = loadConfig({})

  assert.deepEqual(config, {
    host: '0.0.0.0',
    port: 3000,
    databaseFile: 'storage/development.sqlite3',
    cookieSecret: 'art-store-prototype-cookie-secret',
    logLevel: 'info',
  })
})

test('the environment overrides every default', () => {
  const config = loadConfig({
    HOST: '127.0.0.1',
    PORT: '4000',
    DATABASE_FILE: 'storage/test.sqlite3',
    COOKIE_SECRET: 'a-secret-long-enough-to-sign',
    LOG_LEVEL: 'silent',
  })

  assert.equal(config.host, '127.0.0.1')
  assert.equal(config.port, 4000)
  assert.equal(config.databaseFile, 'storage/test.sqlite3')
  assert.equal(config.cookieSecret, 'a-secret-long-enough-to-sign')
  assert.equal(config.logLevel, 'silent')
})

test('unrelated environment variables are ignored', () => {
  const config = loadConfig({ PATH: '/usr/bin', UNRELATED: 'noise' })

  assert.equal(config.port, 3000)
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
