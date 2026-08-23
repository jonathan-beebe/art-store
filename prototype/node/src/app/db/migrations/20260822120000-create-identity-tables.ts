import { sql, type Kysely } from 'kysely'
import { ACTOR_TYPES } from '../../core/auth/actor-type.ts'

/**
 * The five tables identity runs on. Timestamps are ISO-8601 UTC text, which
 * SQLite orders lexicographically, so an expiry comparison is a plain `<`.
 *
 * `customers.email` is nullable: every storefront visitor gets a row before
 * anyone gives an address, and the unique index still keeps one address to one
 * customer because SQLite treats nulls as distinct.
 */
export async function up(db: Kysely<unknown>): Promise<void> {
  await db.schema
    .createTable('sellers')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('email', 'text', (column) => column.notNull())
    .addColumn('name', 'text')
    .addColumn('shop_name', 'text')
    .addColumn('email_verified_at', 'text')
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema.createIndex('sellers_email').on('sellers').column('email').unique().execute()

  await db.schema
    .createTable('customers')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('email', 'text')
    .addColumn('name', 'text')
    .addColumn('email_verified_at', 'text')
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema.createIndex('customers_email').on('customers').column('email').unique().execute()

  await db.schema
    .createTable('admins')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('email', 'text', (column) => column.notNull())
    .addColumn('name', 'text', (column) => column.notNull())
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema.createIndex('admins_email').on('admins').column('email').unique().execute()

  await db.schema
    .createTable('magic_links')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('token_digest', 'text', (column) => column.notNull())
    .addColumn('email', 'text', (column) => column.notNull())
    .addColumn('actor_type', 'text', (column) =>
      column.notNull().check(sql`actor_type in (${sql.join(ACTOR_TYPES.map((type) => sql.lit(type)))})`),
    )
    .addColumn('redirect_to', 'text')
    .addColumn('expires_at', 'text', (column) => column.notNull())
    .addColumn('consumed_at', 'text')
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  await db.schema
    .createIndex('magic_links_token_digest')
    .on('magic_links')
    .column('token_digest')
    .unique()
    .execute()

  await db.schema
    .createTable('customer_merges')
    .addColumn('id', 'integer', (column) => column.primaryKey().autoIncrement())
    .addColumn('anonymous_customer_id', 'integer', (column) =>
      column.notNull().references('customers.id'),
    )
    .addColumn('customer_id', 'integer', (column) => column.notNull().references('customers.id'))
    .addColumn('created_at', 'text', (column) => column.notNull())
    .execute()

  // One anonymous row folds forward exactly once, so a stale cookie has a
  // single answer however many times it comes back.
  await db.schema
    .createIndex('customer_merges_anonymous_customer_id')
    .on('customer_merges')
    .column('anonymous_customer_id')
    .unique()
    .execute()
}

export async function down(db: Kysely<unknown>): Promise<void> {
  await db.schema.dropTable('customer_merges').execute()
  await db.schema.dropTable('magic_links').execute()
  await db.schema.dropTable('admins').execute()
  await db.schema.dropTable('customers').execute()
  await db.schema.dropTable('sellers').execute()
}
