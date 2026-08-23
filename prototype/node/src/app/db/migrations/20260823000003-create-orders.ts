import type { Kysely } from 'kysely'

/**
 * An order and its parts. `order_items` snapshot the title and price so an
 * order reads the same after the seller edits the listing behind it;
 * `payments` is one row per charge attempt, not one per order; `fulfillments`
 * carry the fee and net priced once at placement, one row per (order, seller).
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('orders')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('customer_id', 'integer', (column) => column.notNull().references('customers.id'))
    .addColumn('email', 'text')
    .addColumn('status', 'text', (column) => column.notNull())
    .addColumn('shipping_name', 'text', (column) => column.notNull())
    .addColumn('shipping_line1', 'text', (column) => column.notNull())
    .addColumn('shipping_line2', 'text')
    .addColumn('shipping_city', 'text', (column) => column.notNull())
    .addColumn('shipping_region', 'text', (column) => column.notNull())
    .addColumn('shipping_postal_code', 'text', (column) => column.notNull())
    .addColumn('shipping_country', 'text', (column) => column.notNull())
    .addColumn('subtotal_cents', 'integer', (column) => column.notNull())
    .addColumn('total_cents', 'integer', (column) => column.notNull())
    .addColumn('placed_at', 'text', (column) => column.notNull())
    .addColumn('finalized_at', 'text')
    .addColumn('cancelled_at', 'text')
    .execute()

  await db.schema
    .createIndex('orders_customer_id_placed_at_index')
    .on('orders')
    .columns(['customer_id', 'placed_at'])
    .execute()

  await db.schema
    .createTable('order_items')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('order_id', 'integer', (column) => column.notNull().references('orders.id'))
    .addColumn('listing_id', 'integer', (column) => column.notNull().references('listings.id'))
    .addColumn('seller_id', 'integer', (column) => column.notNull().references('sellers.id'))
    .addColumn('title', 'text', (column) => column.notNull())
    .addColumn('unit_price_cents', 'integer', (column) => column.notNull())
    .addColumn('quantity', 'integer', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('order_items_order_id_index')
    .on('order_items')
    .column('order_id')
    .execute()

  await db.schema
    .createTable('payments')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('order_id', 'integer', (column) => column.notNull().references('orders.id'))
    .addColumn('status', 'text', (column) => column.notNull())
    .addColumn('amount_cents', 'integer', (column) => column.notNull())
    .addColumn('card_last_four', 'text', (column) => column.notNull())
    .addColumn('decline_reason', 'text')
    .addColumn('processed_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('payments_order_id_processed_at_index')
    .on('payments')
    .columns(['order_id', 'processed_at'])
    .execute()

  await db.schema
    .createTable('fulfillments')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('order_id', 'integer', (column) => column.notNull().references('orders.id'))
    .addColumn('seller_id', 'integer', (column) => column.notNull().references('sellers.id'))
    .addColumn('status', 'text', (column) => column.notNull().defaultTo('awaiting_shipment'))
    .addColumn('carrier', 'text')
    .addColumn('tracking_number', 'text')
    .addColumn('subtotal_cents', 'integer', (column) => column.notNull())
    .addColumn('fee_cents', 'integer', (column) => column.notNull())
    .addColumn('net_cents', 'integer', (column) => column.notNull())
    .addColumn('shipped_at', 'text')
    .addColumn('delivered_at', 'text')
    .execute()

  await db.schema
    .createIndex('fulfillments_order_id_seller_id_index')
    .on('fulfillments')
    .columns(['order_id', 'seller_id'])
    .unique()
    .execute()

  await db.schema
    .createIndex('fulfillments_seller_id_status_index')
    .on('fulfillments')
    .columns(['seller_id', 'status'])
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('fulfillments').execute()
  await db.schema.dropTable('payments').execute()
  await db.schema.dropTable('order_items').execute()
  await db.schema.dropTable('orders').execute()
}
