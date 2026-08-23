import path from 'node:path'
import { z } from 'zod'
import {
  MAGIC_LINK_DELIVERIES,
  type MagicLinkDeliveryName,
} from './delivery/magic-link-delivery.ts'

export const ENVIRONMENTS = ['development', 'test', 'production'] as const

export type Environment = (typeof ENVIRONMENTS)[number]

export type AppConfig = {
  environment: Environment
  host: string
  port: number
  databaseFile: string
  cookieSecret: string
  logLevel: LogLevel
  magicLinkDelivery: MagicLinkDeliveryName
  uploadsDir: string
  /** Where the drain writes `.eml` files. */
  outboxDir: string
  /** The origin links are built from, or null to build them from the request. */
  publicUrl: string | null
  trustProxy: boolean
  secureCookies: boolean
  showsDebugMagicLinks: boolean
}

const LOG_LEVELS = ['fatal', 'error', 'warn', 'info', 'debug', 'trace', 'silent'] as const

type LogLevel = (typeof LOG_LEVELS)[number]

// Same convention app.ts uses for PUBLIC_ROOT: the public directory sits
// beside app/ at the project root.
const PUBLIC_ROOT = path.join(import.meta.dirname, '..', 'public')
const DEFAULT_UPLOADS_DIR = path.join(PUBLIC_ROOT, 'uploads')

// Signs the flash and identity cookies outside production, so a clone runs
// with no configuration. Production brings its own secret or does not boot.
const DEVELOPMENT_COOKIE_SECRET = 'art-store-prototype-cookie-secret'

const environmentSchema = z.object({
  NODE_ENV: z.enum(ENVIRONMENTS).default('development'),
  HOST: z.string().min(1).default('0.0.0.0'),
  PORT: z.coerce.number().int().positive().default(4000),
  DATABASE_FILE: z.string().min(1).default('storage/development.sqlite3'),
  COOKIE_SECRET: z.string().min(16).optional(),
  LOG_LEVEL: z.enum(LOG_LEVELS).default('info'),
  MAGIC_LINK_DELIVERY: z.enum(MAGIC_LINK_DELIVERIES).default('flash'),
  UPLOADS_DIR: z.string().min(1).default(DEFAULT_UPLOADS_DIR),
  OUTBOX_DIR: z.string().min(1).default('storage/outbox'),
  // Reduced to an origin: every route is served from the root, so a path or
  // query on the way in is noise every link built from it would carry.
  PUBLIC_URL: z
    .url()
    .transform((value) => new URL(value).origin)
    .optional(),
  TRUST_PROXY: z.stringbool().default(false),
})

type ParsedEnvironment = z.infer<typeof environmentSchema>

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

/**
 * Parses the environment into the value the whole app reads its deployment
 * from. A production boot that would serve forgeable cookies or print sign-in
 * links into a page throws here instead of starting.
 */
export function loadConfig(environment: NodeJS.ProcessEnv): AppConfig {
  const parsed = environmentSchema.parse(environment)
  refuseUnsafeProduction(parsed)

  const isProduction = parsed.NODE_ENV === 'production'
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
    outboxDir: parsed.OUTBOX_DIR,
    publicUrl,
    trustProxy: parsed.TRUST_PROXY,
    secureCookies: isProduction || publicUrl?.startsWith('https:') === true,
    showsDebugMagicLinks: !isProduction && parsed.MAGIC_LINK_DELIVERY === 'flash',
  }
}
