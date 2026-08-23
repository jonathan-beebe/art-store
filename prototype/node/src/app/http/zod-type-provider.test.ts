import { test } from 'node:test'
import assert from 'node:assert/strict'
import { z, ZodError } from 'zod'
import { zodValidator } from './zod-type-provider.ts'

const schema = z.object({ id: z.coerce.number().int().positive() })

const validate = zodValidator({ schema, method: 'GET', url: '/thing/:id' })

test('an accepted input comes back as the parsed value Fastify puts on the request', () => {
  assert.deepEqual(validate({ id: '7' }), { value: { id: 7 } })
})

test('a refused input comes back as the ZodError, which the error handler reads', () => {
  const result = validate({ id: 'abc' })

  assert.ok(result !== null && typeof result === 'object' && 'error' in result)
  assert.ok(result.error instanceof ZodError)
})
