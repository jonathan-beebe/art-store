/**
 * The source of "now" for everything above the core. Core functions take a
 * `Date` parameter instead, so only actions and routes ever hold a clock.
 */
export type Clock = {
  now(): Date
}

export const systemClock: Clock = {
  now: () => new Date(),
}

export function fixedClock(instant: Date): Clock {
  const frozen = instant.getTime()
  return { now: () => new Date(frozen) }
}
