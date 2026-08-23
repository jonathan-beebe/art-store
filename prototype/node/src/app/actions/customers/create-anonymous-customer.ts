import type { Selectable } from 'kysely'
import type { Clock } from '../../clock.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { CustomerTable } from '../../db/schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/**
 * The row a first storefront request gets, so favorites, a cart, and a guest
 * order have somewhere to hang before anyone gives an address.
 */
export async function createAnonymousCustomer({
  db,
  clock,
}: {
  db: AppDatabase
  clock: Clock
}): Promise<Selectable<CustomerTable>> {
  return await db
    .insertInto('customers')
    .values({ email: null, name: null, emailVerifiedAt: null, createdAt: toTimestamp(clock.now()) })
    .returningAll()
    .executeTakeFirstOrThrow()
}
