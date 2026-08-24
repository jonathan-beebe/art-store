import { sql, type Kysely } from 'kysely'
import { ACTOR_TYPES } from '../../core/auth/actor-type.ts'
import { CONVERSATION_KINDS } from '../../core/messaging/conversation-kind.ts'

/**
 * One table serves every pairing: `kind` says which two participant columns are
 * filled and which subject column, if any, names what the thread is about. Four
 * tables would repeat the same message store four times.
 *
 * `subject_key` is `subjectKey(subject)` (`conversation-subject.ts`) written
 * back to the row: the app-side equality that decides reuse and the
 * database-side uniqueness that guards it are one rule. A composite unique
 * index over the five nullable participant/subject columns below could not
 * take that job, because SQL counts two nulls as distinct.
 *
 * A conversation has exactly two participants, so a message needs only one
 * `read_at` — the reader is always the participant who did not send it.
 *
 * A `listing_faqs` row exists only while it is published: unpublishing deletes
 * it, and re-publishing is one click from the thread the answer came from. Its
 * `(listing_id, source_message_id)` uniqueness refuses a second publish of the
 * same message; a row with no source (a FAQ written by hand) carries a null
 * there, and SQLite does not count two nulls as a collision.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('conversations')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('kind', 'text', (column) =>
      column.notNull().check(sql`kind in (${sql.join(CONVERSATION_KINDS.map((kind) => sql.lit(kind)))})`),
    )
    .addColumn('subject_key', 'text', (column) => column.notNull().unique())
    .addColumn('seller_id', 'text', (column) => column.references('sellers.id'))
    .addColumn('customer_id', 'text', (column) => column.references('customers.id'))
    .addColumn('admin_id', 'text', (column) => column.references('admins.id'))
    .addColumn('listing_id', 'text', (column) => column.references('listings.id'))
    .addColumn('fulfillment_id', 'text', (column) => column.references('fulfillments.id'))
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
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('conversation_id', 'text', (column) =>
      column.notNull().references('conversations.id'),
    )
    .addColumn('sender_type', 'text', (column) =>
      column
        .notNull()
        .check(sql`sender_type in (${sql.join(ACTOR_TYPES.map((type) => sql.lit(type)))})`),
    )
    .addColumn('sender_id', 'text', (column) => column.notNull())
    .addColumn('body', 'text', (column) => column.notNull())
    .addColumn('sent_at', 'text', (column) => column.notNull())
    .addColumn('read_at', 'text')
    .execute()

  await db.schema
    .createIndex('messages_conversation_id_index')
    .on('messages')
    .columns(['conversation_id', 'sent_at'])
    .execute()

  await db.schema
    .createTable('listing_faqs')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('listing_id', 'text', (column) => column.notNull().references('listings.id'))
    .addColumn('question', 'text', (column) => column.notNull())
    .addColumn('answer', 'text', (column) => column.notNull())
    .addColumn('source_message_id', 'text', (column) => column.references('messages.id'))
    .addColumn('published_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('listing_faqs_listing_id_index')
    .on('listing_faqs')
    .columns(['listing_id', 'published_at'])
    .execute()

  await db.schema
    .createIndex('listing_faqs_listing_id_source_message_id_index')
    .on('listing_faqs')
    .columns(['listing_id', 'source_message_id'])
    .unique()
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('listing_faqs').execute()
  await db.schema.dropTable('messages').execute()
  await db.schema.dropTable('conversations').execute()
}
