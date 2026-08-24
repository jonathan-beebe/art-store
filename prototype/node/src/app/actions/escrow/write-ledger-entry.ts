import type { FulfillmentId, PayoutId, SellerId } from '../../core/ids/entity-ids.ts'
import type { LedgerMovement } from '../../core/escrow/ledger-movement.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionDid } from '../action-story.ts'
import type { Timestamp } from '../../db/timestamp.ts'

export type LedgerEntryDraft = {
  sellerId: SellerId
  /** The fulfillment the money belongs to, or null for a payout. */
  fulfillmentId: FulfillmentId | null
  /** The payout that sent it, or null for a hold or a release. */
  payoutId: PayoutId | null
  movement: LedgerMovement
  /**
   * When the money moved, which is not always when the row was written: a
   * payout is dated at the close of the period it settles.
   */
  occurredAt: Timestamp
}

/**
 * One step through escrow, written and logged. The balance is a fold over these
 * rows, so a balance nobody can explain is answered by reading the
 * `ledger.write` lines for that seller back in order.
 */
export async function writeLedgerEntry(
  context: ActionContext,
  draft: LedgerEntryDraft,
  at: Date,
): Promise<void> {
  await context.db
    .insertInto('ledgerEntries')
    .values({
      id: newId('led', at),
      sellerId: draft.sellerId,
      fulfillmentId: draft.fulfillmentId,
      payoutId: draft.payoutId,
      entryType: draft.movement.entryType,
      amountCents: draft.movement.amountCents,
      occurredAt: draft.occurredAt,
    })
    .execute()

  actionDid(
    context,
    'ledger.write',
    `${draft.movement.entryType} ${draft.movement.amountCents} for ${draft.sellerId}`,
    {
      seller_id: draft.sellerId,
      fulfillment_id: draft.fulfillmentId,
      payout_id: draft.payoutId,
      entry_type: draft.movement.entryType,
      amount_cents: draft.movement.amountCents,
    },
    'debug',
  )
}
