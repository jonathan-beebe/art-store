import type { Selectable } from 'kysely'
import type { Clock } from '../../clock.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { CustomerTable } from '../../db/schema.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'
import { resolveCustomerFromCookie } from './resolve-customer-from-cookie.ts'

/**
 * The customer behind a storefront request, creating an anonymous one when the
 * cookie names nobody. Only the storefront runs this: an auth route reads the
 * cookie through `resolveCustomerFromCookie` so a seller following a seller
 * link never leaves a customer row behind.
 */
export async function resolveCurrentCustomer(
  { db, clock }: { db: AppDatabase; clock: Clock },
  cookieValue: string | null | undefined,
): Promise<Selectable<CustomerTable>> {
  const remembered = await resolveCustomerFromCookie({ db }, cookieValue)

  return remembered ?? (await createAnonymousCustomer({ db, clock }))
}
