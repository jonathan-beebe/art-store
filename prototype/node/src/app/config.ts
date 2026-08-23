import { z } from 'zod'

export type AppConfig = {
  host: string
  port: number
  databaseFile: string
  cookieSecret: string
  logLevel: LogLevel
}

const LOG_LEVELS = ['fatal', 'error', 'warn', 'info', 'debug', 'trace', 'silent'] as const

type LogLevel = (typeof LOG_LEVELS)[number]

const environmentSchema = z.object({
  HOST: z.string().min(1).default('0.0.0.0'),
  PORT: z.coerce.number().int().positive().default(4000),
  DATABASE_FILE: z.string().min(1).default('storage/development.sqlite3'),
  // Signs the flash and identity cookies. A deployment overrides it; the
  // prototype ships a default so a clone runs with no configuration.
  COOKIE_SECRET: z.string().min(16).default('art-store-prototype-cookie-secret'),
  LOG_LEVEL: z.enum(LOG_LEVELS).default('info'),
})

export function loadConfig(environment: NodeJS.ProcessEnv): AppConfig {
  const parsed = environmentSchema.parse(environment)

  return {
    host: parsed.HOST,
    port: parsed.PORT,
    databaseFile: parsed.DATABASE_FILE,
    cookieSecret: parsed.COOKIE_SECRET,
    logLevel: parsed.LOG_LEVEL,
  }
}
