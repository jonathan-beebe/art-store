import type { ActionContext } from '../../../actions/action-context.ts'
import { ledgerBalance, type LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import type { LedgerEntryType } from '../../../core/escrow/ledger-entry-type.ts'
import type { Cents } from '../../../core/money.ts'
import { shopName } from '../../../core/shop/shop-name.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export type LedgerRow = {
  id: number
  sellerId: number
  sellerName: string
  entryType: LedgerEntryType
  amountCents: Cents
  fulfillmentId: number | null
  payoutId: number | null
  occurredAt: Timestamp
}

export type LedgerRowsFilter = { sellerId?: number; entryType?: LedgerEntryType }

/** The rows a filter matches, plus what they fold to — a partial ledger reads as a partial balance. */
export type LedgerRowsResult = { rows: readonly LedgerRow[]; totals: LedgerBalance }

export async function ledgerRows(
  { db }: Pick<ActionContext, 'db'>,
  filter: LedgerRowsFilter = {},
): Promise<LedgerRowsResult> {
  let query = db
    .selectFrom('ledgerEntries')
    .innerJoin('sellers', 'sellers.id', 'ledgerEntries.sellerId')
    .select([
      'ledgerEntries.id',
      'ledgerEntries.sellerId',
      'sellers.shopName',
      'sellers.email',
      'ledgerEntries.entryType',
      'ledgerEntries.amountCents',
      'ledgerEntries.fulfillmentId',
      'ledgerEntries.payoutId',
      'ledgerEntries.occurredAt',
    ])
    .orderBy('ledgerEntries.occurredAt', 'desc')
    .orderBy('ledgerEntries.id', 'desc')

  if (filter.sellerId !== undefined) query = query.where('ledgerEntries.sellerId', '=', filter.sellerId)
  if (filter.entryType !== undefined) query = query.where('ledgerEntries.entryType', '=', filter.entryType)

  const rows = await query.execute()

  const mapped = rows.map((row) => ({
    id: row.id,
    sellerId: row.sellerId,
    sellerName: shopName({ shopName: row.shopName, email: row.email }),
    entryType: row.entryType,
    amountCents: row.amountCents,
    fulfillmentId: row.fulfillmentId,
    payoutId: row.payoutId,
    occurredAt: row.occurredAt,
  }))

  return { rows: mapped, totals: ledgerBalance(mapped) }
}
