import type { ActionContext } from '../action-context.ts'
import { ledgerMovements } from './ledger-movements.ts'
import { ledgerBalance, type LedgerBalance } from '../../core/escrow/ledger-balance.ts'
import type { Timestamp } from '../../db/timestamp.ts'

/**
 * A seller's money folded from their ledger: held until delivery, available for
 * the next payout, and paid out over their lifetime.
 */
export async function sellerBalance(
  context: Pick<ActionContext, 'db'>,
  sellerId: number,
  occurredBy?: Timestamp,
): Promise<LedgerBalance> {
  const movements = await ledgerMovements(context, occurredBy)

  return ledgerBalance(movements.filter((movement) => movement.sellerId === sellerId))
}
