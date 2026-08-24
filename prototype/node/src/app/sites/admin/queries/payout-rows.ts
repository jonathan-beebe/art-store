import type { ActionContext } from '../../../actions/action-context.ts'
import type { PayoutId, SellerId } from '../../../core/ids/entity-ids.ts'
import type { Cents } from '../../../core/money.ts'
import { shopName } from '../../../core/shop/shop-name.ts'
import type { Day } from '../../../db/commerce-schema.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

export type PayoutRow = {
  id: PayoutId
  sellerId: SellerId
  sellerName: string
  periodStart: Day
  periodEnd: Day
  amountCents: Cents
  paidAt: Timestamp
}

export type PayoutRowsFilter = { sellerId?: SellerId }

/** Every payout, newest first, optionally narrowed to one seller. */
export async function payoutRows(
  { db }: Pick<ActionContext, 'db'>,
  filter: PayoutRowsFilter = {},
): Promise<readonly PayoutRow[]> {
  let query = db
    .selectFrom('payouts')
    .innerJoin('sellers', 'sellers.id', 'payouts.sellerId')
    .select([
      'payouts.id',
      'payouts.sellerId',
      'sellers.shopName',
      'sellers.email',
      'payouts.periodStart',
      'payouts.periodEnd',
      'payouts.amountCents',
      'payouts.paidAt',
    ])
    .orderBy('payouts.paidAt', 'desc')
    .orderBy('payouts.paidAt', 'desc')
    .orderBy('payouts.id', 'desc')

  if (filter.sellerId !== undefined) query = query.where('payouts.sellerId', '=', filter.sellerId)

  const rows = await query.execute()

  return rows.map((row) => ({
    id: row.id,
    sellerId: row.sellerId,
    sellerName: shopName({ shopName: row.shopName, email: row.email }),
    periodStart: row.periodStart,
    periodEnd: row.periodEnd,
    amountCents: row.amountCents,
    paidAt: row.paidAt,
  }))
}
