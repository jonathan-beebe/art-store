/**
 * Every timestamp column stores an ISO-8601 instant in UTC. Text sorts and
 * compares in chronological order in that format, so an expiry check is a
 * plain `<` in SQL and needs no date functions.
 */
export type Timestamp = string

export function toTimestamp(instant: Date): Timestamp {
  return instant.toISOString()
}

export function fromTimestamp(value: Timestamp): Date {
  return new Date(value)
}

export function fromNullableTimestamp(value: Timestamp | null): Date | null {
  return value === null ? null : fromTimestamp(value)
}
