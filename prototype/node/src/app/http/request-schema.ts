import { z } from 'zod'

/** A positive integer id, wherever a url segment or a query string names one. */
export const idValue = z.coerce.number().int().positive()

/** The `:id` segment a route names its subject by. */
export const idParams = z.object({ id: idValue })

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
