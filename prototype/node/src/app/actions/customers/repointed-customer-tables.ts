import type { Database } from '../../db/schema.ts'

/**
 * Tables whose rows move to the verified customer on a merge by writing their
 * `customerId` column and nothing else. Carts, favorites, and conversations
 * are absent on purpose — `FOLDED_CUSTOMER_TABLES` is why.
 */
export const REPOINTED_CUSTOMER_TABLES = [
  'orders',
  'listingEvents',
  'notifications',
  'customerBlocks',
] as const satisfies readonly (keyof Database)[]

export type RepointedCustomerTable = (typeof REPOINTED_CUSTOMER_TABLES)[number]

/**
 * `customerId`-holding tables a blind repoint would leave broken, and why: a
 * verified customer merging in a second identity must never end up with two
 * of the row a fold instead collapses into one. `customer-owned-tables-manifest.test.ts`
 * checks this list, `REPOINTED_CUSTOMER_TABLES`, and `LEFT_BEHIND_CUSTOMER_TABLES`
 * together cover every `customer_id` column the schema has.
 */
export const FOLDED_CUSTOMER_TABLES = {
  favorites:
    'deduplicated against the verified customer’s own favorites rather than repointed, so a listing favorited on both sides does not become two rows',
  carts:
    'folded line-by-line into the verified customer’s cart rather than repointed, so a verified customer never ends up with two carts',
  conversations:
    'folded onto the verified customer’s existing thread on the same subject rather than repointed, so a subject never ends up with two threads',
} as const satisfies Partial<Record<keyof Database, string>>

export type FoldedCustomerTable = keyof typeof FOLDED_CUSTOMER_TABLES

/**
 * `customerId`-holding tables a merge deliberately leaves as they are, and why.
 */
export const LEFT_BEHIND_CUSTOMER_TABLES = {
  customerMerges:
    'the trail record of the merge itself: it names the anonymous customer on purpose, so a stale cookie still resolves forward',
} as const satisfies Partial<Record<keyof Database, string>>

export type LeftBehindCustomerTable = keyof typeof LEFT_BEHIND_CUSTOMER_TABLES
