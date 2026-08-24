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

const REQUEST_ID_HEADER = 'x-request-id'

/** A stack trace is a development aid, so it is dropped everywhere else. */
const STACK_PATH = 'error.stack'

type LoggingConfig = Pick<AppConfig, 'logLevel' | 'environment'>

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
 * what was logged; the running app leaves it unset, so pino writes to stdout.
 */
export function loggingOptions(
  config: LoggingConfig,
  { stream }: { stream?: pino.DestinationStream } = {},
): LoggingServerOptions {
  return {
    logger: { ...payloadOptions(config), stream },
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
 * `stream` lets a test capture what was logged.
 */
export function createCliLogger(
  config: LoggingConfig,
  { stream }: { stream?: pino.DestinationStream } = {},
): pino.Logger {
  const options = payloadOptions(config)
  const logger = stream === undefined ? pino(options) : pino(options, stream)

  return logger.child({ actor_type: 'system' })
}
