/**
 * Every row is named by `<prefix>_<ulid>`: a three-letter table prefix, an
 * underscore, then the 26-character Crockford base32 ULID the spec renders.
 * The prefix makes an id self-describing in a URL or a log line, and a route
 * that names its own table's prefix answers 404 for an id from another table.
 *
 * Nothing here draws randomness or reads a clock: `encodeUlid` takes the
 * millisecond and the random bytes as arguments, so a test can call it with
 * literals and get the same string every time.
 */

/** Crockford base32: the ten digits and the letters, less I, L, O, and U. */
const SYMBOLS = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'

const BITS_PER_SYMBOL = 5

/** The ULID's first ten symbols are a 48-bit millisecond timestamp. */
const TIMESTAMP_SYMBOLS = 10

/** Its last sixteen are 80 bits of randomness. */
export const ULID_RANDOMNESS_BYTES = 10

/** 48 bits of milliseconds run out on 10889-08-02. */
const MAX_TIMESTAMP_MS = 2 ** 48 - 1

/** Three lowercase letters, an underscore, and 26 Crockford base32 symbols. */
const PREFIXED_ID_PATTERN = /^([a-z]{3})_([0-9A-HJKMNP-TV-Z]{26})$/

/** An id belonging to the table `Prefix` names. */
export type PrefixedId<Prefix extends string> = `${Prefix}_${string}`

/** Why a string is not the id the caller asked for. */
export type IdRefusal = 'malformed' | 'wrong_prefix'

export type ParsedId<Prefix extends string> =
  | { readonly outcome: 'id'; readonly id: PrefixedId<Prefix> }
  | { readonly outcome: 'refused'; readonly reason: IdRefusal }

/**
 * The ULID for one instant and one draw of randomness. `milliseconds` is a
 * Unix epoch millisecond and `randomness` is exactly
 * `ULID_RANDOMNESS_BYTES` bytes; both are the caller's to supply, and a value
 * outside those bounds is a mistake in the caller rather than a refusal this
 * function reports.
 */
export function encodeUlid(milliseconds: number, randomness: Uint8Array): string {
  if (!Number.isInteger(milliseconds) || milliseconds < 0 || milliseconds > MAX_TIMESTAMP_MS) {
    throw new RangeError(`a ULID timestamp is 0 to ${MAX_TIMESTAMP_MS} milliseconds, not ${milliseconds}`)
  }

  if (randomness.length !== ULID_RANDOMNESS_BYTES) {
    throw new RangeError(`a ULID needs ${ULID_RANDOMNESS_BYTES} random bytes, not ${randomness.length}`)
  }

  return encodeTimestamp(milliseconds) + encodeRandomness(randomness)
}

/** Most significant symbol first, so two ULIDs compare in timestamp order. */
function encodeTimestamp(milliseconds: number): string {
  let remaining = milliseconds
  let symbols = ''

  for (let position = 0; position < TIMESTAMP_SYMBOLS; position += 1) {
    symbols = SYMBOLS.charAt(remaining % SYMBOLS.length) + symbols
    remaining = Math.floor(remaining / SYMBOLS.length)
  }

  return symbols
}

/** Ten bytes are eighty bits, which is sixteen five-bit symbols exactly. */
function encodeRandomness(randomness: Uint8Array): string {
  let pending = 0
  let pendingBits = 0
  let symbols = ''

  for (const byte of randomness) {
    pending = (pending << 8) | byte
    pendingBits += 8

    while (pendingBits >= BITS_PER_SYMBOL) {
      pendingBits -= BITS_PER_SYMBOL
      symbols += SYMBOLS.charAt((pending >> pendingBits) & (SYMBOLS.length - 1))
    }
  }

  return symbols
}

/**
 * The randomness that follows `randomness`, for a second id minted inside the
 * same millisecond: the ULID spec's monotonic step, so ids sort in the order
 * they were minted even where the clock cannot tell them apart. It wraps to
 * zero on overflow, which takes 2^80 ids in one millisecond.
 */
export function nextRandomness(randomness: Uint8Array): Uint8Array {
  const next = Uint8Array.from(randomness)

  for (let index = next.length - 1; index >= 0; index -= 1) {
    const stepped = (next[index] ?? 0) + 1
    next[index] = stepped & 0xff

    if (stepped <= 0xff) return next
  }

  return next
}

/**
 * The id `value` names for the table `prefix` belongs to. Untrusted text — a
 * url segment, a query string, a cookie — reaches the rest of the application
 * only through here.
 */
export function parsePrefixedId<Prefix extends string>(
  prefix: Prefix,
  value: string,
): ParsedId<Prefix> {
  const match = PREFIXED_ID_PATTERN.exec(value)
  if (match === null) return { outcome: 'refused', reason: 'malformed' }

  const [, head = '', ulid = ''] = match
  if (head !== prefix) return { outcome: 'refused', reason: 'wrong_prefix' }

  return { outcome: 'id', id: `${prefix}_${ulid}` }
}

/** Whether `value` is an id of the table `prefix` belongs to. */
export function isPrefixedId<Prefix extends string>(
  prefix: Prefix,
  value: string,
): value is PrefixedId<Prefix> {
  return parsePrefixedId(prefix, value).outcome === 'id'
}
