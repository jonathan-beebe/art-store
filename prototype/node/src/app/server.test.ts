import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { buildApp } from './app.ts'
import { fixedClock } from './clock.ts'
import { flashMagicLinkDelivery } from './delivery/flash-magic-link-delivery.ts'
import { IN_MEMORY_DATABASE, openDatabase } from './db/database.ts'
import { migrateToLatest } from './db/migrator.ts'
import { armGracefulShutdown } from './server.ts'
import { TEST_CONFIG, TEST_INSTANT } from './test/build-test-app.ts'

/** Polls until `predicate` is true or the attempts run out, off the event
 * loop rather than a timer — the shutdown handler runs on its own turns. */
async function waitUntil(predicate: () => boolean): Promise<void> {
  for (let attempt = 0; attempt < 200; attempt += 1) {
    if (predicate()) return
    await new Promise((resolve) => setImmediate(resolve))
  }
}

/**
 * A migrated app not yet `ready()` — `onClose` hooks must be added before
 * that, so each test attaches its own and readies the app itself.
 */
async function buildUnreadyTestApp() {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)
  const uploadsDir = await mkdtemp(path.join(tmpdir(), 'art-store-test-uploads-'))

  const app = buildApp({
    db,
    clock: fixedClock(TEST_INSTANT),
    config: { ...TEST_CONFIG, uploadsDir },
    magicLinkDelivery: flashMagicLinkDelivery,
  })

  return { app, db }
}

test('SIGTERM flips draining, closes the app, and leaves the exit code untouched', async (t) => {
  const { app, db } = await buildUnreadyTestApp()
  let closed = false
  app.addHook('onClose', async () => {
    closed = true
  })
  await app.ready()
  t.after(async () => {
    process.removeAllListeners('SIGTERM')
    process.removeAllListeners('SIGINT')
    await db.destroy()
  })

  armGracefulShutdown(app)
  assert.equal(app.draining, false)

  process.emit('SIGTERM')
  await waitUntil(() => closed)

  assert.equal(app.draining, true)
  assert.equal(process.exitCode, undefined)
})

test('a close() that rejects sets the exit code to 1', async (t) => {
  const { app, db } = await buildUnreadyTestApp()
  app.addHook('onClose', async () => {
    throw new Error('boom')
  })
  await app.ready()
  t.after(async () => {
    process.removeAllListeners('SIGINT')
    process.removeAllListeners('SIGTERM')
    process.exitCode = undefined
    await db.destroy()
  })

  armGracefulShutdown(app)
  process.emit('SIGINT')
  await waitUntil(() => process.exitCode === 1)

  assert.equal(process.exitCode, 1)
})

test('a close() that hangs force-exits after the deadline', async (t) => {
  const { app, db } = await buildUnreadyTestApp()
  app.addHook('onClose', () => new Promise(() => {}))
  await app.ready()
  t.after(async () => {
    process.removeAllListeners('SIGINT')
    process.removeAllListeners('SIGTERM')
    // The onClose hook above never resolves, so app.close() (already in
    // flight from the SIGTERM below) never will either — only the database
    // needs releasing.
    await db.destroy()
  })

  const exitCalls: Array<number | undefined> = []
  t.mock.method(process, 'exit', ((code?: number) => {
    exitCalls.push(code)
  }) as never)
  t.mock.timers.enable({ apis: ['setTimeout'] })

  armGracefulShutdown(app)
  process.emit('SIGTERM')
  await Promise.resolve()
  t.mock.timers.tick(10_000)

  assert.deepEqual(exitCalls, [1])
})
