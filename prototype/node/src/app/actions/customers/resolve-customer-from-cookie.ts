import type { Selectable } from 'kysely'
import type { CustomerId } from '../../core/ids/entity-ids.ts'
import type { CustomerTable } from '../../db/schema.ts'
import type { ActionContext } from '../action-context.ts'

/**
 * The customer a parsed identity cookie points at, or null when the cookie
 * named nobody or names a row that is gone.
 *
 * A merge target is always a verified customer and a verified customer is never
 * merged away, so following one merge is enough to land on the current row.
 */
export async function resolveCustomerFromCookie(
  { db }: Pick<ActionContext, 'db'>,
  cookieId: CustomerId | null,
): Promise<Selectable<CustomerTable> | null> {
  if (cookieId === null) return null

  const merge = await db
    .selectFrom('customerMerges')
    .select('customerId')
    .where('anonymousCustomerId', '=', cookieId)
    .executeTakeFirst()

  const customer = await db
    .selectFrom('customers')
    .selectAll()
    .where('id', '=', merge?.customerId ?? cookieId)
    .executeTakeFirst()

  return customer ?? null
}
