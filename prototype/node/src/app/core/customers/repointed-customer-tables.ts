export type RepointedTable = { table: string; column: string }

/**
 * Tables whose rows move wholesale to the verified customer on a merge.
 * Carts and favorites are absent on purpose — they are folded (summed /
 * de-duplicated) rather than re-pointed, so a verified customer never ends
 * up with two carts.
 */
export const REPOINTED_CUSTOMER_TABLES: readonly RepointedTable[] = [
  { table: 'orders', column: 'customer_id' },
  { table: 'listing_events', column: 'customer_id' },
  { table: 'notifications', column: 'customer_id' },
  { table: 'conversations', column: 'customer_id' },
]
