import type { Kysely } from 'kysely'

/**
 * One message waiting to leave the application. A row is written inside the
 * business transaction that caused it, so a rolled-back order sends nothing;
 * the drain reads `delivered_at is null` and stamps it after the file lands.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('outbox_messages')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('recipient', 'text', (column) => column.notNull())
    .addColumn('subject', 'text', (column) => column.notNull())
    .addColumn('body', 'text', (column) => column.notNull())
    .addColumn('url', 'text')
    .addColumn('created_at', 'text', (column) => column.notNull())
    .addColumn('delivered_at', 'text')
    .execute()

  await db.schema
    .createIndex('outbox_messages_delivered_at_created_at_index')
    .on('outbox_messages')
    .columns(['delivered_at', 'created_at'])
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('outbox_messages').execute()
}
