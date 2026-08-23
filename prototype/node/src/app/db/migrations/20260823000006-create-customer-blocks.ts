import type { Kysely } from 'kysely'

/**
 * An admin's block on a customer. A blocked customer can still browse; what
 * they lose is adding to a cart, checking out, and sending messages.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('customer_blocks')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('customer_id', 'integer', (column) => column.notNull().references('customers.id'))
    .addColumn('admin_id', 'integer', (column) => column.notNull().references('admins.id'))
    .addColumn('reason', 'text', (column) => column.notNull())
    .addColumn('created_at', 'text', (column) => column.notNull())
    .addColumn('lifted_at', 'text')
    .execute()

  await db.schema
    .createIndex('customer_blocks_customer_id_lifted_at_index')
    .on('customer_blocks')
    .columns(['customer_id', 'lifted_at'])
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('customer_blocks').execute()
}
