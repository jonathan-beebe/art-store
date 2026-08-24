import { test } from 'node:test'
import assert from 'node:assert/strict'
import { newId } from './ids.ts'
import { encodeUlid, parsePrefixedId, ULID_RANDOMNESS_BYTES } from './core/ids/prefixed-id.ts'

const AT = new Date('2026-08-20T09:00:00.000Z')

test('a minted id is the prefix and a ULID, thirty characters long', () => {
  const id = newId('ord', AT)

  assert.equal(id.length, 30)
  assert.deepEqual(parsePrefixedId('ord', id), { outcome: 'id', id })
})

test('the ULID carries the millisecond the clock reported, not the system time', () => {
  const timestamp = encodeUlid(AT.getTime(), new Uint8Array(ULID_RANDOMNESS_BYTES)).slice(0, 10)

  assert.equal(newId('ord', AT).slice(4, 14), timestamp)
})

test('two ids minted in the same millisecond differ and sort in mint order', () => {
  const ids = [newId('ord', AT), newId('ord', AT), newId('ord', AT)]

  assert.equal(new Set(ids).size, 3)
  assert.deepEqual([...ids].sort(), ids)
})

test('ids minted at increasing milliseconds sort in creation order', () => {
  const ids = [0, 1, 2, 60_000].map((offset) => newId('lst', new Date(AT.getTime() + offset)))

  assert.deepEqual([...ids].sort(), ids)
})

test('each prefix mints ids only its own table answers for', () => {
  const listingId = newId('lst', AT)

  assert.equal(parsePrefixedId('lst', listingId).outcome, 'id')
  assert.deepEqual(parsePrefixedId('ord', listingId), {
    outcome: 'refused',
    reason: 'wrong_prefix',
  })
})
