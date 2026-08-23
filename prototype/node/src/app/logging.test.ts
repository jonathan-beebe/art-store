import { test } from 'node:test'
import assert from 'node:assert/strict'
import { loggingOptions, readOrGenerateRequestId, redactedCookies } from './logging.ts'

test('redactedCookies redacts the identity and flash cookies and keeps the rest', () => {
  const header = 'seller_id=s.sig; customer_id=c.sig=; admin_id=a.sig; flash=%7B%22notice%22; theme=dark'

  assert.deepEqual(redactedCookies(header), {
    seller_id: '[redacted]',
    customer_id: '[redacted]',
    admin_id: '[redacted]',
    flash: '[redacted]',
    theme: 'dark',
  })
})

test('redactedCookies keeps a value containing an = sign intact when not redacted', () => {
  assert.deepEqual(redactedCookies('theme=dark=default'), { theme: 'dark=default' })
})

test('redactedCookies returns undefined for a request with no Cookie header', () => {
  assert.equal(redactedCookies(undefined), undefined)
})

test('readOrGenerateRequestId returns the caller-supplied header value', () => {
  assert.equal(readOrGenerateRequestId('req-123'), 'req-123')
})

test('readOrGenerateRequestId takes the first value of a repeated header', () => {
  assert.equal(readOrGenerateRequestId(['req-1', 'req-2']), 'req-1')
})

test('readOrGenerateRequestId generates one when the header is absent or empty', () => {
  const generated = readOrGenerateRequestId(undefined)
  const generatedFromEmpty = readOrGenerateRequestId('')

  assert.equal(generated.length > 0, true)
  assert.equal(generatedFromEmpty.length > 0, true)
  assert.notEqual(generated, generatedFromEmpty)
})

test('loggingOptions carries the configured level and the request id wiring', () => {
  const options = loggingOptions({ logLevel: 'warn' })

  assert.equal(options.requestIdHeader, 'x-request-id')
  assert.equal(typeof options.genReqId, 'function')
  assert.equal(typeof options.logger === 'object' && options.logger !== null, true)
  const logger = options.logger as { level?: string }
  assert.equal(logger.level, 'warn')
})

test("loggingOptions' genReqId reads x-request-id and falls back to a generated id", () => {
  const options = loggingOptions({ logLevel: 'silent' })
  const genReqId = options.genReqId as (req: { headers: Record<string, string | string[] | undefined> }) => string

  assert.equal(genReqId({ headers: { 'x-request-id': 'incoming-id' } }), 'incoming-id')
  assert.equal(genReqId({ headers: {} }).length > 0, true)
})
