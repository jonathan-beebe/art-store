import type { ActionContext } from '../../../actions/action-context.ts'
import { ledgerMovements } from '../../../actions/escrow/ledger-movements.ts'
import { ledgerBalance, type LedgerBalance } from '../../../core/escrow/ledger-balance.ts'
import { addCents, type Cents } from '../../../core/money.ts'

/** Escrow across every seller, plus what the platform kept out of it. */
export type PlatformMoney = LedgerBalance & { feesEarnedCents: Cents }

/**
 * The platform's side of the ledger. The balance folds every seller's
 * movements at once — `ledgerBalance` adds signed amounts, so one seller's
 * ledger and all of them together fold the same way.
 */
export async function platformMoney(
  context: Pick<ActionContext, 'db'>,
): Promise<PlatformMoney> {
  const movements = await ledgerMovements(context)

  return { ...ledgerBalance(movements), feesEarnedCents: await feesEarned(context) }
}

/**
 * The fee is priced at placement and earned when the order pays, which is the
 * moment the seller's net is held — so a held entry is what says the fee is
 * real, and an unpaid order's fee is not counted.
 */
async function feesEarned({ db }: Pick<ActionContext, 'db'>): Promise<Cents> {
  const settled = await db
    .selectFrom('fulfillments')
    .innerJoin('ledgerEntries', 'ledgerEntries.fulfillmentId', 'fulfillments.id')
    .where('ledgerEntries.entryType', '=', 'held')
    .select('fulfillments.feeCents')
    .execute()

  return settled.reduce((total, row) => addCents(total, row.feeCents), 0)
}
