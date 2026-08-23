import type { AppDatabase } from '../../../db/database.ts'
import type { Payout } from '../../../db/commerce-schema.ts'

export async function payoutsForSeller(db: AppDatabase, sellerId: number): Promise<readonly Payout[]> {
  return db
    .selectFrom('payouts')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('periodStart', 'desc')
    .execute()
}
