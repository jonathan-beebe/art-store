import { sql, type Kysely } from 'kysely'
import { LEDGER_ENTRY_TYPES } from '../../core/escrow/ledger-entry-type.ts'

/**
 * Per-seller money: an auditable entry per movement rather than one mutable
 * balance column. `payouts` is unique per (seller, period), and the `paid_out`
 * entry a run writes is dated at the close of the period it settles, so a
 * second run of the same period pays nothing.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('payouts')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('seller_id', 'integer', (column) => column.notNull().references('sellers.id'))
    .addColumn('period_start', 'text', (column) => column.notNull())
    .addColumn('period_end', 'text', (column) => column.notNull())
    .addColumn('amount_cents', 'integer', (column) => column.notNull())
    .addColumn('paid_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('payouts_seller_id_period_start_index')
    .on('payouts')
    .columns(['seller_id', 'period_start'])
    .unique()
    .execute()

  await db.schema
    .createTable('ledger_entries')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('seller_id', 'integer', (column) => column.notNull().references('sellers.id'))
    .addColumn('fulfillment_id', 'integer', (column) => column.references('fulfillments.id'))
    .addColumn('payout_id', 'integer', (column) => column.references('payouts.id'))
    .addColumn('entry_type', 'text', (column) =>
      column
        .notNull()
        .check(sql`entry_type in (${sql.join(LEDGER_ENTRY_TYPES.map((type) => sql.lit(type)))})`),
    )
    .addColumn('amount_cents', 'integer', (column) => column.notNull())
    .addColumn('occurred_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('ledger_entries_seller_id_occurred_at_index')
    .on('ledger_entries')
    .columns(['seller_id', 'occurred_at'])
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('ledger_entries').execute()
  await db.schema.dropTable('payouts').execute()
}
