const SECONDS_PER_MINUTE = 60

/**
 * What every site's 429 page and every re-rendered form say, word for word —
 * PHP and Rails match it. Minutes are always plural, `retryAfterSeconds`
 * rounded up so the number never undersells how long the wait actually is.
 */
export function tooManyRequestsMessage(retryAfterSeconds: number): string {
  const minutes = Math.max(1, Math.ceil(retryAfterSeconds / SECONDS_PER_MINUTE))

  return `Too many requests — try again in ${minutes} minutes.`
}
