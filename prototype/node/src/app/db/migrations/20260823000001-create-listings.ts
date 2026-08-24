import { sql, type Kysely } from 'kysely'
import { LISTING_EVENT_TYPES } from '../../core/listings/listing-event-type.ts'
import { LISTING_STATUSES } from '../../core/listings/listing-status.ts'
import { REMOVAL_KINDS } from '../../core/moderation/listing-removal.ts'

/**
 * The catalogue and what visitors do to it. `listing_removals` is the admin's
 * moderation record: a listing with an unlifted row is off the storefront
 * whatever its status.
 *
 * `sellers`, `customers`, and `admins` belong to the identity migrations.
 * SQLite resolves a foreign key when a row is written, not when the table is
 * created, so the order the two sets of migrations run in does not matter.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('listings')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('seller_id', 'text', (column) => column.notNull().references('sellers.id'))
    .addColumn('title', 'text', (column) => column.notNull())
    .addColumn('slug', 'text', (column) => column.notNull().unique())
    .addColumn('description', 'text')
    .addColumn('medium', 'text')
    .addColumn('dimensions', 'text')
    .addColumn('price_cents', 'integer', (column) => column.notNull().check(sql`price_cents >= 0`))
    .addColumn('quantity', 'integer', (column) =>
      column.notNull().defaultTo(1).check(sql`quantity >= 0`),
    )
    .addColumn('status', 'text', (column) =>
      column
        .notNull()
        .defaultTo('draft')
        .check(sql`status in (${sql.join(LISTING_STATUSES.map((status) => sql.lit(status)))})`),
    )
    .addColumn('image_path', 'text')
    .addColumn('created_at', 'text', (column) => column.notNull())
    .addColumn('updated_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('listings_seller_id_status_index')
    .on('listings')
    .columns(['seller_id', 'status'])
    .execute()

  await db.schema
    .createIndex('listings_status_created_at_index')
    .on('listings')
    .columns(['status', 'created_at'])
    .execute()

  await db.schema
    .createTable('listing_events')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('listing_id', 'text', (column) => column.notNull().references('listings.id'))
    .addColumn('customer_id', 'text', (column) => column.references('customers.id'))
    .addColumn('event_type', 'text', (column) =>
      column
        .notNull()
        .check(sql`event_type in (${sql.join(LISTING_EVENT_TYPES.map((type) => sql.lit(type)))})`),
    )
    .addColumn('occurred_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('listing_events_listing_id_event_type_occurred_at_index')
    .on('listing_events')
    .columns(['listing_id', 'event_type', 'occurred_at'])
    .execute()

  await db.schema
    .createTable('favorites')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('customer_id', 'text', (column) => column.notNull().references('customers.id'))
    .addColumn('listing_id', 'text', (column) => column.notNull().references('listings.id'))
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('favorites_customer_id_listing_id_index')
    .on('favorites')
    .columns(['customer_id', 'listing_id'])
    .unique()
    .execute()

  await db.schema
    .createTable('listing_removals')
    .addColumn('id', 'text', (column) => column.primaryKey().notNull())
    .addColumn('listing_id', 'text', (column) => column.notNull().references('listings.id'))
    .addColumn('admin_id', 'text', (column) => column.notNull().references('admins.id'))
    .addColumn('kind', 'text', (column) =>
      column.notNull().check(sql`kind in (${sql.join(REMOVAL_KINDS.map((kind) => sql.lit(kind)))})`),
    )
    .addColumn('reason', 'text', (column) => column.notNull())
    .addColumn('created_at', 'text', (column) => column.notNull())
    .addColumn('lifted_at', 'text')
    .execute()

  await db.schema
    .createIndex('listing_removals_listing_id_lifted_at_index')
    .on('listing_removals')
    .columns(['listing_id', 'lifted_at'])
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('listing_removals').execute()
  await db.schema.dropTable('favorites').execute()
  await db.schema.dropTable('listing_events').execute()
  await db.schema.dropTable('listings').execute()
}
