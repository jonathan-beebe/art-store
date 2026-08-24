import { addCents, negateCents, subtractCents, ZERO_CENTS, type Cents } from '../money.ts'
import type { LedgerEntryType } from './ledger-entry-type.ts'
import type { LedgerMovement } from './ledger-movement.ts'
import type { SellerId } from '../ids/entity-ids.ts'

/**
 * What a seller's ledger adds up to: money waiting on delivery, money ready
 * for the next payout, and money already sent.
 */
export type LedgerBalance = { heldCents: Cents; availableCents: Cents; paidOutCents: Cents }

/** A ledger movement as read alongside the seller it belongs to. */
export type SellerLedgerMovement = LedgerMovement & { sellerId: SellerId }

export function ledgerBalance(movements: readonly LedgerMovement[]): LedgerBalance {
  const totals: Record<LedgerEntryType, Cents> = { held: ZERO_CENTS, released: ZERO_CENTS, paid_out: ZERO_CENTS }
  for (const movement of movements) {
    totals[movement.entryType] = addCents(totals[movement.entryType], movement.amountCents)
  }

  return {
    heldCents: subtractCents(totals.held, totals.released),
    availableCents: addCents(totals.released, totals.paid_out),
    paidOutCents: negateCents(totals.paid_out),
  }
}

export function isPayable(balance: LedgerBalance): boolean {
  return balance.availableCents > 0
}

/**
 * Every seller's balance, each folded from their own movements in one pass
 * over a shared read of the ledger. A seller with no movements is absent
 * rather than zeroed, so a caller that needs every seller supplies its own
 * zero balance for a miss.
 */
export function ledgerBalancesBySeller(
  movements: readonly SellerLedgerMovement[],
): ReadonlyMap<SellerId, LedgerBalance> {
  const bySeller = new Map<SellerId, SellerLedgerMovement[]>()
  for (const movement of movements) {
    bySeller.set(movement.sellerId, [...(bySeller.get(movement.sellerId) ?? []), movement])
  }

  return new Map([...bySeller].map(([sellerId, own]) => [sellerId, ledgerBalance(own)]))
}
