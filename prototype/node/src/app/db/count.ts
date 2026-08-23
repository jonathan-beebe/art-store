/**
 * An aggregate as the driver hands it back. `count` and `sum` arrive as a
 * number, a string, or a bigint depending on the driver and the width of the
 * total, and a `sum` over no rows arrives as null — a report of traffic nobody
 * made reads as zero rather than as nothing.
 */
export function toCount(value: number | string | bigint | null | undefined): number {
  if (value === null || value === undefined) return 0

  const count = Number(value)
  if (!Number.isFinite(count)) {
    throw new TypeError(`toCount: not a count: ${String(value)}`)
  }

  return count
}
