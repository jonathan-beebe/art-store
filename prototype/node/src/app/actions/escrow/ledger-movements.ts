import type { ActionContext } from '../action-context.ts'
import type { LedgerMovement } from '../../core/escrow/ledger-movement.ts'
import type { Timestamp } from '../../db/timestamp.ts'

export type SellerLedgerMovement = LedgerMovement & { sellerId: number }

/**
 * Every seller's escrow movements in one read, oldest first. `occurredBy`
 * bounds them to a payout period; left out, it reads the whole ledger.
 */
export async function ledgerMovements(
  { db }: Pick<ActionContext, 'db'>,
  occurredBy?: Timestamp,
): Promise<readonly SellerLedgerMovement[]> {
  let query = db
    .selectFrom('ledgerEntries')
    .select(['sellerId', 'entryType', 'amountCents'])
    .orderBy('sellerId')
    .orderBy('id')

  if (occurredBy !== undefined) query = query.where('occurredAt', '<=', occurredBy)

  return query.execute()
}
