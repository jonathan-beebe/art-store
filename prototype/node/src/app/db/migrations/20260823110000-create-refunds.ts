import { sql, type Kysely } from 'kysely'
import { REFUND_ISSUER_TYPES } from '../../core/orders/refund.ts'

/**
 * One row per reversal: a seller's decline or a platform refund, always for
 * the whole fulfillment subtotal. The `payments` row it names is the approved
 * charge the money came in on, so a refund can always be traced to what paid
 * for it. `orders.refunded_cents` is these rows summed per order.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('refunds')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('order_id', 'text', (column) => column.notNull().references('orders.id'))
    .addColumn('fulfillment_id', 'text', (column) => column.notNull().references('fulfillments.id'))
    .addColumn('payment_id', 'text', (column) => column.notNull().references('payments.id'))
    .addColumn('amount_cents', 'integer', (column) => column.notNull())
    .addColumn('reason', 'text', (column) => column.notNull())
    .addColumn('issued_by_type', 'text', (column) =>
      column
        .notNull()
        .check(sql`issued_by_type in (${sql.join(REFUND_ISSUER_TYPES.map((type) => sql.lit(type)))})`),
    )
    .addColumn('issued_by_id', 'text', (column) => column.notNull())
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('refunds_order_id_created_at_index')
    .on('refunds')
    .columns(['order_id', 'created_at'])
    .execute()

  // One reversal per fulfillment: the second one is refused by the state
  // machine, and this is the same rule where two writers could race it.
  await db.schema
    .createIndex('refunds_fulfillment_id_index')
    .on('refunds')
    .column('fulfillment_id')
    .unique()
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('refunds').execute()
}
