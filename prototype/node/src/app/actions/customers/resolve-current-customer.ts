import type { Selectable } from 'kysely'
import type { CustomerTable } from '../../db/schema.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'
import { resolveCustomerFromCookie } from './resolve-customer-from-cookie.ts'

/**
 * The customer behind a storefront request, creating an anonymous one when the
 * cookie names nobody. Only the storefront runs this: an auth route reads the
 * cookie through `resolveCustomerFromCookie` so a seller following a seller
 * link never leaves a customer row behind.
 */
export async function resolveCurrentCustomer(
  context: ActionContext,
  cookieId: number | null,
): Promise<Selectable<CustomerTable>> {
  return runInTransaction(context, async (transacted) => {
    const remembered = await resolveCustomerFromCookie(transacted, cookieId)

    return remembered ?? (await createAnonymousCustomer(transacted))
  })
}
