import path from 'node:path'
import { z } from 'zod'
import { MAGIC_LINK_DELIVERIES } from './delivery/magic-link-delivery.ts'
import { RATE_LIMIT_NAMES, type RateLimitName } from './core/rate-limit/rate-limit-name.ts'
import { parseRateLimit, type RateLimit } from './core/rate-limit/rate-limit-value.ts'

export const ENVIRONMENTS = ['development', 'test', 'production'] as const

export type Environment = (typeof ENVIRONMENTS)[number]

const LOG_LEVELS = ['fatal', 'error', 'warn', 'info', 'debug', 'trace', 'silent'] as const

// Same convention app.ts uses for PUBLIC_ROOT: the public directory sits
// beside app/ at the project root.
const PUBLIC_ROOT = path.join(import.meta.dirname, '..', 'public')
const DEFAULT_UPLOADS_DIR = path.join(PUBLIC_ROOT, 'uploads')

// Signs the flash and identity cookies outside production, so a clone runs
// with no configuration. Production brings its own secret or does not boot.
const DEVELOPMENT_COOKIE_SECRET = 'art-store-prototype-cookie-secret'

/** `docs/alignment.md` §3, in table order: the env variable and default for
 * each of the seven limits. */
const RATE_LIMIT_ENV: Record<RateLimitName, { variable: string; default: string }> = {
  magic_link_request: { variable: 'RATE_LIMIT_MAGIC_LINK_REQUEST', default: '5/15m' },
  magic_link_consume: { variable: 'RATE_LIMIT_MAGIC_LINK_CONSUME', default: '20/15m' },
  message_post: { variable: 'RATE_LIMIT_MESSAGE_POST', default: '30/1h' },
  conversation_open: { variable: 'RATE_LIMIT_CONVERSATION_OPEN', default: '10/1h' },
  checkout: { variable: 'RATE_LIMIT_CHECKOUT', default: '10/1h' },
  payment_attempt: { variable: 'RATE_LIMIT_PAYMENT_ATTEMPT', default: '5/15m' },
  listing_write: { variable: 'RATE_LIMIT_LISTING_WRITE', default: '60/1h' },
}

const environmentVariables = z.object({
  NODE_ENV: z.enum(ENVIRONMENTS).default('development'),
  HOST: z.string().min(1).default('0.0.0.0'),
  PORT: z.coerce.number().int().positive().default(4000),
  DATABASE_FILE: z.string().min(1).default('storage/development.sqlite3'),
  COOKIE_SECRET: z.string().min(16).optional(),
  LOG_LEVEL: z.enum(LOG_LEVELS).default('info'),
  MAGIC_LINK_DELIVERY: z.enum(MAGIC_LINK_DELIVERIES).default('flash'),
  UPLOADS_DIR: z.string().min(1).default(DEFAULT_UPLOADS_DIR),
  OUTBOX_DIR: z.string().min(1).default('storage/outbox'),
  STALE_ORDER_HOURS: z.coerce.number().int().positive().default(24),
  // Reduced to an origin: every route is served from the root, so a path or
  // query on the way in is noise every link built from it would carry.
  PUBLIC_URL: z
    .url()
    .transform((value) => new URL(value).origin)
    .optional(),
  TRUST_PROXY: z.stringbool().default(false),
  // A comma-separated list of proxy addresses/CIDRs Fastify's own `trustProxy`
  // option accepts. Unset, `request.ip` reads the socket; set, it reads the
  // first forwarded hop past those addresses — never any hop a caller can
  // forge by sending its own `X-Forwarded-For`.
  TRUSTED_PROXIES: z.string().min(1).optional(),
  RATE_LIMIT_MAGIC_LINK_REQUEST: z.string().min(1).optional(),
  RATE_LIMIT_MAGIC_LINK_CONSUME: z.string().min(1).optional(),
  RATE_LIMIT_MESSAGE_POST: z.string().min(1).optional(),
  RATE_LIMIT_CONVERSATION_OPEN: z.string().min(1).optional(),
  RATE_LIMIT_CHECKOUT: z.string().min(1).optional(),
  RATE_LIMIT_PAYMENT_ATTEMPT: z.string().min(1).optional(),
  RATE_LIMIT_LISTING_WRITE: z.string().min(1).optional(),
})

