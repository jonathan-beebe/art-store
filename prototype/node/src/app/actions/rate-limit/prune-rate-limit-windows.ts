import type { ActionContext } from '../action-context.ts'
import { expiredWindowCutoff } from '../../core/rate-limit/expired-window-cutoff.ts'
import type { RateLimit } from '../../core/rate-limit/rate-limit-value.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type PruneRateLimitWindowsInput = {
  limits: readonly RateLimit[]
  asOf: Date
}

/**
 * Deletes every `rateLimitWindows` row that no configured limit could still
 * read, per `expiredWindowCutoff`. `docs/alignment.md` §2.3 has no event for
 * this write, so — like the cutoff itself is conservative rather than exact —
 * this stays silent rather than inventing one.
 */
export async function pruneRateLimitWindows(
  context: Pick<ActionContext, 'db'>,
  input: PruneRateLimitWindowsInput,
): Promise<number> {
  const cutoff = expiredWindowCutoff(input.asOf, input.limits)
  if (cutoff === null) return 0

  const result = await context.db
    .deleteFrom('rateLimitWindows')
    .where('windowStart', '<', toTimestamp(cutoff))
    .executeTakeFirst()

  return Number(result.numDeletedRows)
}
