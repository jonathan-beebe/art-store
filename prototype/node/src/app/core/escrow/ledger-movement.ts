import { negateCents, type Cents } from '../money.ts'
import type { LedgerEntryType } from './ledger-entry-type.ts'

/**
 * One signed step through escrow. Holds and releases are positive; a payout
 * and a refund are negative, which is what lets a balance fold the whole
 * ledger by adding.
 */
export type LedgerMovement = { entryType: LedgerEntryType; amountCents: Cents }

export function holdMovement(netCents: Cents): LedgerMovement {
  return { entryType: 'held', amountCents: netCents }
}

export function releaseMovement(netCents: Cents): LedgerMovement {
  return { entryType: 'released', amountCents: netCents }
}

export function payoutMovement(availableCents: Cents): LedgerMovement {
  return { entryType: 'paid_out', amountCents: negateCents(availableCents) }
}

/** A decline or a refund taking the fulfillment's whole net back off the seller. */
export function refundMovement(netCents: Cents): LedgerMovement {
  return { entryType: 'refunded', amountCents: negateCents(netCents) }
}
