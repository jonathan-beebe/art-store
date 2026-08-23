import type { ActionContext } from '../action-context.ts'
import type { SellerLedgerMovement } from '../../core/escrow/ledger-balance.ts'
import type { Timestamp } from '../../db/timestamp.ts'

export type { SellerLedgerMovement }

/**
 * Every seller's escrow movements in one read, oldest first. `occurredBy`
 * bounds them to a payout period; `sellerId` narrows to one seller. Left out,
 * each reads the whole ledger.
 */
export async function ledgerMovements(
  { db }: Pick<ActionContext, 'db'>,
  occurredBy?: Timestamp,
  sellerId?: number,
): Promise<readonly SellerLedgerMovement[]> {
  let query = db
    .selectFrom('ledgerEntries')
    .select(['sellerId', 'entryType', 'amountCents'])
    .orderBy('sellerId')
    .orderBy('id')

  if (occurredBy !== undefined) query = query.where('occurredAt', '<=', occurredBy)
  if (sellerId !== undefined) query = query.where('sellerId', '=', sellerId)

  return query.execute()
}
