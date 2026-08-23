import type { Selectable } from 'kysely'
import type { AppDatabase } from '../../db/database.ts'
import type { CustomerTable } from '../../db/schema.ts'

/**
 * The customer an identity cookie points at, or null when the cookie is absent,
 * unreadable, or names a row that is gone.
 *
 * A merge target is always a verified customer and a verified customer is never
 * merged away, so following one merge is enough to land on the current row.
 */
export async function resolveCustomerFromCookie(
  { db }: { db: AppDatabase },
  cookieValue: string | null | undefined,
): Promise<Selectable<CustomerTable> | null> {
  const cookieId = customerIdFromCookie(cookieValue)
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

const CUSTOMER_ID = /^[0-9]+$/

function customerIdFromCookie(cookieValue: string | null | undefined): number | null {
  if (typeof cookieValue !== 'string' || !CUSTOMER_ID.test(cookieValue)) return null

  const id = Number(cookieValue)

  return id >= 1 ? id : null
}
