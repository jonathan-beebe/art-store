import type { Database } from '../../db/schema.ts'

/**
 * Tables whose rows move wholesale to the verified customer on a merge, keyed
 * by the schema so a renamed table stops compiling here. Carts and favorites
 * are absent on purpose — they are folded (summed / de-duplicated) rather than
 * re-pointed, so a verified customer never ends up with two carts.
 */
export const REPOINTED_CUSTOMER_TABLES = [
  'orders',
  'listingEvents',
  'notifications',
  'conversations',
] as const satisfies readonly (keyof Database)[]

export type RepointedCustomerTable = (typeof REPOINTED_CUSTOMER_TABLES)[number]
