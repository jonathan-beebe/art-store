/** How many rows carry one key — a status, an event type, a ledger entry type. */
export type Tally<Key extends string> = { key: Key; count: number }

/**
 * Every key the domain knows, in the domain's own order, carrying the count
 * that was measured for it. A `group by` answers only for the keys that have
 * rows, and a status nobody has reached is still a status the page shows.
 */
export function tallyOver<Key extends string>(
  keys: readonly Key[],
  counted: readonly Tally<Key>[],
): readonly Tally<Key>[] {
  const counts = new Map(counted.map((tally) => [tally.key, tally.count]))

  return keys.map((key) => ({ key, count: counts.get(key) ?? 0 }))
}

export function tallyTotal<Key extends string>(tallies: readonly Tally<Key>[]): number {
  return tallies.reduce((total, tally) => total + tally.count, 0)
}
