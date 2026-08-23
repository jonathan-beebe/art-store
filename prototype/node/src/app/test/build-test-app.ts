import type { FastifyInstance, LightMyRequestResponse } from 'fastify'
import { claimSellerIdentity } from '../actions/auth/claim-seller-identity.ts'
import { findAdminByEmail } from '../actions/auth/find-admin-by-email.ts'
import { claimCustomerIdentity } from '../actions/customers/claim-customer-identity.ts'
import { createAnonymousCustomer } from '../actions/customers/create-anonymous-customer.ts'
import { buildApp, type AppDependencies } from '../app.ts'
import { fixedClock, type Clock } from '../clock.ts'
import type { AppConfig } from '../config.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'
import { seedAdmins } from '../db/seed-admins.ts'
import { flashMagicLinkDelivery } from '../delivery/flash-magic-link-delivery.ts'

/** Frozen so payout periods and link expiries read the same whatever day it is. */
export const TEST_INSTANT = new Date('2026-08-24T12:00:00.000Z')

export const TEST_CONFIG: AppConfig = {
  host: '127.0.0.1',
  port: 0,
  databaseFile: IN_MEMORY_DATABASE,
  cookieSecret: 'test-cookie-secret-long-enough',
  logLevel: 'silent',
  magicLinkDelivery: 'flash',
}

export type TestApp = {
  app: FastifyInstance
  db: AppDatabase
  clock: Clock
  close(): Promise<void>
}

/**
 * Builds the whole application over a migrated in-memory database, ready for
 * `app.inject`. Pass `t.after(close)` so the database goes with the test.
 */
export async function buildTestApp(overrides: Partial<AppDependencies> = {}): Promise<TestApp> {
  const db = overrides.db ?? openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const clock = overrides.clock ?? fixedClock(TEST_INSTANT)
  const app = buildApp({
    db,
    clock,
    config: overrides.config ?? TEST_CONFIG,
    magicLinkDelivery: overrides.magicLinkDelivery ?? flashMagicLinkDelivery,
  })
  await app.ready()

  return {
    app,
    db,
    clock,
    close: async () => {
      await app.close()
      await db.destroy()
    },
  }
}

/** An identity plus the cookie jar `app.inject` needs to present it. */
export type SignedInActor = {
  id: number
  cookies: Record<string, string>
}

/**
 * Signs in without walking the magic-link flow, for tests about something else.
 * The flow itself is covered where it lives, in `app/sites/auth`.
 */
export async function signInAsSeller(
  { app, db, clock }: TestApp,
  email = 'artist@example.com',
): Promise<SignedInActor> {
  const seller = await claimSellerIdentity({ db, clock }, email)

  return { id: seller.id, cookies: { seller_id: app.signCookie(String(seller.id)) } }
}

export async function signInAsCustomer(
  { app, db, clock }: TestApp,
  email = 'buyer@example.com',
): Promise<SignedInActor> {
  const customer = await claimCustomerIdentity({ db, clock }, { email, currentCustomerId: null })

  return { id: customer.id, cookies: { customer_id: app.signCookie(String(customer.id)) } }
}

/** A storefront visitor who has given no address, as the identity hook creates one. */
export async function browseAsAnonymousCustomer({
  app,
  db,
  clock,
}: TestApp): Promise<SignedInActor> {
  const customer = await createAnonymousCustomer({ db, clock })

  return { id: customer.id, cookies: { customer_id: app.signCookie(String(customer.id)) } }
}

export async function signInAsAdmin(
  { app, db, clock }: TestApp,
  email = 'jonathan-beebe@outlook.com',
): Promise<SignedInActor> {
  await seedAdmins({ db, clock })
  const admin = await findAdminByEmail({ db }, email)

  if (admin === null) throw new Error(`no seeded admin for ${email}`)

  return { id: admin.id, cookies: { admin_id: app.signCookie(String(admin.id)) } }
}

/** The sign-in URL a response flashed for the debug alert to print. */
export function takeDebugMagicLink({ app }: TestApp, response: LightMyRequestResponse): string {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')

  if (cookie === undefined) throw new Error('the response flashed nothing')

  const unsigned = app.unsignCookie(String(cookie.value))
  const flash: unknown = JSON.parse(unsigned.value ?? '{}')
  const link = (flash as { debugMagicLink?: string }).debugMagicLink

  if (link === undefined) throw new Error('the flash carries no magic link')

  return link
}
