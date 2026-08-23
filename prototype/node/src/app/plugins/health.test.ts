import { test } from 'node:test'
import assert from 'node:assert/strict'
import path from 'node:path'
import { tmpdir } from 'node:os'
import { buildApp } from '../app.ts'
import { fixedClock } from '../clock.ts'
import { flashMagicLinkDelivery } from '../delivery/flash-magic-link-delivery.ts'
import { IN_MEMORY_DATABASE, openDatabase } from '../db/database.ts'
import { buildTestApp, TEST_CONFIG, TEST_INSTANT } from '../test/build-test-app.ts'

type HealthBody = {
  status: string
  checks: { database: string; migrations: string }
  uptimeSeconds: number
}

test('a fully migrated app over a live database answers 200 ok', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/health' })
  const body = response.json<HealthBody>()

  assert.equal(response.statusCode, 200)
  assert.deepEqual(body, {
    status: 'ok',
    checks: { database: 'ok', migrations: 'current' },
    uptimeSeconds: body.uptimeSeconds,
  })
  assert.equal(typeof body.uptimeSeconds, 'number')
})

test('an unmigrated database answers 503 with a pending migration', async (t) => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  t.after(() => db.destroy())

  const app = buildApp({
    db,
    clock: fixedClock(TEST_INSTANT),
    config: { ...TEST_CONFIG, uploadsDir: path.join(tmpdir(), 'art-store-test-uploads-unused') },
    magicLinkDelivery: flashMagicLinkDelivery,
  })
  t.after(() => app.close())

  const response = await app.inject({ method: 'GET', url: '/health' })
  const body = response.json<HealthBody>()

  assert.equal(response.statusCode, 503)
  assert.equal(body.status, 'unavailable')
  assert.equal(body.checks.migrations, 'pending')
})

test('a draining app answers 503 draining even though its checks pass', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  app.draining = true

  const response = await app.inject({ method: 'GET', url: '/health' })
  const body = response.json<HealthBody>()

  assert.equal(response.statusCode, 503)
  assert.deepEqual(body, {
    status: 'draining',
    checks: { database: 'ok', migrations: 'current' },
    uptimeSeconds: body.uptimeSeconds,
  })
})
