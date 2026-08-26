/**
 * How a log line is shaped on the way out. Every line the app writes — request,
 * action, or CLI run — is one JSON object with the fields
 * `docs/alignment.md` §2.1 fixes, in every environment, so the three prototypes
 * can be read side by side.
 */
import { randomUUID } from 'node:crypto'
import { LogController, type FastifyServerOptions } from 'fastify'
import pino from 'pino'
import type { AppConfig } from './config.ts'
import { isAcceptableRequestId } from './core/logging/request-id.ts'
import { logStoreStream, openLogStore, type LogStore } from './log-store.ts'

const REQUEST_ID_HEADER = 'x-request-id'

/** A stack trace is a development aid, so it is dropped everywhere else. */
const STACK_PATH = 'error.stack'

/** `logDatabaseFile` is optional so a caller wiring only a level and an
 * environment mirrors nothing; the full `AppConfig` always carries it. */
type LoggingConfig = Pick<AppConfig, 'logLevel' | 'environment'> &
  Partial<Pick<AppConfig, 'logDatabaseFile'>>

/** One store per file per process, created the first time a logger defaults
 * to it, so the server and any CLI run in the same process share one handle
 * and one batch writer. The store rides along so the admin reader can wrap
 * the same handle. */
const defaultDestinations = new Map<string, { store: LogStore; stream: pino.DestinationStream }>()

/** The log-store destination for the configured file, or undefined — `off`,
 * or no file named — to leave pino writing to stdout alone. */
function defaultDestination(
  config: LoggingConfig,
): { store: LogStore; stream: pino.DestinationStream } | undefined {
  const file = config.logDatabaseFile
  if (file === undefined || file === 'off') return undefined

  let destination = defaultDestinations.get(file)
  if (destination === undefined) {
    const store = openLogStore(file)
    destination = { store, stream: logStoreStream(store) }
    defaultDestinations.set(file, destination)
  }

  return destination
}

function defaultStream(config: LoggingConfig): pino.DestinationStream | undefined {
  return defaultDestination(config)?.stream
}

/** The store the default stream writes into, for the admin reader to wrap —
 * the same handle per file per process, so reads and the batch writer
 * serialize. Undefined when the config names no store. */
export function defaultLogStore(config: LoggingConfig): LogStore | undefined {
  return defaultDestination(config)?.store
}

/**
 * `ts` in place of pino's epoch `time`, the level as its own name rather than
 * its number, and the stack removed from `failed` lines outside development.
 */
function payloadOptions(config: LoggingConfig): pino.LoggerOptions {
  return {
    level: config.logLevel,
    timestamp: () => `,"ts":"${new Date().toISOString()}"`,
    formatters: { level: (label) => ({ level: label }) },
    ...(config.environment === 'development' ? {} : { redact: { paths: [STACK_PATH], remove: true } }),
  }
}

/** The caller's own correlation id when it is one, and a fresh one when it is not. */
export function acceptRequestId(header: string | string[] | undefined): string {
  const value = Array.isArray(header) ? header[0] : header

  return value !== undefined && isAcceptableRequestId(value) ? value : randomUUID()
}

type LoggingServerOptions = Pick<
  FastifyServerOptions,
  'logger' | 'genReqId' | 'requestIdHeader' | 'logController'
>

/**
 * The logging half of Fastify's server options. `stream` lets a test capture
 * what was logged; the running app leaves it unset, so every line goes to
 * stdout and — when the config names a log database — into the store.
 */
export function loggingOptions(
  config: LoggingConfig,
  { stream }: { stream?: pino.DestinationStream } = {},
): LoggingServerOptions {
  return {
    logger: { ...payloadOptions(config), stream: stream ?? defaultStream(config) },
    // `requestIdHeader` would take the header as it arrived, whatever it holds.
    // `genReqId` reads it instead, so an id that is not the accepted shape is
    // replaced rather than written into the log and echoed back.
    genReqId: (rawRequest) => acceptRequestId(rawRequest.headers[REQUEST_ID_HEADER]),
    requestIdHeader: false,
    // Fastify's own request lines carry no `event` or `phase`, so they are off
    // and `plugins/request-log.ts` writes the `http.request` story instead.
    logController: new LogController({
      disableRequestLogging: true,
      requestIdLogLabel: 'request_id',
    }),
  }
}

/**
 * A pino instance for a CLI entrypoint, in the payload the server writes.
 * Nobody asked for a CLI run, so every line it writes is the system's.
 * `stream` lets a test capture what was logged; unset, lines go to stdout
 * and — when the config names a log database — into the store.
 */
export function createCliLogger(
  config: LoggingConfig,
  { stream }: { stream?: pino.DestinationStream } = {},
): pino.Logger {
  const options = payloadOptions(config)
  const destination = stream ?? defaultStream(config)
  const logger = destination === undefined ? pino(options) : pino(options, destination)

  return logger.child({ actor_type: 'system' })
}
