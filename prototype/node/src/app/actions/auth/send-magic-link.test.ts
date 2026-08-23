import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, TEST_INSTANT } from '../../test/build-test-app.ts'
import { sendMagicLink } from './send-magic-link.ts'
import { digestMagicLinkToken } from '../../core/auth/magic-link-token.ts'
import type { MagicLinkDelivery, MagicLinkMessage } from '../../delivery/magic-link-delivery.ts'
import type { Flash } from '../../plugins/flash.ts'

const MAGIC_LINK_ORIGIN = 'http://magic.test/'

function magicLinkUrl(token: string): string {
  return `${MAGIC_LINK_ORIGIN}${token}`
}

function recordingDelivery(): MagicLinkDelivery & { readonly deliveries: MagicLinkMessage[] } {
  const deliveries: MagicLinkMessage[] = []

  return {
    deliveries,
    deliver(message: MagicLinkMessage): Flash {
      deliveries.push(message)
      return { debugMagicLink: message.url }
    },
  }
}

test('it stores one row for the address', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  const rows = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').execute()
  assert.equal(rows.length, 1)
})

test('it normalizes the address before storing', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: '  Artist@Example.COM ', actorType: 'seller' },
  )

  const row = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').executeTakeFirst()
  assert.ok(row !== undefined)
})

test('it stores only the digest of the token it handed out', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  const token = delivery.deliveries[0]?.url.slice(MAGIC_LINK_ORIGIN.length)
  const row = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').executeTakeFirstOrThrow()
  assert.equal(row.tokenDigest, digestMagicLinkToken(token ?? ''))
})

test('the delivered url is the one magicLinkUrl built and the token is 64 hex characters', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  const url = delivery.deliveries[0]?.url ?? ''
  assert.match(url, new RegExp(`^${MAGIC_LINK_ORIGIN}[0-9a-f]{64}$`))
})

test('it delivers to the normalized address', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: '  Artist@Example.COM ', actorType: 'seller' },
  )

  assert.equal(delivery.deliveries[0]?.email, 'artist@example.com')
})

test('it records the actor type it was asked for', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'buyer@example.com', actorType: 'customer' },
  )

  const row = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'buyer@example.com').executeTakeFirstOrThrow()
  assert.equal(row.actorType, 'customer')
})

test('it carries redirectTo when given', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller', redirectTo: '/seller/listings' },
  )

  const row = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').executeTakeFirstOrThrow()
  assert.equal(row.redirectTo, '/seller/listings')
})

test('it stores null for redirectTo when not given', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  const row = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').executeTakeFirstOrThrow()
  assert.equal(row.redirectTo, null)
})

test("it expires the link 15 minutes after the clock's now", async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  const row = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').executeTakeFirstOrThrow()
  assert.equal(row.expiresAt, new Date(TEST_INSTANT.getTime() + 15 * 60 * 1000).toISOString())
})

test('it leaves consumedAt null', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  const row = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').executeTakeFirstOrThrow()
  assert.equal(row.consumedAt, null)
})

test('two calls for the same address store two different digests', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )
  await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  const rows = await db.selectFrom('magicLinks').selectAll().where('email', '=', 'artist@example.com').execute()
  assert.equal(rows.length, 2)
  assert.notEqual(rows[0]?.tokenDigest, rows[1]?.tokenDigest)
})

test('it returns whatever the delivery returned', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const delivery = recordingDelivery()

  const flash = await sendMagicLink(
    { db, clock, delivery, magicLinkUrl },
    { email: 'artist@example.com', actorType: 'seller' },
  )

  assert.equal(flash.debugMagicLink, delivery.deliveries[0]?.url)
})
