import { randomUUID } from 'node:crypto'
import { mkdir, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import type { FastifyInstance, InjectOptions, LightMyRequestResponse } from 'fastify'
import { claimSellerIdentity } from '../actions/auth/claim-seller-identity.ts'
import { findAdminByEmail } from '../actions/auth/find-admin-by-email.ts'
import { claimCustomerIdentity } from '../actions/customers/claim-customer-identity.ts'
import { createAnonymousCustomer } from '../actions/customers/create-anonymous-customer.ts'
import { buildApp, type AppDependencies } from '../app.ts'
import { fixedClock, type Clock } from '../clock.ts'
import type { AppConfig } from '../config.ts'
import { CSRF_FIELD_NAME, csrfToken } from '../core/security/csrf-token.ts'
import type { ActorId, AdminId, CustomerId, SellerId } from '../core/ids/entity-ids.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from '../db/database.ts'
import { seedAdmins } from '../db/seed-admins.ts'
import { flashMagicLinkDelivery } from '../delivery/flash-magic-link-delivery.ts'
import { newId } from '../ids.ts'
import { flashSchema } from '../plugins/flash.ts'
import { applySchemaTemplate } from './schema-template.ts'

const STATE_CHANGING_METHODS: ReadonlySet<string> = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

/**
 * Attaches a valid double-submit CSRF token to a state-changing `app.inject`
 * call, the way a real browser carries the `sid` cookie from the page it
 * loaded a form on to the request that form submits — so an integration test
 * about something else does not have to derive the token by hand. A test that
 * already set its own `sid` cookie or `_csrf_token` field is left alone,
 * which is how `csrf.test.ts` exercises the guard's failure paths through
 * this same `app.inject`.
 *
 * `defaultSid` is one fixed id for the whole test app, not a fresh one per
 * call: a test that compares two separately-injected pages byte for byte
 * (a shared layout's rendered sign-out form among them) needs both to have
 * carried the same session and so derived the same token.
 */
function withAutomaticCsrfToken(
  rawInject: FastifyInstance['inject'],
  secret: string,
  defaultSid: string,
): FastifyInstance['inject'] {
  const inject = (opts: InjectOptions | string) => {
    if (typeof opts === 'string') return rawInject(opts)

    const method = (opts.method ?? 'GET').toString().toUpperCase()
    if (!STATE_CHANGING_METHODS.has(method)) return rawInject(opts)

    const cookies: Record<string, string> = { ...opts.cookies }
    cookies.sid ??= defaultSid

    const withToken: InjectOptions = {
      ...opts,
      cookies,
      payload: attachCsrfToken(opts.payload, opts.headers, cookies.sid, secret),
    }

    return rawInject(withToken)
  }

  return inject as FastifyInstance['inject']
}

/** The `Content-Type` header value, wherever `attachCsrfToken` finds one — a
 * `light-my-request` header bag may hold one value per name or an array of
 * them, and the field's own name is matched case-insensitively. */
function contentTypeOf(headers: InjectOptions['headers']): string | null {
  if (headers === undefined) return null

  const entry = Object.entries(headers).find(([name]) => name.toLowerCase() === 'content-type')
  if (entry === undefined) return null

  const [, value] = entry
  const first = Array.isArray(value) ? value[0] : value

  return typeof first === 'string' ? first : null
}

function attachCsrfToken(
  payload: unknown,
  headers: InjectOptions['headers'],
  sid: string,
  secret: string,
): InjectOptions['payload'] {
  const token = csrfToken(sid, secret)

  if (payload === undefined || payload === null) return { [CSRF_FIELD_NAME]: token }
  if (Buffer.isBuffer(payload)) return attachToMultipartBuffer(payload, headers, token)
  if (typeof payload === 'string') return attachToUrlencodedString(payload, headers, token)
  if (typeof payload !== 'object') return payload

  return attachToPlainObject(payload as Record<string, unknown>, token)
}

function attachToMultipartBuffer(payload: Buffer, headers: InjectOptions['headers'], token: string): Buffer {
  const boundary = multipartBoundary(contentTypeOf(headers))

  return boundary === null ? payload : withMultipartCsrfField(payload, boundary, token)
}

function attachToUrlencodedString(payload: string, headers: InjectOptions['headers'], token: string): string {
  const isUrlencoded = contentTypeOf(headers)?.includes('application/x-www-form-urlencoded') === true
  if (!isUrlencoded || payload.includes(`${CSRF_FIELD_NAME}=`)) return payload

  const separator = payload.length === 0 ? '' : '&'
  return `${payload}${separator}${CSRF_FIELD_NAME}=${token}`
}

function attachToPlainObject(submitted: Record<string, unknown>, token: string): Record<string, unknown> {
  return CSRF_FIELD_NAME in submitted ? submitted : { ...submitted, [CSRF_FIELD_NAME]: token }
}

function multipartBoundary(contentType: string | null): string | null {
  if (contentType === null) return null

  const match = /multipart\/form-data;\s*boundary=(\S+)/.exec(contentType)
  return match?.[1] ?? null
}

/**
 * A hand-built multipart body, with one more field spliced in just ahead of
 * its closing boundary — `@fastify/multipart`'s parser reads a field from
 * anywhere in the body, so where it lands among the others carries no
 * meaning of its own.
 */
function withMultipartCsrfField(payload: Buffer, boundary: string, token: string): Buffer {
  const closing = Buffer.from(`--${boundary}--`)
  const closingIndex = payload.lastIndexOf(closing)
  if (closingIndex === -1) return payload

  const field = Buffer.from(
    `--${boundary}\r\nContent-Disposition: form-data; name="${CSRF_FIELD_NAME}"\r\n\r\n${token}\r\n`,
  )

  return Buffer.concat([payload.subarray(0, closingIndex), field, payload.subarray(closingIndex)])
}

/** Frozen so payout periods and link expiries read the same whatever day it is. */
export const TEST_INSTANT = new Date('2026-08-24T12:00:00.000Z')

/** `uploadsDir` and `outboxDir` here are never read: `buildTestApp` always
 * builds fresh per-test temp directories unless a caller supplies its own
 * `config`, and those directories are named but not created until something
 * writes into them. */
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
  staleOrderHours: 24,
  publicUrl: null,
  trustedProxies: null,
  secureCookies: false,
  showsDebugMagicLinks: true,
  // Off by default so a test unrelated to rate limiting can hit a guarded
  // route as many times as its scenario needs; a test about one limit
  // overrides just that entry (see `rate-limit.test.ts` and each guarded
  // route's own suite).
  rateLimits: {
    magic_link_request: 'off',
    magic_link_consume: 'off',
    message_post: 'off',
    conversation_open: 'off',
    checkout: 'off',
    payment_attempt: 'off',
    listing_write: 'off',
  },
}

