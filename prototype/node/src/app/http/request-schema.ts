import { z } from 'zod'
import type { IdPrefix } from '../core/ids/entity-ids.ts'
import { isPrefixedId, type PrefixedId } from '../core/ids/prefixed-id.ts'

/**
 * The prefixed id of one table, wherever a url segment or a query string names
 * one. The prefix is the table's, so an id belonging to another table is
 * refused here rather than looked up and missed.
 */
export function idValue<Prefix extends IdPrefix>(prefix: Prefix) {
  return z.custom<PrefixedId<Prefix>>(
    (value) => typeof value === 'string' && isPrefixedId(prefix, value),
    { message: `not a ${prefix} id` },
  )
}

/**
 * The `:id` segment a route names its subject by. A segment that is not this
 * table's id fails params validation, which `isRefusedRouteParams` turns into
 * the site's own 404 — the same page an id nobody owns answers with.
 */
export function idParams<Prefix extends IdPrefix>(prefix: Prefix) {
  return z.object({ id: idValue(prefix) })
}

/** The `:slug` segment a storefront route names a listing by. */
export const slugParams = z.object({ slug: z.string() })

export type SlugParams = z.output<typeof slugParams>

/**
 * One optional filter on a query string. A `<select>`'s "all" option and an
 * emptied number input both submit the field with an empty value, which reads
 * as no filter rather than as a value the filter refuses.
 */
export function optionalFilter<Filter extends z.ZodType>(filter: Filter) {
  return z.preprocess((value) => (value === '' ? undefined : value), filter.optional())
}

/**
 * A submitted form. Fastify hands a request that carried no body at all to the
 * schema as null, and every form here answers for a field nobody filled in, so
 * an absent body reads as an empty form.
 */
export function submittedForm<Fields extends z.ZodRawShape>(fields: Fields) {
  return z.preprocess((body) => body ?? {}, z.object(fields))
}
