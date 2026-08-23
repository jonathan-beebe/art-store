import { test } from 'node:test'
import assert from 'node:assert/strict'
import { z } from 'zod'
import { idParams, idValue, optionalFilter, slugParams, submittedForm } from './request-schema.ts'

const sellerFilter = z.object({ seller: optionalFilter(idValue) })
const typeFilter = z.object({ type: optionalFilter(z.enum(['hold', 'release'])) })

test('an id segment reads as the number it names', () => {
  assert.deepEqual(idParams.parse({ id: '42' }), { id: 42 })
  assert.deepEqual(slugParams.parse({ slug: 'harbour-at-dusk' }), { slug: 'harbour-at-dusk' })
})

test('a segment that is not a positive integer is refused', () => {
  assert.equal(idParams.safeParse({ id: 'abc' }).success, false)
  assert.equal(idParams.safeParse({ id: '0' }).success, false)
  assert.equal(idParams.safeParse({ id: '-3' }).success, false)
  assert.equal(idParams.safeParse({ id: '1.5' }).success, false)
})

test('a filter left off the query string is absent', () => {
  assert.equal(sellerFilter.parse({}).seller, undefined)
})

test('a filter submitted empty by the "all" option is absent, not refused', () => {
  assert.equal(sellerFilter.parse({ seller: '' }).seller, undefined)
  assert.equal(typeFilter.parse({ type: '' }).type, undefined)
})

test('a filter carrying a value the schema accepts keeps it', () => {
  assert.deepEqual(sellerFilter.parse({ seller: '7' }), { seller: 7 })
  assert.deepEqual(typeFilter.parse({ type: 'hold' }), { type: 'hold' })
})

test('a filter carrying something the schema refuses is refused', () => {
  assert.equal(sellerFilter.safeParse({ seller: 'nobody' }).success, false)
  assert.equal(typeFilter.safeParse({ type: 'nonsense' }).success, false)
})

test('a request that carried no body reads as an empty form', () => {
  const form = submittedForm({ carrier: z.string().optional() })

  assert.equal(form.parse(null).carrier, undefined)
  assert.equal(form.parse(undefined).carrier, undefined)
})

test('a submitted form keeps the fields it carries', () => {
  const form = submittedForm({ carrier: z.string().optional() })

  assert.deepEqual(form.parse({ carrier: 'Royal Mail' }), { carrier: 'Royal Mail' })
})

test('a submitted form still refuses a field it will not accept', () => {
  const form = submittedForm({ kind: z.enum(['temporary', 'permanent']) })

  assert.equal(form.safeParse({}).success, false)
  assert.equal(form.safeParse({ kind: 'forever' }).success, false)
})