type ParsedEnvironment = z.output<typeof environmentVariables>

/** Everything a production boot must not run with, refused before it starts. */
function refuseUnsafeProduction(parsed: ParsedEnvironment): void {
  if (parsed.NODE_ENV !== 'production') return

  if (parsed.COOKIE_SECRET === undefined) {
    throw new Error(
      'COOKIE_SECRET is required when NODE_ENV=production: the identity cookies are ' +
        'signed with it, and a shared default makes an admin cookie forgeable.',
    )
  }

  if (parsed.MAGIC_LINK_DELIVERY === 'flash') {
    throw new Error(
      'MAGIC_LINK_DELIVERY=flash prints the sign-in link into the page that asked for ' +
        'it, which makes it a development-only delivery. Choose another delivery when ' +
        'NODE_ENV=production.',
    )
  }
}

/** The comma-separated list `TRUSTED_PROXIES` carries, or `null` for none —
 * what Fastify's `trustProxy` server option reads besides a plain boolean. */
function parseTrustedProxies(raw: string | undefined): string[] | null {
  if (raw === undefined) return null

  const addresses = raw
    .split(',')
    .map((address) => address.trim())
    .filter((address) => address.length > 0)

  return addresses.length === 0 ? null : addresses
}

/**
 * Every limit `docs/alignment.md` §3 names, parsed from its own env variable
 * or its default. A malformed value throws with the variable it came from, so
 * `loadConfig` refuses to boot rather than serve an unbounded route.
 */
function rateLimitsFrom(parsed: ParsedEnvironment): Record<RateLimitName, RateLimit> {
  const entries = RATE_LIMIT_NAMES.map((name) => {
    const { variable, default: defaultValue } = RATE_LIMIT_ENV[name]
    const raw = parsed[variable as keyof ParsedEnvironment] as string | undefined
    const result = parseRateLimit(raw, defaultValue)

    if (!result.ok) throw new Error(`${variable}: ${result.error}`)

    return [name, result.value] as const
  })

  return Object.fromEntries(entries) as Record<RateLimitName, RateLimit>
}

/** The SCREAMING_CASE environment as the camelCase deployment the app reads. */
function toAppConfig(parsed: ParsedEnvironment) {
  refuseUnsafeProduction(parsed)

  const isProduction = parsed.NODE_ENV === 'production'
  // The origin links are built from, or null to build them from the request.
  const publicUrl = parsed.PUBLIC_URL ?? null

  return {
    environment: parsed.NODE_ENV,
    host: parsed.HOST,
    port: parsed.PORT,
    databaseFile: parsed.DATABASE_FILE,
    cookieSecret: parsed.COOKIE_SECRET ?? DEVELOPMENT_COOKIE_SECRET,
    logLevel: parsed.LOG_LEVEL,
    magicLinkDelivery: parsed.MAGIC_LINK_DELIVERY,
    uploadsDir: parsed.UPLOADS_DIR,
    // Where the drain writes `.eml` files.
    outboxDir: parsed.OUTBOX_DIR,
    // How long an unverified order holds its stock before `make sweep` cancels it.
    staleOrderHours: parsed.STALE_ORDER_HOURS,
    publicUrl,
    trustProxy: parsed.TRUST_PROXY,
    // The addresses Fastify trusts a forwarded header from, or null to fall
    // back to `trustProxy` above. Kept apart from it so an operator can turn
    // on protocol/host forwarding without also trusting client-ip headers.
    trustedProxies: parseTrustedProxies(parsed.TRUSTED_PROXIES),
    secureCookies: isProduction || publicUrl?.startsWith('https:') === true,
    showsDebugMagicLinks: !isProduction && parsed.MAGIC_LINK_DELIVERY === 'flash',
    rateLimits: rateLimitsFrom(parsed),
  }
}

const environmentSchema = environmentVariables.transform(toAppConfig)

/** Everything the app reads its deployment from, inferred from the schema that
 * parses it — a new setting is one edit here rather than three. */
export type AppConfig = z.output<typeof environmentSchema>

/**
 * Parses the environment into the value the whole app reads its deployment
 * from. A production boot that would serve forgeable cookies or print sign-in
 * links into a page throws here instead of starting.
 */
export function loadConfig(environment: NodeJS.ProcessEnv): AppConfig {
  return environmentSchema.parse(environment)
}
