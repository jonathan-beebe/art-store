import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { Clock } from '../../clock.ts'
import type { ActorType } from '../../core/auth/actor-type.ts'
import { digestMagicLinkToken } from '../../core/auth/magic-link-token.ts'
import { seedAdmins } from '../../db/seed-admins.ts'
import { newId } from '../../ids.ts'
import { buildTestApp, TEST_INSTANT, type TestApp } from '../../test/build-test-app.ts'
import { createAnonymousCustomer } from '../customers/create-anonymous-customer.ts'
import { runInTransaction } from '../transaction.ts'
import { signInWithMagicLink } from './sign-in-with-magic-link.ts'

const NOW = TEST_INSTANT.toISOString()

const IN_FIVE_MINUTES = new Date(TEST_INSTANT.getTime() + 5 * 60 * 1000).toISOString()

const FIVE_MINUTES_AGO = new Date(TEST_INSTANT.getTime() - 5 * 60 * 1000).toISOString()

let issued = 0

type LinkOptions = {
  actorType?: ActorType
  email?: string
  expiresAt?: string
  consumedAt?: string | null
  redirectTo?: string | null
}

async function issueLink({ db }: TestApp, options: LinkOptions = {}): Promise<string> {
  issued += 1
  const token = String(issued).padStart(64, '0')

  await db
    .insertInto('magicLinks')
    .values({
      id: newId('mlk', new Date()),
      tokenDigest: digestMagicLinkToken(token),
      email: options.email ?? 'artist@example.com',
      actorType: options.actorType ?? 'seller',
      redirectTo: options.redirectTo ?? null,
      expiresAt: options.expiresAt ?? IN_FIVE_MINUTES,
      consumedAt: options.consumedAt ?? null,
      createdAt: NOW,
    })
    .execute()

  return token
}

test('a token no link was issued for signs nobody in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const signIn = await signInWithMagicLink(testApp, {
    token: '0'.repeat(64),
    currentCustomerId: null,
  })

  assert.deepEqual(signIn, { outcome: 'unknown' })
})

test("a seller's first link creates the account and signs it in", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp, { email: 'newcomer@example.com' })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.equal(signIn.outcome, 'signedIn')
  assert.equal(signIn.outcome === 'signedIn' && signIn.actorType, 'seller')

  const sellers = await testApp.db.selectFrom('sellers').selectAll().execute()

  assert.equal(sellers.length, 1)
  assert.equal(sellers[0]?.email, 'newcomer@example.com')
  assert.equal(signIn.outcome === 'signedIn' && signIn.actorId, sellers[0]?.id)
})

test('a link works only once', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp)

  const first = await signInWithMagicLink(testApp, { token, currentCustomerId: null })
  const second = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.equal(first.outcome, 'signedIn')
  assert.deepEqual(second, { outcome: 'refused', actorType: 'seller', refusal: 'consumed' })
})

test('spending a link stamps when it was spent', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp)

  await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  const link = await testApp.db.selectFrom('magicLinks').selectAll().executeTakeFirstOrThrow()

  assert.equal(link.consumedAt, NOW)
})

test('a link past its expiry signs nobody in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp, { expiresAt: FIVE_MINUTES_AGO })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.deepEqual(signIn, { outcome: 'refused', actorType: 'seller', refusal: 'expired' })
  assert.equal((await testApp.db.selectFrom('sellers').selectAll().execute()).length, 0)
})

test('a refusal names the side of the marketplace that asked', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp, { actorType: 'customer', expiresAt: FIVE_MINUTES_AGO })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.equal(signIn.outcome === 'refused' && signIn.actorType, 'customer')
})

test('a link still inside its window works', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const oneMillisecondLeft = new Date(TEST_INSTANT.getTime() + 1).toISOString()
  const token = await issueLink(testApp, { expiresAt: oneMillisecondLeft })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.equal(signIn.outcome, 'signedIn')
})

test('a customer link claims the anonymous row the cookie names', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const anonymous = await createAnonymousCustomer(testApp)
  const token = await issueLink(testApp, { actorType: 'customer', email: 'buyer@example.com' })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: anonymous.id })

  assert.equal(signIn.outcome === 'signedIn' && signIn.actorId, anonymous.id)
  assert.equal((await testApp.db.selectFrom('customers').selectAll().execute()).length, 1)
})

test('it carries the destination the link holds', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp, { redirectTo: '/orders/7/pay' })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.equal(signIn.outcome === 'signedIn' && signIn.redirectTo, '/orders/7/pay')
})

test('a seeded admin signs in through a link like anyone else', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  await seedAdmins(testApp)
  const token = await issueLink(testApp, {
    actorType: 'admin',
    email: 'jonathan-beebe@outlook.com',
  })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  const admin = await testApp.db
    .selectFrom('admins')
    .selectAll()
    .where('email', '=', 'jonathan-beebe@outlook.com')
    .executeTakeFirstOrThrow()

  assert.equal(signIn.outcome === 'signedIn' && signIn.actorId, admin.id)
})

test('an admin link for an address nobody seeded creates no admin', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp, { actorType: 'admin', email: 'stranger@example.com' })

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.deepEqual(signIn, { outcome: 'refused', actorType: 'admin', refusal: 'unrecognized' })
  assert.equal((await testApp.db.selectFrom('admins').selectAll().execute()).length, 0)
})

/** Reads once, then refuses, so a claim fails after the link has been spent. */
function clockThatStopsAfterOneReading(instant: Date): Clock {
  let readings = 0

  return {
    now: () => {
      readings += 1
      if (readings > 1) throw new Error('the clock stopped')

      return new Date(instant)
    },
  }
}

test('a claim that throws leaves the link spendable', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const token = await issueLink(testApp, { email: 'newcomer@example.com' })

  await assert.rejects(
    signInWithMagicLink(
      { db: testApp.db, clock: clockThatStopsAfterOneReading(TEST_INSTANT) },
      { token, currentCustomerId: null },
    ),
  )

  const signIn = await signInWithMagicLink(testApp, { token, currentCustomerId: null })

  assert.equal(signIn.outcome, 'signedIn')
  assert.equal((await testApp.db.selectFrom('sellers').selectAll().execute()).length, 1)
})

test("signing in inside the caller's transaction joins it", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const anonymous = await createAnonymousCustomer(testApp)
  await testApp.db
    .insertInto('customers')
    .values({ id: newId('cus', new Date()), email: 'buyer@example.com', name: null, emailVerifiedAt: NOW, createdAt: NOW })
    .execute()
  const token = await issueLink(testApp, { actorType: 'customer', email: 'buyer@example.com' })

  const signIn = await runInTransaction(testApp, async (context) =>
    signInWithMagicLink(context, { token, currentCustomerId: anonymous.id }),
  )

  assert.equal(signIn.outcome, 'signedIn')
  assert.equal((await testApp.db.selectFrom('customerMerges').selectAll().execute()).length, 1)
})
