import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
  encodeUlid,
  isPrefixedId,
  parsePrefixedId,
  nextRandomness,
  ULID_RANDOMNESS_BYTES,
  type PrefixedId,
} from './prefixed-id.ts'

const ZERO_RANDOMNESS = new Uint8Array(ULID_RANDOMNESS_BYTES)
const MAX_RANDOMNESS = new Uint8Array(ULID_RANDOMNESS_BYTES).fill(0xff)

/** The example `docs/alignment.md` §1 renders. */
const EXAMPLE_ID = 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE'

test('a ULID is 26 symbols', () => {
  assert.equal(encodeUlid(0, ZERO_RANDOMNESS).length, 26)
  assert.equal(encodeUlid(Date.parse('2026-08-23T18:00:00.000Z'), MAX_RANDOMNESS).length, 26)
})

test('a ULID uses only the Crockford alphabet, which drops I, L, O, and U', () => {
  const alphabet = new Set<string>()

  for (let byte = 0; byte < 256; byte += 1) {
    const randomness = new Uint8Array(ULID_RANDOMNESS_BYTES).fill(byte)
    for (const symbol of encodeUlid(byte * 1_000_000, randomness)) alphabet.add(symbol)
  }

  assert.deepEqual([...alphabet].sort().join(''), '0123456789ABCDEFGHJKMNPQRSTVWXYZ')
})

test('the first ten symbols are the millisecond, most significant first', () => {
  assert.equal(encodeUlid(0, ZERO_RANDOMNESS).slice(0, 10), '0000000000')
  assert.equal(encodeUlid(1, ZERO_RANDOMNESS).slice(0, 10), '0000000001')
  assert.equal(encodeUlid(32, ZERO_RANDOMNESS).slice(0, 10), '0000000010')
  // Ten five-bit symbols hold fifty bits, so the 48-bit ceiling leaves the
  // leading symbol at 7 and the ULID spec's `7ZZZZZZZZZ` is the last instant.
  assert.equal(encodeUlid(2 ** 48 - 1, ZERO_RANDOMNESS).slice(0, 10), '7ZZZZZZZZZ')
})

test('the last sixteen symbols are the eighty random bits', () => {
  assert.equal(encodeUlid(0, ZERO_RANDOMNESS).slice(10), '0000000000000000')
  assert.equal(encodeUlid(0, MAX_RANDOMNESS).slice(10), 'ZZZZZZZZZZZZZZZZ')
})

test('two ULIDs minted in the same millisecond differ by their randomness', () => {
  const milliseconds = Date.parse('2026-08-23T18:00:00.000Z')
  const first = encodeUlid(milliseconds, new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]))
  const second = encodeUlid(milliseconds, new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 11]))

  assert.notEqual(first, second)
  assert.equal(first.slice(0, 10), second.slice(0, 10))
})

test('ULIDs minted at increasing milliseconds sort lexicographically', () => {
  const instants = [0, 1, 32, 1_000, Date.parse('2026-08-23T18:00:00.000Z'), 2 ** 48 - 1]
  const ulids = instants.map((milliseconds) => encodeUlid(milliseconds, MAX_RANDOMNESS))

  assert.deepEqual([...ulids].sort(), ulids)
})

test('a randomness draw of the wrong width is refused', () => {
  assert.throws(() => encodeUlid(0, new Uint8Array(9)), RangeError)
  assert.throws(() => encodeUlid(0, new Uint8Array(11)), RangeError)
})

test('a millisecond outside the 48 bits a ULID carries is refused', () => {
  assert.throws(() => encodeUlid(-1, ZERO_RANDOMNESS), RangeError)
  assert.throws(() => encodeUlid(2 ** 48, ZERO_RANDOMNESS), RangeError)
  assert.throws(() => encodeUlid(1.5, ZERO_RANDOMNESS), RangeError)
  assert.throws(() => encodeUlid(Number.NaN, ZERO_RANDOMNESS), RangeError)
})

test('the next randomness steps the eighty bits by one', () => {
  assert.deepEqual(
    nextRandomness(new Uint8Array([0, 0, 0, 0, 0, 0, 0, 0, 0, 0])),
    new Uint8Array([0, 0, 0, 0, 0, 0, 0, 0, 0, 1]),
  )
  assert.deepEqual(
    nextRandomness(new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 0xff])),
    new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 10, 0]),
  )
  assert.deepEqual(
    nextRandomness(new Uint8Array([1, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff, 0xff])),
    new Uint8Array([2, 0, 0, 0, 0, 0, 0, 0, 0, 0]),
  )
})

test('the next randomness wraps to zero once every bit is set', () => {
  assert.deepEqual(nextRandomness(MAX_RANDOMNESS), ZERO_RANDOMNESS)
})

test('stepped randomness sorts after the draw it steps', () => {
  const drawn = new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
  const stepped = nextRandomness(drawn)

  assert.ok(encodeUlid(0, drawn) < encodeUlid(0, stepped))
})

test('a prefixed id is the prefix, an underscore, and the ULID', () => {
  const id: PrefixedId<'ord'> = `ord_${encodeUlid(0, ZERO_RANDOMNESS)}`

  assert.equal(id.length, 30)
  assert.deepEqual(parsePrefixedId('ord', id), { outcome: 'id', id })
})

test('the alignment example parses', () => {
  assert.deepEqual(parsePrefixedId('ord', EXAMPLE_ID), { outcome: 'id', id: EXAMPLE_ID })
})

test('an id belonging to another table is refused by prefix', () => {
  assert.deepEqual(parsePrefixedId('lst', EXAMPLE_ID), {
    outcome: 'refused',
    reason: 'wrong_prefix',
  })
})

const MALFORMED_IDS: ReadonlyArray<readonly [string, string]> = [
  ['empty', ''],
  ['a bare integer', '1'],
  ['a bare ULID with no prefix', '01J5X3M9A2K8YB7Q4R6T1V0WZE'],
  ['no underscore', 'ord01J5X3M9A2K8YB7Q4R6T1V0WZE'],
  ['a lowercase ULID body', 'ord_01j5x3m9a2k8yb7q4r6t1v0wze'],
  ['a 25-symbol body', 'ord_01J5X3M9A2K8YB7Q4R6T1V0W'],
  ['a 27-symbol body', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZEE'],
  ['I, outside the Crockford alphabet', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZI'],
  ['L, outside the Crockford alphabet', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZL'],
  ['O, outside the Crockford alphabet', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZO'],
  ['U, outside the Crockford alphabet', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZU'],
  ['a hyphen for the underscore', 'ord-01J5X3M9A2K8YB7Q4R6T1V0WZE'],
  ['a two-letter prefix', 'or_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
  ['an uppercase prefix', 'ORD_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
  ['trailing whitespace', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE '],
  ['a second id appended', 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE_ord_01J5X3M9A2K8YB7Q4R6T1V0WZE'],
]

for (const [shape, value] of MALFORMED_IDS) {
  test(`${shape} is refused as malformed`, () => {
    assert.deepEqual(parsePrefixedId('ord', value), { outcome: 'refused', reason: 'malformed' })
  })
}

test('isPrefixedId narrows a string to the table it belongs to', () => {
  const value: string = EXAMPLE_ID

  assert.equal(isPrefixedId('ord', value), true)
  assert.equal(isPrefixedId('lst', value), false)
  assert.equal(isPrefixedId('ord', '1'), false)
})
