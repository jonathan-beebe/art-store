import type { Selectable } from 'kysely'
import type { CustomerTable } from '../../db/schema.ts'
import type { CustomerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'
import { resolveCustomerFromCookie } from './resolve-customer-from-cookie.ts'

/**
 * The customer behind a storefront request, creating an anonymous one when the
 * cookie names nobody. Only the storefront runs this: an auth route reads the
 * cookie through `resolveCustomerFromCookie` so a seller following a seller
 * link never leaves a customer row behind.
 *
 * A remembered customer is a plain read against `context.db`, so it never
 * waits on SQLite's write lock. Only a first visit, which has to create the
 * row, opens a transaction.
 */
export async function resolveCurrentCustomer(
  context: ActionContext,
  cookieId: CustomerId | null,
): Promise<Selectable<CustomerTable>> {
  const remembered = await resolveCustomerFromCookie(context, cookieId)
  if (remembered !== null) return remembered

  return runInTransaction(context, createAnonymousCustomer)
}
