import { z } from 'zod'

const idParams = z.object({ id: z.coerce.number().int().positive() })

/** A route's `:id` segment as the numeric primary key it names, or null for
 * anything that is not one — the caller answers 404 rather than 500. */
export function parseIdParam(params: unknown): number | null {
  const parsed = idParams.safeParse(params)

  return parsed.success ? parsed.data.id : null
}
