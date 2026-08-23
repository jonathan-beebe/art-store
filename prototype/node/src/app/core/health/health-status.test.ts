import { test } from 'node:test'
import assert from 'node:assert/strict'
import { evaluateHealth, healthStatusCode } from './health-status.ts'

test('ok when the database answers and no migration is pending', () => {
  const status = evaluateHealth({ database: 'ok', migrations: 'current' }, false)

  assert.equal(status, 'ok')
  assert.equal(healthStatusCode(status), 200)
})

test('unavailable when the database check fails', () => {
  const status = evaluateHealth({ database: 'failed', migrations: 'current' }, false)

  assert.equal(status, 'unavailable')
  assert.equal(healthStatusCode(status), 503)
})

test('unavailable when a migration is pending', () => {
  const status = evaluateHealth({ database: 'ok', migrations: 'pending' }, false)

  assert.equal(status, 'unavailable')
  assert.equal(healthStatusCode(status), 503)
})

test('draining wins over passing checks', () => {
  const status = evaluateHealth({ database: 'ok', migrations: 'current' }, true)

  assert.equal(status, 'draining')
  assert.equal(healthStatusCode(status), 503)
})

test('draining wins over failing checks too', () => {
  const status = evaluateHealth({ database: 'failed', migrations: 'pending' }, true)

  assert.equal(status, 'draining')
  assert.equal(healthStatusCode(status), 503)
})