export type TestAppOverrides = Partial<AppDependencies> & {
  /** Raises the level above `TEST_CONFIG`'s `silent`, for a test about the log. */
  logLevel?: AppConfig['logLevel']
}

export type TestApp = {
  app: FastifyInstance
  db: AppDatabase
  clock: Clock
  /** `app.inject` before `buildTestApp` wrapped it with an automatic CSRF
   * token — for a test that needs to submit a state-changing request with no
   * token, a foreign one, or one derived from a `sid` it does not also send. */
  rawInject: FastifyInstance['inject']
  close: () => Promise<void>
}

/**
 * The config and temp root `buildTestApp` uses when no override supplies its
 * own config: a fresh `temporaryRoot` naming an uploads and an outbox
 * directory beneath it. Neither directory is created here —
 * `saveUploadedListingImage` and `drainOutbox` each `mkdir` its directory on
 * demand, and `@fastify/static` tolerates a missing root — except when the
 * test surfaces the log, where that static plugin's missing-root warning
 * would land in the captured stream, so only that case creates `uploadsDir`
 * up front.
 */
async function buildIsolatedTestConfig(
  overrides: TestAppOverrides,
): Promise<{ config: AppConfig; temporaryRoot: string }> {
  const temporaryRoot = path.join(tmpdir(), `art-store-test-${randomUUID()}`)
  const uploadsDir = path.join(temporaryRoot, 'uploads')

  if (overrides.logLevel !== undefined || overrides.loggerStream !== undefined) {
    await mkdir(uploadsDir, { recursive: true })
  }

  return {
    temporaryRoot,
    config: {
      ...TEST_CONFIG,
      uploadsDir,
      outboxDir: path.join(temporaryRoot, 'outbox'),
      logLevel: overrides.logLevel ?? TEST_CONFIG.logLevel,
    },
  }
}

/**
 * Builds the whole application over a migrated in-memory database, ready for
 * `app.inject`. Pass `t.after(close)` so the database goes with the test.
 */
export async function buildTestApp(overrides: TestAppOverrides = {}): Promise<TestApp> {
  const db = overrides.db ?? openDatabase(IN_MEMORY_DATABASE)
  await applySchemaTemplate(db)

  const clock = overrides.clock ?? fixedClock(TEST_INSTANT)

  // A config override brings its own directories; otherwise each test app gets
  // isolated temp ones, removed with everything else it built.
  let temporaryRoot: string | null = null
  let config = overrides.config
  if (config === undefined) {
    ({ config, temporaryRoot } = await buildIsolatedTestConfig(overrides))
  }

  const app = buildApp({
    db,
    clock,
    config,
    magicLinkDelivery: overrides.magicLinkDelivery ?? flashMagicLinkDelivery,
    loggerStream: overrides.loggerStream,
  })
  await app.ready()

  const rawInject = app.inject.bind(app)
  app.inject = withAutomaticCsrfToken(rawInject, config.cookieSecret, newId('ses', clock.now()))

  return {
    app,
    db,
    clock,
    rawInject,
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
