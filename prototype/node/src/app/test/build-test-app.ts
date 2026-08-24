import { mkdir, mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import type { FastifyInstance, LightMyRequestResponse } from 'fastify'
import { claimSellerIdentity } from '../actions/auth/claim-seller-identity.ts'
import { findAdminByEmail } from '../actions/auth/find-admin-by-email.ts'
import { claimCustomerIdentity } from '../actions/customers/claim-customer-identity.ts'
import { createAnonymousCustomer } from '../actions/customers/create-anonymous-customer.ts'
import { buildApp, type AppDependencies } from '../app.ts'
import { fixedClock, type Clock } from '../clock.ts'
import type { AppConfig } from '../config.ts'
import type { ActorId, AdminId, CustomerId, SellerId } from '../core/ids/entity-ids.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../db/database.ts'
import { migrateToLatest } from '../db/migrator.ts'
import { seedAdmins } from '../db/seed-admins.ts'
import { flashMagicLinkDelivery } from '../delivery/flash-magic-link-delivery.ts'
import { flashSchema } from '../plugins/flash.ts'

/** Frozen so payout periods and link expiries read the same whatever day it is. */
export const TEST_INSTANT = new Date('2026-08-24T12:00:00.000Z')

/** `uploadsDir` and `outboxDir` here are never read: `buildTestApp` always
 * builds fresh per-test temp directories unless a caller supplies its own
 * `config`. */
export const TEST_CONFIG: AppConfig = {
  environment: 'test',
  host: '127.0.0.1',
  port: 0,
  databaseFile: IN_MEMORY_DATABASE,
  cookieSecret: 'test-cookie-secret-long-enough',
  logLevel: 'silent',
  magicLinkDelivery: 'flash',
  uploadsDir: path.join(tmpdir(), 'art-store-test-uploads-unused'),
  outboxDir: path.join(tmpdir(), 'art-store-test-outbox-unused'),
  publicUrl: null,
  trustProxy: false,
  secureCookies: false,
  showsDebugMagicLinks: true,
}

export type TestAppOverrides = Partial<AppDependencies> & {
  /** Raises the level above `TEST_CONFIG`'s `silent`, for a test about the log. */
  logLevel?: AppConfig['logLevel']
}

export type TestApp = {
  app: FastifyInstance
  db: AppDatabase
  clock: Clock
  close: () => Promise<void>
}

/**
 * Builds the whole application over a migrated in-memory database, ready for
 * `app.inject`. Pass `t.after(close)` so the database goes with the test.
 */
export async function buildTestApp(overrides: TestAppOverrides = {}): Promise<TestApp> {
  const db = overrides.db ?? openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const clock = overrides.clock ?? fixedClock(TEST_INSTANT)

  // A config override brings its own directories; otherwise each test app gets
  // isolated temp ones, removed with everything else it built.
  let temporaryRoot: string | null = null
  let config = overrides.config
  if (config === undefined) {
    temporaryRoot = await mkdtemp(path.join(tmpdir(), 'art-store-test-'))
    const uploadsDir = path.join(temporaryRoot, 'uploads')
    await mkdir(uploadsDir)
    config = {
      ...TEST_CONFIG,
      uploadsDir,
      outboxDir: path.join(temporaryRoot, 'outbox'),
      logLevel: overrides.logLevel ?? TEST_CONFIG.logLevel,
    }
  }

  const app = buildApp({
    db,
    clock,
    config,
    magicLinkDelivery: overrides.magicLinkDelivery ?? flashMagicLinkDelivery,
    loggerStream: overrides.loggerStream,
  })
  await app.ready()

  return {
    app,
    db,
    clock,
    close: async () => {
      await app.close()
      await db.destroy()
      if (temporaryRoot !== null) await rm(temporaryRoot, { recursive: true, force: true })
    },
  }
}

/** An identity plus the cookie jar `app.inject` needs to present it. */
export type SignedInActor<Id extends ActorId = ActorId> = {
  id: Id
  cookies: Record<string, string>
}

/**
 * Signs in without walking the magic-link flow, for tests about something else.
 * The flow itself is covered where it lives, in `app/sites/auth`.
 */
export async function signInAsSeller(
  { app, db, clock }: TestApp,
  email = 'artist@example.com',
): Promise<SignedInActor<SellerId>> {
  const seller = await claimSellerIdentity({ db, clock }, email)

  return { id: seller.id, cookies: { seller_id: app.signCookie(seller.id) } }
}

export async function signInAsCustomer(
  { app, db, clock }: TestApp,
  email = 'buyer@example.com',
): Promise<SignedInActor<CustomerId>> {
  const customer = await claimCustomerIdentity({ db, clock }, { email, currentCustomerId: null })

  return { id: customer.id, cookies: { customer_id: app.signCookie(customer.id) } }
}

/** A storefront visitor who has given no address, as the identity hook creates one. */
export async function browseAsAnonymousCustomer({
  app,
  db,
  clock,
}: TestApp): Promise<SignedInActor<CustomerId>> {
  const customer = await createAnonymousCustomer({ db, clock })

  return { id: customer.id, cookies: { customer_id: app.signCookie(customer.id) } }
}

export async function signInAsAdmin(
  { app, db, clock }: TestApp,
  email = 'jonathan-beebe@outlook.com',
): Promise<SignedInActor<AdminId>> {
  await seedAdmins({ db, clock })
  const admin = await findAdminByEmail({ db }, email)

  if (admin === null) throw new Error(`no seeded admin for ${email}`)

  return { id: admin.id, cookies: { admin_id: app.signCookie(admin.id) } }
}

/** The sign-in URL a response flashed for the debug alert to print. */
export function takeDebugMagicLink({ app }: TestApp, response: LightMyRequestResponse): string {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')

  if (cookie === undefined) throw new Error('the response flashed nothing')

  const unsigned = app.unsignCookie(String(cookie.value))
  const flash = flashSchema.parse(JSON.parse(unsigned.value ?? '{}'))
  const link = flash.debugMagicLink

  if (link === undefined) throw new Error('the flash carries no magic link')

  return link
}
