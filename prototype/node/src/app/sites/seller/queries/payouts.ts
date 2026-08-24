import type { SellerId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import type { LedgerEntry, Payout } from '../../../db/commerce-schema.ts'

export async function payoutsForSeller(db: AppDatabase, sellerId: SellerId): Promise<readonly Payout[]> {
  return db
    .selectFrom('payouts')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('periodStart', 'desc')
    .execute()
}

/**
 * Every step the seller's money took, newest first — holds, releases, payouts,
 * and the refunds that took some of it back.
 */
export async function ledgerEntriesForSeller(
  db: AppDatabase,
  sellerId: SellerId,
): Promise<readonly LedgerEntry[]> {
  return db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('occurredAt', 'desc')
    .orderBy('id', 'desc')
    .execute()
}
