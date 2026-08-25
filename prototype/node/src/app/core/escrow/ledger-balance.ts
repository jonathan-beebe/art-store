import { addCents, subtractCents, ZERO_CENTS, type Cents } from '../money.ts'
import type { LedgerMovement } from './ledger-movement.ts'
import type { FulfillmentId, SellerId } from '../ids/entity-ids.ts'

/**
 * What a seller's ledger adds up to: money waiting on delivery, money ready
 * for the next payout, and money already sent. `availableCents` goes negative
 * when a refund lands after the money was released, and stays negative until a
 * later week's sales cover it.
 */
export type LedgerBalance = { heldCents: Cents; availableCents: Cents; paidOutCents: Cents }

/**
 * A movement as the fold reads it. The fulfillment is what tells a refund
 * which bucket it reverses: money still in escrow comes out of held, money
 * already released comes out of available.
 */
export type BalanceMovement = LedgerMovement & { fulfillmentId: FulfillmentId | null }

/** A ledger movement as read alongside the seller it belongs to. */
export type SellerLedgerMovement = BalanceMovement & { sellerId: SellerId }

/** Every fulfillment whose escrow has already moved to available. */
function releasedFulfillmentIds(movements: readonly BalanceMovement[]): ReadonlySet<FulfillmentId> {
  const released = new Set<FulfillmentId>()
  for (const movement of movements) {
    if (movement.entryType === 'released' && movement.fulfillmentId !== null) {
      released.add(movement.fulfillmentId)
    }
  }

  return released
}

export function ledgerBalance(movements: readonly BalanceMovement[]): LedgerBalance {
  const released = releasedFulfillmentIds(movements)
  let heldCents = ZERO_CENTS
  let availableCents = ZERO_CENTS
  let paidOutCents = ZERO_CENTS

  for (const movement of movements) {
    switch (movement.entryType) {
      case 'held':
        heldCents = addCents(heldCents, movement.amountCents)
        break
      case 'released':
        heldCents = subtractCents(heldCents, movement.amountCents)
        availableCents = addCents(availableCents, movement.amountCents)
        break
      case 'paid_out':
        availableCents = addCents(availableCents, movement.amountCents)
        paidOutCents = subtractCents(paidOutCents, movement.amountCents)
        break
      case 'refunded':
        if (movement.fulfillmentId !== null && released.has(movement.fulfillmentId)) {
          availableCents = addCents(availableCents, movement.amountCents)
        } else {
          heldCents = addCents(heldCents, movement.amountCents)
        }
        break
    }
  }

  return { heldCents, availableCents, paidOutCents }
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
    const existing = bySeller.get(movement.sellerId) ?? []
    existing.push(movement)
    bySeller.set(movement.sellerId, existing)
  }

  return new Map([...bySeller].map(([sellerId, own]) => [sellerId, ledgerBalance(own)]))
}
