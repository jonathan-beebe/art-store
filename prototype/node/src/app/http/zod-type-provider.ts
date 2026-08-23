import type {
  FastifyPluginCallback,
  FastifySchemaCompiler,
  FastifyTypeProvider,
  RawServerDefault,
} from 'fastify'
import type { z } from 'zod'

/**
 * Reads the zod schema a route declares as the type its handler receives, so
 * `request.params`, `request.query`, and `request.body` arrive parsed. This is
 * the whole of what a type-provider package would add, so none is in the tree.
 */
export interface ZodTypeProvider extends FastifyTypeProvider {
  validator: this['schema'] extends z.ZodType ? z.output<this['schema']> : unknown
}

/** A plugin whose routes declare their input as zod schemas. */
export type ZodRoutes = FastifyPluginCallback<
  Record<never, never>,
  RawServerDefault,
  ZodTypeProvider
>

/**
 * What Fastify runs against every schema a route declares. `{ value }` puts the
 * parsed value back on the request in place of the raw one; `{ error }` carries
 * the `ZodError` to the error handler, which reads which part of the request it
 * came from.
 */
export const zodValidator: FastifySchemaCompiler<z.ZodType> =
  ({ schema }) =>
  (data) => {
    const parsed = schema.safeParse(data)

    return parsed.success ? { value: parsed.data } : { error: parsed.error }
  }
