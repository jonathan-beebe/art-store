import { test } from 'node:test'
import assert from 'node:assert/strict'
import { acceptRequestId, createCliLogger, defaultLogStore, loggingOptions } from './logging.ts'
import { captureLogLines } from './test/log-lines.ts'

test('acceptRequestId returns a caller-supplied id that matches the accepted shape', () => {
  assert.equal(acceptRequestId('req-123_ABC'), 'req-123_ABC')
})

test('acceptRequestId takes the first value of a repeated header', () => {
  assert.equal(acceptRequestId(['req-1', 'req-2']), 'req-1')
})

test('acceptRequestId mints its own for an id carrying anything but the accepted characters', () => {
  const minted = acceptRequestId('req 1; drop table')

  assert.notEqual(minted, 'req 1; drop table')
  assert.match(minted, /^[A-Za-z0-9_-]{1,64}$/)
})

test('acceptRequestId mints its own for an absent, empty, or over-long id', () => {
  assert.match(acceptRequestId(undefined), /^[A-Za-z0-9_-]{1,64}$/)
  assert.match(acceptRequestId(''), /^[A-Za-z0-9_-]{1,64}$/)
  assert.match(acceptRequestId('x'.repeat(65)), /^[A-Za-z0-9_-]{1,64}$/)
})

test('acceptRequestId mints a different id each time', () => {
  assert.notEqual(acceptRequestId(undefined), acceptRequestId(undefined))
})

test('loggingOptions carries the level, the request-id wiring, and no framework request lines', () => {
  const options = loggingOptions({ logLevel: 'warn', environment: 'test' })

  assert.equal(options.requestIdHeader, false)
  assert.equal(options.logController?.requestIdLogLabel, 'request_id')
  assert.equal(options.logController?.disableRequestLogging, true)
  assert.equal(typeof options.genReqId, 'function')
  const logger = options.logger as { level?: string }
  assert.equal(logger.level, 'warn')
})

test("loggingOptions' genReqId reads x-request-id and refuses one that is not the accepted shape", () => {
  const options = loggingOptions({ logLevel: 'silent', environment: 'test' })
  const genReqId = options.genReqId as (req: {
    headers: Record<string, string | string[] | undefined>
  }) => string

  assert.equal(genReqId({ headers: { 'x-request-id': 'incoming-id' } }), 'incoming-id')
  assert.notEqual(genReqId({ headers: { 'x-request-id': 'has spaces' } }), 'has spaces')
  assert.equal(genReqId({ headers: {} }).length > 0, true)
})

test('a CLI line carries ts, the level by name, and the system actor', () => {
  const stream = captureLogLines()
  const log = createCliLogger({ logLevel: 'info', environment: 'test' }, { stream })

  log.info({ event: 'seed.run', phase: 'did' }, 'seeded')

  const line = stream.lines()[0]
  assert.equal(line?.level, 'info')
  assert.equal(line?.actor_type, 'system')
  assert.equal(line?.event, 'seed.run')
  assert.equal(line?.msg, 'seeded')
  assert.match(String(line?.ts), /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/)
  assert.equal(line?.time, undefined)
})

test('a failed line keeps its stack in development and drops it everywhere else', () => {
  const inDevelopment = captureLogLines()
  createCliLogger({ logLevel: 'info', environment: 'development' }, { stream: inDevelopment }).error(
    { event: 'seed.run', phase: 'failed', error: { type: 'Error', message: 'no', stack: 'at x' } },
    'the seed run failed',
  )

  const inProduction = captureLogLines()
  createCliLogger({ logLevel: 'info', environment: 'production' }, { stream: inProduction }).error(
    { event: 'seed.run', phase: 'failed', error: { type: 'Error', message: 'no', stack: 'at x' } },
    'the seed run failed',
  )

  assert.deepEqual(inDevelopment.lines()[0]?.error, {
    type: 'Error',
    message: 'no',
    stack: 'at x',
  })
  assert.deepEqual(inProduction.lines()[0]?.error, { type: 'Error', message: 'no' })
})

test('createCliLogger writes to stdout when no stream is given', () => {
  const log = createCliLogger({ logLevel: 'silent', environment: 'test' })

  assert.equal(typeof log.info, 'function')
})

test('loggingOptions defaults its stream to one shared log store per file', () => {
  const config = { logLevel: 'silent', environment: 'test', logDatabaseFile: ':memory:' } as const

  const first = loggingOptions(config).logger as { stream?: unknown }
  const second = loggingOptions(config).logger as { stream?: unknown }

  assert.notEqual(first.stream, undefined)
  assert.equal(first.stream, second.stream)
})

test('an injected stream wins over the configured store', () => {
  const stream = captureLogLines()

  const options = loggingOptions(
    { logLevel: 'silent', environment: 'test', logDatabaseFile: ':memory:' },
    { stream },
  )

  assert.equal((options.logger as { stream?: unknown }).stream, stream)
})

test('logDatabaseFile off leaves pino writing to stdout alone', () => {
  const options = loggingOptions({ logLevel: 'silent', environment: 'test', logDatabaseFile: 'off' })

  assert.equal((options.logger as { stream?: unknown }).stream, undefined)
})

test('a CLI logger with an injected stream keeps exactly that stream', () => {
  const stream = captureLogLines()
  const log = createCliLogger(
    { logLevel: 'info', environment: 'test', logDatabaseFile: ':memory:' },
    { stream },
  )

  log.info({ event: 'seed.run', phase: 'did' }, 'seeded')

  assert.equal(stream.lines().length, 1)
})

test('defaultLogStore hands back the store behind the default stream', () => {
  const config = { logLevel: 'silent', environment: 'test', logDatabaseFile: ':memory:' } as const

  loggingOptions(config)
  const store = defaultLogStore(config)

  assert.notEqual(store, undefined)
  assert.notEqual(store?.database, null)
  assert.equal(defaultLogStore(config), store)
})

test('defaultLogStore is undefined when the config names no store', () => {
  assert.equal(defaultLogStore({ logLevel: 'silent', environment: 'test', logDatabaseFile: 'off' }), undefined)
  assert.equal(defaultLogStore({ logLevel: 'silent', environment: 'test' }), undefined)
})
