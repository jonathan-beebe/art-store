import type { Selectable } from 'kysely'
import type { CustomerTable } from '../../db/schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'

/**
 * The row a first storefront request gets, so favorites, a cart, and a guest
 * order have somewhere to hang before anyone gives an address.
 */
export async function createAnonymousCustomer({
  db,
  clock,
}: ActionContext): Promise<Selectable<CustomerTable>> {
  return await db
    .insertInto('customers')
    .values({
      id: newId('cus', clock.now()),
      email: null,
      name: null,
      emailVerifiedAt: null,
      createdAt: toTimestamp(clock.now()),
    })
    .returningAll()
    .executeTakeFirstOrThrow()
}
