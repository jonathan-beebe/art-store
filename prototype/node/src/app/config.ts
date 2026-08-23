import path from 'node:path'
import { z } from 'zod'
import {
  MAGIC_LINK_DELIVERIES,
  type MagicLinkDeliveryName,
} from './delivery/magic-link-delivery.ts'

export type AppConfig = {
  host: string
  port: number
  databaseFile: string
  cookieSecret: string
  logLevel: LogLevel
  magicLinkDelivery: MagicLinkDeliveryName
  uploadsDir: string
}

const LOG_LEVELS = ['fatal', 'error', 'warn', 'info', 'debug', 'trace', 'silent'] as const

type LogLevel = (typeof LOG_LEVELS)[number]

// Same convention app.ts uses for PUBLIC_ROOT: the public directory sits
// beside app/ at the project root.
const PUBLIC_ROOT = path.join(import.meta.dirname, '..', 'public')
const DEFAULT_UPLOADS_DIR = path.join(PUBLIC_ROOT, 'uploads')

const environmentSchema = z.object({
  HOST: z.string().min(1).default('0.0.0.0'),
  PORT: z.coerce.number().int().positive().default(4000),
  DATABASE_FILE: z.string().min(1).default('storage/development.sqlite3'),
  // Signs the flash and identity cookies. A deployment overrides it; the
  // prototype ships a default so a clone runs with no configuration.
  COOKIE_SECRET: z.string().min(16).default('art-store-prototype-cookie-secret'),
  LOG_LEVEL: z.enum(LOG_LEVELS).default('info'),
  MAGIC_LINK_DELIVERY: z.enum(MAGIC_LINK_DELIVERIES).default('flash'),
  UPLOADS_DIR: z.string().min(1).default(DEFAULT_UPLOADS_DIR),
})

export function loadConfig(environment: NodeJS.ProcessEnv): AppConfig {
  const parsed = environmentSchema.parse(environment)

  return {
    host: parsed.HOST,
    port: parsed.PORT,
    databaseFile: parsed.DATABASE_FILE,
    cookieSecret: parsed.COOKIE_SECRET,
    logLevel: parsed.LOG_LEVEL,
    magicLinkDelivery: parsed.MAGIC_LINK_DELIVERY,
    uploadsDir: parsed.UPLOADS_DIR,
  }
}
