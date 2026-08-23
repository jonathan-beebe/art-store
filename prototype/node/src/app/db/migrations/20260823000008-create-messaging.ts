import type { Kysely } from 'kysely'

/**
 * One table serves every pairing: `kind` says which two participant columns are
 * filled and which subject column, if any, names what the thread is about. Four
 * tables would repeat the same message store four times.
 *
 * A conversation has exactly two participants, so a message needs only one
 * `read_at` — the reader is always the participant who did not send it.
 *
 * A `listing_faqs` row exists only while it is published: unpublishing deletes
 * it, and re-publishing is one click from the thread the answer came from.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('conversations')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('kind', 'text', (column) => column.notNull())
    .addColumn('seller_id', 'integer', (column) => column.references('sellers.id'))
    .addColumn('customer_id', 'integer', (column) => column.references('customers.id'))
    .addColumn('admin_id', 'integer', (column) => column.references('admins.id'))
    .addColumn('listing_id', 'integer', (column) => column.references('listings.id'))
    .addColumn('fulfillment_id', 'integer', (column) => column.references('fulfillments.id'))
    .addColumn('created_at', 'text', (column) => column.notNull())
    .addColumn('last_message_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('conversations_seller_id_last_message_at_index')
    .on('conversations')
    .columns(['seller_id', 'last_message_at'])
    .execute()

  await db.schema
    .createIndex('conversations_customer_id_last_message_at_index')
    .on('conversations')
    .columns(['customer_id', 'last_message_at'])
    .execute()

  await db.schema
    .createIndex('conversations_admin_id_last_message_at_index')
    .on('conversations')
    .columns(['admin_id', 'last_message_at'])
    .execute()

  await db.schema
    .createIndex('conversations_kind_index')
    .on('conversations')
    .columns(['kind', 'listing_id', 'fulfillment_id'])
    .execute()

  await db.schema
    .createTable('messages')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('conversation_id', 'integer', (column) =>
      column.notNull().references('conversations.id'),
    )
    .addColumn('sender_type', 'text', (column) => column.notNull())
    .addColumn('sender_id', 'integer', (column) => column.notNull())
    .addColumn('body', 'text', (column) => column.notNull())
    .addColumn('sent_at', 'text', (column) => column.notNull())
    .addColumn('read_at', 'text')
    .execute()

  await db.schema
    .createIndex('messages_conversation_id_index')
    .on('messages')
    .columns(['conversation_id', 'id'])
    .execute()

  await db.schema
    .createTable('listing_faqs')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('listing_id', 'integer', (column) => column.notNull().references('listings.id'))
    .addColumn('question', 'text', (column) => column.notNull())
    .addColumn('answer', 'text', (column) => column.notNull())
    .addColumn('source_message_id', 'integer', (column) => column.references('messages.id'))
    .addColumn('published_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('listing_faqs_listing_id_index')
    .on('listing_faqs')
    .columns(['listing_id', 'id'])
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('listing_faqs').execute()
  await db.schema.dropTable('messages').execute()
  await db.schema.dropTable('conversations').execute()
}
