import { sql, type Kysely } from 'kysely'

/**
 * A customer's in-progress selection. `carts.customer_id` is deliberately not
 * unique: merging an anonymous visitor into a verified customer can leave two,
 * and the cart holding the most items is the one they were shopping with.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('carts')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('customer_id', 'text', (column) => column.notNull().references('customers.id'))
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema.createIndex('carts_customer_id_index').on('carts').column('customer_id').execute()

  await db.schema
    .createTable('cart_items')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('cart_id', 'text', (column) => column.notNull().references('carts.id'))
    .addColumn('listing_id', 'text', (column) => column.notNull().references('listings.id'))
    .addColumn('quantity', 'integer', (column) => column.notNull().check(sql`quantity >= 1`))
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('cart_items_cart_id_listing_id_index')
    .on('cart_items')
    .columns(['cart_id', 'listing_id'])
    .unique()
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('cart_items').execute()
  await db.schema.dropTable('carts').execute()
}
