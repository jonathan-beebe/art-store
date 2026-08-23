import type { ListingEventType } from './listing-event-type.ts'

// Every page load would otherwise be a row, and a seller's activity numbers
// only need to know that someone looked, not how many times they refreshed.
const ONCE_PER_HOUR: readonly ListingEventType[] = ['view']

/** The window a repeated event collapses into: the UTC hour containing `now`. */
export function viewWindowStart(now: Date): Date {
  return new Date(
    Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), now.getUTCHours()),
  )
}

/** Whether one (listing, customer, hour) holds at most one event of this kind. */
export function isRecordedOncePerHour(eventType: ListingEventType): boolean {
  return ONCE_PER_HOUR.includes(eventType)
}
