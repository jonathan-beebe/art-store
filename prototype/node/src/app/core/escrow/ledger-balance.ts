import { addCents, type Cents } from '../money.ts'
import type { LedgerEntryType } from './ledger-entry-type.ts'
import type { LedgerMovement } from './ledger-movement.ts'

/**
 * What a seller's ledger adds up to: money waiting on delivery, money ready
 * for the next payout, and money already sent.
 */
export type LedgerBalance = { heldCents: Cents; availableCents: Cents; paidOutCents: Cents }

export function ledgerBalance(movements: readonly LedgerMovement[]): LedgerBalance {
  const totals: Record<LedgerEntryType, Cents> = { held: 0, released: 0, paid_out: 0 }
  for (const movement of movements) {
    totals[movement.entryType] = addCents(totals[movement.entryType], movement.amountCents)
  }

  return {
    heldCents: addCents(totals.held, -totals.released),
    availableCents: addCents(totals.released, totals.paid_out),
    paidOutCents: addCents(0, -totals.paid_out),
  }
}

export function isPayable(balance: LedgerBalance): boolean {
  return balance.availableCents > 0
}
