import type { IdPrefix } from '../core/ids/entity-ids.ts'
import type { PrefixedId } from '../core/ids/prefixed-id.ts'

/**
 * A hand-written id of the right shape, for a test that names its rows rather
 * than minting them: `fixtureId('ord', 1)` is `ord_00000000000000000000000001`,
 * a valid ULID whose digits read as the number a reader can hold in their head.
 * Sequence order is id order, so a list ordered by creation reads the same way.
 */
export function fixtureId<Prefix extends IdPrefix>(
  prefix: Prefix,
  sequence: number,
): PrefixedId<Prefix> {
  return `${prefix}_${sequence.toString().padStart(26, '0')}`
}
