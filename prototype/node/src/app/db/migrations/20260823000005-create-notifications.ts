import { sql, type Kysely } from 'kysely'

/**
 * A message in one actor's header. Three real foreign keys rather than a
 * polymorphic pair, so a customer merge re-points notifications the same way it
 * re-points favorites and orders; the check keeps exactly one of them set.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('notifications')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('seller_id', 'integer', (column) => column.references('sellers.id'))
    .addColumn('customer_id', 'integer', (column) => column.references('customers.id'))
    .addColumn('admin_id', 'integer', (column) => column.references('admins.id'))
    .addColumn('subject', 'text', (column) => column.notNull())
    .addColumn('body', 'text', (column) => column.notNull())
    .addColumn('url', 'text')
    .addColumn('created_at', 'text', (column) => column.notNull())
    .addColumn('read_at', 'text')
    .addCheckConstraint(
      'notifications_one_recipient_check',
      sql`(seller_id is not null) + (customer_id is not null) + (admin_id is not null) = 1`,
    )
    .execute()

  await db.schema
    .createIndex('notifications_seller_id_read_at_index')
    .on('notifications')
    .columns(['seller_id', 'read_at'])
    .execute()

  await db.schema
    .createIndex('notifications_customer_id_read_at_index')
    .on('notifications')
    .columns(['customer_id', 'read_at'])
    .execute()

  await db.schema
    .createIndex('notifications_admin_id_read_at_index')
    .on('notifications')
    .columns(['admin_id', 'read_at'])
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('notifications').execute()
}
