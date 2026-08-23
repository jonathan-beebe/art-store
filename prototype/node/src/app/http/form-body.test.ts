import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { FastifyRequest } from 'fastify'
import { formBody } from './form-body.ts'

function requestWithBody(body: unknown): Pick<FastifyRequest, 'body'> {
  return { body }
}

test('an object body passes through unchanged', () => {
  assert.deepEqual(formBody(requestWithBody({ reason: 'Chargeback fraud.' })), { reason: 'Chargeback fraud.' })
})

test('an undefined body reads as an empty form', () => {
  assert.deepEqual(formBody(requestWithBody(undefined)), {})
})
