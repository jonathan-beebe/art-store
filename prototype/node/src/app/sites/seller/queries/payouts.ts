import type { SellerId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { toCount } from '../../../db/count.ts'
import type { LedgerEntry, Payout } from '../../../db/commerce-schema.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'

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
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<readonly LedgerEntry[]> {
  return db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('occurredAt', 'desc')
    .orderBy('id', 'desc')
    .offset(page.offset)
    .limit(page.limit)
    .execute()
}

export async function countLedgerEntriesForSeller(db: AppDatabase, sellerId: SellerId): Promise<number> {
  const counted = await db
    .selectFrom('ledgerEntries')
    .select((eb) => eb.fn.countAll<string | number | bigint>().as('total'))
    .where('sellerId', '=', sellerId)
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}
