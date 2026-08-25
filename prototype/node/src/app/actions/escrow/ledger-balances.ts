import { sql, type RawBuilder } from 'kysely'
import type { ActionContext } from '../action-context.ts'
import type { SellerId } from '../../core/ids/entity-ids.ts'
import type { Timestamp } from '../../db/timestamp.ts'
import { centsFromColumn, ZERO_CENTS } from '../../core/money.ts'
import type { LedgerBalance } from '../../core/escrow/ledger-balance.ts'

const ZERO_BALANCE: LedgerBalance = { heldCents: ZERO_CENTS, availableCents: ZERO_CENTS, paidOutCents: ZERO_CENTS }

type RawSum = number | string | bigint
type BalanceRow = { heldCents: RawSum; availableCents: RawSum; paidOutCents: RawSum }
type SellerBalanceRow = BalanceRow & { sellerId: SellerId }

function toBalance(row: BalanceRow): LedgerBalance {
  return {
    heldCents: centsFromColumn(row.heldCents),
    availableCents: centsFromColumn(row.availableCents),
    paidOutCents: centsFromColumn(row.paidOutCents),
  }
}

/**
 * Whether the money on a refund's fulfillment had already moved to available
 * as of `occurredBy` — the fact `ledgerBalance`'s `releasedFulfillmentIds`
 * computes in JS, read here as an EXISTS so both refund branches below can
 * share it. `fulfillment_id = fulfillment_id` never matches on a null (SQL
 * null equality), the same guard the JS fold makes explicit.
 */
function releasedAsOf(occurredBy: Timestamp | undefined): RawBuilder<unknown> {
  const bound = occurredBy === undefined ? sql`` : sql` and released.occurred_at <= ${occurredBy}`

  return sql`exists (
    select 1 from ledger_entries released
    where released.entry_type = 'released'
      and released.fulfillment_id = ledger_entries.fulfillment_id${bound}
  )`
}

/** `ledgerBalance`'s bucket arithmetic as one row of SQL sums, bound the same way its EXISTS check is. */
function bucketSums(occurredBy: Timestamp | undefined): RawBuilder<unknown> {
  return sql`
    coalesce(sum(case entry_type
      when 'held' then amount_cents
      when 'released' then -amount_cents
      when 'refunded' then case when ${releasedAsOf(occurredBy)} then 0 else amount_cents end
      else 0 end), 0) as held_cents,
    coalesce(sum(case entry_type
      when 'released' then amount_cents
      when 'paid_out' then amount_cents
      when 'refunded' then case when ${releasedAsOf(occurredBy)} then amount_cents else 0 end
      else 0 end), 0) as available_cents,
    coalesce(sum(case when entry_type = 'paid_out' then -amount_cents else 0 end), 0) as paid_out_cents
  `
}

function whereClause(conditions: readonly RawBuilder<unknown>[]): RawBuilder<unknown> {
  return conditions.length === 0 ? sql`` : sql`where ${sql.join(conditions, sql` and `)}`
}

/** The balance a read's first row names, zeroed when the read found nothing. */
function firstBalance(rows: readonly BalanceRow[]): LedgerBalance {
  const row = rows[0]

  return row === undefined ? ZERO_BALANCE : toBalance(row)
}

/**
 * Every seller's balance in one grouped read, bounded to a payout period when
 * `occurredBy` is given. A seller with no ledger entries is absent, same as
 * `ledgerBalancesBySeller`.
 */
export async function sellerBalances(
  { db }: Pick<ActionContext, 'db'>,
  occurredBy?: Timestamp,
): Promise<ReadonlyMap<SellerId, LedgerBalance>> {
  const conditions = occurredBy === undefined ? [] : [sql`occurred_at <= ${occurredBy}`]

  const { rows } = await sql<SellerBalanceRow>`
    select seller_id, ${bucketSums(occurredBy)}
    from ledger_entries
    ${whereClause(conditions)}
    group by seller_id
  `.execute(db)

  return new Map(rows.map((row) => [row.sellerId, toBalance(row)]))
}

/** One seller's balance, zeroed when they have no ledger entries. */
export async function sellerBalance(
  { db }: Pick<ActionContext, 'db'>,
  sellerId: SellerId,
  occurredBy?: Timestamp,
): Promise<LedgerBalance> {
  const conditions: RawBuilder<unknown>[] = [sql`seller_id = ${sellerId}`]
  if (occurredBy !== undefined) conditions.push(sql`occurred_at <= ${occurredBy}`)

  const { rows } = await sql<SellerBalanceRow>`
    select seller_id, ${bucketSums(occurredBy)}
    from ledger_entries
    ${whereClause(conditions)}
    group by seller_id
  `.execute(db)

  return firstBalance(rows)
}

/** Escrow across every seller, folded to one row rather than one per seller. */
export async function platformBalance({ db }: Pick<ActionContext, 'db'>): Promise<LedgerBalance> {
  const { rows } = await sql<BalanceRow>`
    select ${bucketSums(undefined)}
    from ledger_entries
  `.execute(db)

  return firstBalance(rows)
}
