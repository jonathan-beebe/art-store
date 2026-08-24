import { sql } from 'kysely'
import type { ActionContext } from '../action-context.ts'
import { decideRateLimit, windowStart, type RateLimitDecision } from '../../core/rate-limit/rate-limit-window.ts'
import type { RateLimitName } from '../../core/rate-limit/rate-limit-name.ts'
import type { RateLimit } from '../../core/rate-limit/rate-limit-value.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { newId } from '../../ids.ts'

export type RateLimitCheck = {
  name: RateLimitName
  /** Whatever the limit is keyed by — an email address, a client ip, or an
   * actor id. Never logged raw; a caller redacts it before it reaches a line. */
  key: string
}

/**
 * Increments the fixed-window counter for one `(name, key)` and decides
 * whether the request that just incremented it trips the limit. The insert-
 * or-increment is one statement — an upsert with `count + 1` in its
 * `doUpdateSet` — so two concurrent requests racing the same window never
 * interleave a read and a write. `off` never touches the table.
 */
export async function checkRateLimit(
  { db, clock }: Pick<ActionContext, 'db' | 'clock'>,
  limit: RateLimit,
  check: RateLimitCheck,
): Promise<RateLimitDecision> {
  if (limit === 'off') return { tripped: false }

  const now = clock.now()
  const start = windowStart(now, limit.windowSeconds)

  const row = await db
    .insertInto('rateLimitWindows')
    .values({
      id: newId('rlw', now),
      name: check.name,
      key: check.key,
      windowStart: toTimestamp(start),
      count: 1,
    })
    .onConflict((conflict) =>
      conflict
        .columns(['name', 'key', 'windowStart'])
        .doUpdateSet({ count: sql<number>`rate_limit_windows.count + 1` }),
    )
    .returning('count')
    .executeTakeFirstOrThrow()

  return decideRateLimit(row.count, limit.count, start, limit.windowSeconds, now)
}
