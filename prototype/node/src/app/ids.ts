/**
 * Where new ids come from. Randomness is the one ingredient the core cannot
 * hold, so it is drawn here and handed to `encodeUlid` with the millisecond
 * the caller's clock reports — an action mints from the clock it already
 * receives rather than reaching for the system time.
 *
 * The draw is fresh for every new millisecond and stepped by one for each id
 * after the first inside it, which is the ULID spec's monotonic mode. Without
 * the step a fixed clock mints ids in no order at all, and a page rendering
 * rows in the order they were written would show them shuffled.
 */
import { randomBytes } from 'node:crypto'
import type { IdPrefix } from './core/ids/entity-ids.ts'
import {
  encodeUlid,
  nextRandomness,
  ULID_RANDOMNESS_BYTES,
  type PrefixedId,
} from './core/ids/prefixed-id.ts'

let lastMilliseconds = -1
let lastRandomness: Uint8Array = new Uint8Array(ULID_RANDOMNESS_BYTES)

/** A new id for a row of the table `prefix` names, created at `at`. */
export function newId<Prefix extends IdPrefix>(prefix: Prefix, at: Date): PrefixedId<Prefix> {
  const milliseconds = at.getTime()

  lastRandomness =
    milliseconds === lastMilliseconds
      ? nextRandomness(lastRandomness)
      : randomBytes(ULID_RANDOMNESS_BYTES)
  lastMilliseconds = milliseconds

  return `${prefix}_${encodeUlid(milliseconds, lastRandomness)}`
}
