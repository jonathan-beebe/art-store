import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sql, type RawBuilder, type Selectable } from 'kysely'
import { fixtureId } from '../test/fixture-ids.ts'
import { IN_MEMORY_DATABASE, openDatabase, type AppDatabase } from './database.ts'
import { migrateToLatest } from './migrator.ts'
import { cents } from '../core/money.ts'
import type {
  AdminTable,
  CustomerMergeTable,
  CustomerTable,
  MagicLinkTable,
  SellerTable,
} from './schema.ts'
import type {
  Cart,
  CartItem,
  Conversation,
  CustomerBlock,
  FavoritesTable,
  Fulfillment,
  LedgerEntry,
  Listing,
  ListingEvent,
  ListingFaq,
  ListingRemoval,
  Message,
  Notification,
  Order,
  OrderItem,
  OutboxMessage,
  PageViewCountsTable,
  Payment,
  Payout,
  Refund,
} from './commerce-schema.ts'

/**
 * One row per table, typed against the row type it is meant to check: a
 * missing or renamed property fails `tsc`, and a `null` marks the columns this
 * test expects the migration to leave nullable.
 */
const sellersSample: Selectable<SellerTable> = {
  id: fixtureId('sel', 1),
  email: 'seller@example.com',
  name: null,
  shopName: null,
  emailVerifiedAt: null,
  createdAt: '2026-01-01T00:00:00.000Z',
}

const customersSample: Selectable<CustomerTable> = {
  id: fixtureId('cus', 1),
  email: null,
  name: null,
  emailVerifiedAt: null,
  createdAt: '2026-01-01T00:00:00.000Z',
}

const adminsSample: Selectable<AdminTable> = {
  id: fixtureId('adm', 1),
  email: 'admin@example.com',
  name: 'Admin',
  createdAt: '2026-01-01T00:00:00.000Z',
}

const magicLinksSample: Selectable<MagicLinkTable> = {
  id: fixtureId('mlk', 1),
  tokenDigest: 'digest',
  email: 'seller@example.com',
  actorType: 'seller',
  redirectTo: null,
  expiresAt: '2026-01-01T00:00:00.000Z',
  consumedAt: null,
  createdAt: '2026-01-01T00:00:00.000Z',
}

const customerMergesSample: Selectable<CustomerMergeTable> = {
  id: fixtureId('cmg', 1),
  anonymousCustomerId: fixtureId('cus', 1),
  customerId: fixtureId('cus', 2),
  createdAt: '2026-01-01T00:00:00.000Z',
}

const listingsSample: Listing = {
  id: fixtureId('lst', 1),
  sellerId: fixtureId('sel', 1),
  title: 'Untitled',
  slug: 'untitled',
  description: null,
  medium: null,
  dimensions: null,
  priceCents: cents(100),
  quantity: 1,
  status: 'draft',
  imagePath: null,
  createdAt: '2026-01-01T00:00:00.000Z',
  updatedAt: '2026-01-01T00:00:00.000Z',
}

const listingEventsSample: ListingEvent = {
  id: fixtureId('lev', 1),
  listingId: fixtureId('lst', 1),
  customerId: null,
  eventType: 'view',
  occurredAt: '2026-01-01T00:00:00.000Z',
}

const favoritesSample: Selectable<FavoritesTable> = {
  id: fixtureId('fav', 1),
  customerId: fixtureId('cus', 1),
  listingId: fixtureId('lst', 1),
  createdAt: '2026-01-01T00:00:00.000Z',
}

const listingRemovalsSample: ListingRemoval = {
  id: fixtureId('rmv', 1),
  listingId: fixtureId('lst', 1),
  adminId: fixtureId('adm', 1),
  kind: 'temporary',
  reason: 'reason',
  createdAt: '2026-01-01T00:00:00.000Z',
  liftedAt: null,
}

const customerBlocksSample: CustomerBlock = {
  id: fixtureId('blk', 1),
  customerId: fixtureId('cus', 1),
  adminId: fixtureId('adm', 1),
  reason: 'reason',
  createdAt: '2026-01-01T00:00:00.000Z',
  liftedAt: null,
}

const cartsSample: Cart = {
  id: fixtureId('crt', 1),
  customerId: fixtureId('cus', 1),
  createdAt: '2026-01-01T00:00:00.000Z',
}

const cartItemsSample: CartItem = {
  id: fixtureId('cti', 1),
  cartId: fixtureId('crt', 1),
  listingId: fixtureId('lst', 1),
  quantity: 1,
  createdAt: '2026-01-01T00:00:00.000Z',
}

const ordersSample: Order = {
  id: fixtureId('ord', 1),
  customerId: fixtureId('cus', 1),
  email: null,
  status: 'pending_verification',
  shippingName: 'Name',
  shippingLine1: 'Line 1',
  shippingLine2: null,
  shippingCity: 'City',
  shippingRegion: 'Region',
  shippingPostalCode: '00000',
  shippingCountry: 'US',
  subtotalCents: cents(100),
  totalCents: cents(100),
  refundedCents: cents(0),
  placedAt: '2026-01-01T00:00:00.000Z',
  finalizedAt: null,
  cancelledAt: null,
}

const orderItemsSample: OrderItem = {
  id: fixtureId('oit', 1),
  orderId: fixtureId('ord', 1),
  listingId: fixtureId('lst', 1),
  sellerId: fixtureId('sel', 1),
  title: 'Untitled',
  unitPriceCents: cents(100),
  quantity: 1,
  createdAt: '2026-01-01T00:00:00.000Z',
}

const paymentsSample: Payment = {
  id: fixtureId('pay', 1),
  orderId: fixtureId('ord', 1),
  status: 'approved',
  amountCents: cents(100),
  cardLastFour: '4242',
  declineReason: null,
  processedAt: '2026-01-01T00:00:00.000Z',
}

const refundsSample: Refund = {
  id: fixtureId('rfd', 1),
  orderId: fixtureId('ord', 1),
  fulfillmentId: fixtureId('ful', 1),
  paymentId: fixtureId('pay', 1),
  amountCents: cents(100),
  reason: 'Damaged in the kiln',
  issuedByType: 'seller',
  issuedById: fixtureId('sel', 1),
  createdAt: '2026-01-01T00:00:00.000Z',
}

const fulfillmentsSample: Fulfillment = {
  id: fixtureId('ful', 1),
  orderId: fixtureId('ord', 1),
  sellerId: fixtureId('sel', 1),
  status: 'awaiting_shipment',
  carrier: null,
  trackingNumber: null,
  subtotalCents: cents(100),
  feeCents: cents(10),
  netCents: cents(90),
  createdAt: '2026-01-01T00:00:00.000Z',
  shippedAt: null,
  deliveredAt: null,
}

const payoutsSample: Payout = {
  id: fixtureId('pyt', 1),
  sellerId: fixtureId('sel', 1),
  periodStart: '2026-01-01',
  periodEnd: '2026-01-07',
  amountCents: cents(100),
  paidAt: '2026-01-08T00:00:00.000Z',
}

const ledgerEntriesSample: LedgerEntry = {
  id: fixtureId('led', 1),
  sellerId: fixtureId('sel', 1),
  fulfillmentId: null,
  payoutId: null,
  entryType: 'held',
  amountCents: cents(100),
  occurredAt: '2026-01-01T00:00:00.000Z',
}

const notificationsSample: Notification = {
  id: fixtureId('ntf', 1),
  // A CHECK keeps exactly one of these three set; the column itself is
  // nullable on all three.
  sellerId: null,
  customerId: null,
  adminId: null,
  subject: 'Subject',
  body: 'Body',
  url: null,
  createdAt: '2026-01-01T00:00:00.000Z',
  readAt: null,
}

const outboxMessagesSample: OutboxMessage = {
  id: fixtureId('obx', 1),
  recipient: 'seller@example.com',
  subject: 'Subject',
  body: 'Body',
  url: null,
  createdAt: '2026-01-01T00:00:00.000Z',
  deliveredAt: null,
}

const pageViewCountsSample: Selectable<PageViewCountsTable> = {
  id: fixtureId('pvc', 1),
  site: 'shop',
  pathPattern: '/',
  day: '2026-01-01',
  count: 1,
}

const conversationsSample: Conversation = {
  id: fixtureId('cnv', 1),
  kind: 'admin_seller',
  // Every participant/subject column is nullable at the schema level; which
  // ones a row fills depends on `kind`.
  sellerId: null,
  customerId: null,
  adminId: null,
  listingId: null,
  fulfillmentId: null,
  createdAt: '2026-01-01T00:00:00.000Z',
  lastMessageAt: '2026-01-01T00:00:00.000Z',
}

const messagesSample: Message = {
  id: fixtureId('msg', 1),
  conversationId: fixtureId('cnv', 1),
  senderType: 'admin',
  senderId: 'adm_00000000000000000000000001',
  body: 'Body',
  sentAt: '2026-01-01T00:00:00.000Z',
  readAt: null,
}

const listingFaqsSample: ListingFaq = {
  id: fixtureId('faq', 1),
  listingId: fixtureId('lst', 1),
  question: 'Question?',
  answer: 'Answer.',
  sourceMessageId: null,
  publishedAt: '2026-01-01T00:00:00.000Z',
}

/** SQL table name (as the migrations create it) paired with a typed sample row. */
const TABLE_SAMPLES: ReadonlyArray<readonly [string, Record<string, unknown>]> = [
  ['sellers', sellersSample],
  ['customers', customersSample],
  ['admins', adminsSample],
  ['magic_links', magicLinksSample],
  ['customer_merges', customerMergesSample],
  ['listings', listingsSample],
  ['listing_events', listingEventsSample],
  ['favorites', favoritesSample],
  ['listing_removals', listingRemovalsSample],
  ['customer_blocks', customerBlocksSample],
  ['carts', cartsSample],
  ['cart_items', cartItemsSample],
  ['orders', ordersSample],
  ['order_items', orderItemsSample],
  ['payments', paymentsSample],
  ['fulfillments', fulfillmentsSample],
  ['refunds', refundsSample],
  ['payouts', payoutsSample],
  ['ledger_entries', ledgerEntriesSample],
  ['notifications', notificationsSample],
  ['outbox_messages', outboxMessagesSample],
  ['page_view_counts', pageViewCountsSample],
  ['conversations', conversationsSample],
  ['messages', messagesSample],
  ['listing_faqs', listingFaqsSample],
]

type PragmaColumn = { name: string; notNull: number }

function snakeToCamel(column: string): string {
  return column.replace(/_([a-z0-9])/g, (_match, char: string) => char.toUpperCase())
}

async function columnsOf(db: AppDatabase, table: string): Promise<readonly PragmaColumn[]> {
  // `notnull` is a SQLite operator keyword, so it needs quoting to read as a column reference.
  const { rows } = await sql<PragmaColumn>`select name, "notnull" as not_null from pragma_table_info(${table})`.execute(
    db,
  )
  return rows
}

test('every table the migrations create has an entry in TABLE_SAMPLES', async () => {
  const db = openDatabase(IN_MEMORY_DATABASE)
  await migrateToLatest(db)

  const { rows } = await sql<{
    name: string
  }>`select name from sqlite_master where type = 'table' and name not like 'sqlite_%' and name not like 'kysely_%' order by name`.execute(
    db,
  )

  assert.deepEqual(
    rows.map((row) => row.name).sort(),
    TABLE_SAMPLES.map(([table]) => table).sort(),
  )
  await db.destroy()
})

for (const [table, sample] of TABLE_SAMPLES) {
  test(`${table} columns match its row type`, async () => {
    const db = openDatabase(IN_MEMORY_DATABASE)
    await migrateToLatest(db)

    const columns = await columnsOf(db, table)

    assert.deepEqual(
      columns.map((column) => snakeToCamel(column.name)).sort(),
      Object.keys(sample).sort(),
      `${table}: column names`,
    )

    for (const column of columns) {
      const property = snakeToCamel(column.name)
      const expectedNullable = sample[property] === null
      const actualNullable = column.notNull === 0

      assert.equal(
        actualNullable,
        expectedNullable,
        `${table}.${column.name}: expected nullable=${expectedNullable}, migration says nullable=${actualNullable}`,
      )
    }

    await db.destroy()
  })
}

/**
 * The rows every insert below hangs off. Ids are written by hand rather than
 * minted: this test is about the schema, and a fixed id keeps the statements
 * readable.
 */
const SELLER_ID = fixtureId('sel', 1)
const CUSTOMER_ID = fixtureId('cus', 1)
const ADMIN_ID = fixtureId('adm', 1)
const LISTING_ID = fixtureId('lst', 1)
const ORDER_ID = fixtureId('ord', 1)
const FULFILLMENT_ID = fixtureId('ful', 1)
const PAYMENT_ID = fixtureId('pay', 9)
const CONVERSATION_ID = fixtureId('cnv', 1)

/**
 * One parent row per foreign key an invalid insert below needs to reach its
 * own CHECK constraint instead of failing on the FK first.
 */
async function seedParentRows(db: AppDatabase): Promise<void> {
  await sql`insert into sellers (id, email, created_at) values (${SELLER_ID}, 'seller@example.com', '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
  await sql`insert into customers (id, created_at) values (${CUSTOMER_ID}, '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
  await sql`insert into admins (id, email, name, created_at) values (${ADMIN_ID}, 'admin@example.com', 'Admin', '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
  await sql`insert into listings (id, seller_id, title, slug, price_cents, created_at, updated_at) values (${LISTING_ID}, ${SELLER_ID}, 'Untitled', 'untitled', 100, '2026-01-01T00:00:00.000Z', '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
  await sql`insert into orders (id, customer_id, status, shipping_name, shipping_line1, shipping_city, shipping_region, shipping_postal_code, shipping_country, subtotal_cents, total_cents, placed_at) values (${ORDER_ID}, ${CUSTOMER_ID}, 'pending_verification', 'Name', 'Line 1', 'City', 'Region', '00000', 'US', 100, 100, '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
  await sql`insert into fulfillments (id, order_id, seller_id, subtotal_cents, fee_cents, net_cents, created_at) values (${FULFILLMENT_ID}, ${ORDER_ID}, ${SELLER_ID}, 100, 10, 90, '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
  await sql`insert into payments (id, order_id, status, amount_cents, card_last_four, processed_at) values (${PAYMENT_ID}, ${ORDER_ID}, 'approved', 100, '4242', '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
  await sql`insert into conversations (id, kind, seller_id, admin_id, created_at, last_message_at) values (${CONVERSATION_ID}, 'admin_seller', ${SELLER_ID}, ${ADMIN_ID}, '2026-01-01T00:00:00.000Z', '2026-01-01T00:00:00.000Z')`.execute(
    db,
  )
}

/** One out-of-set insert per column the migrations now constrain with a CHECK. */
const INVALID_STATUS_INSERTS: ReadonlyArray<readonly [string, RawBuilder<unknown>]> = [
  [
    'magic_links.actor_type',
    sql`insert into magic_links (id, token_digest, email, actor_type, expires_at, created_at) values (${fixtureId('mlk', 1)}, 'digest', 'seller@example.com', 'not_an_actor', '2026-01-01T00:00:00.000Z', '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'listings.status',
    sql`insert into listings (id, seller_id, title, slug, price_cents, status, created_at, updated_at) values (${fixtureId('lst', 2)}, ${SELLER_ID}, 'Untitled', 'untitled-2', 100, 'not_a_status', '2026-01-01T00:00:00.000Z', '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'listing_events.event_type',
    sql`insert into listing_events (id, listing_id, event_type, occurred_at) values (${fixtureId('lev', 1)}, ${LISTING_ID}, 'not_an_event', '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'listing_removals.kind',
    sql`insert into listing_removals (id, listing_id, admin_id, kind, reason, created_at) values (${fixtureId('rmv', 1)}, ${LISTING_ID}, ${ADMIN_ID}, 'not_a_kind', 'reason', '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'orders.status',
    sql`insert into orders (id, customer_id, status, shipping_name, shipping_line1, shipping_city, shipping_region, shipping_postal_code, shipping_country, subtotal_cents, total_cents, placed_at) values (${fixtureId('ord', 2)}, ${CUSTOMER_ID}, 'not_a_status', 'Name', 'Line 1', 'City', 'Region', '00000', 'US', 100, 100, '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'payments.status',
    sql`insert into payments (id, order_id, status, amount_cents, card_last_four, processed_at) values (${fixtureId('pay', 1)}, ${ORDER_ID}, 'not_a_status', 100, '4242', '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'payments.decline_reason',
    sql`insert into payments (id, order_id, status, amount_cents, card_last_four, decline_reason, processed_at) values (${fixtureId('pay', 2)}, ${ORDER_ID}, 'declined', 100, '4242', 'not_a_reason', '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'fulfillments.status',
    sql`insert into fulfillments (id, order_id, seller_id, status, subtotal_cents, fee_cents, net_cents, created_at) values (${fixtureId('ful', 2)}, ${ORDER_ID}, ${SELLER_ID}, 'not_a_status', 100, 10, 90, '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'refunds.issued_by_type',
    sql`insert into refunds (id, order_id, fulfillment_id, payment_id, amount_cents, reason, issued_by_type, issued_by_id, created_at) values (${fixtureId('rfd', 1)}, ${ORDER_ID}, ${FULFILLMENT_ID}, ${PAYMENT_ID}, 100, 'Reason', 'not_an_issuer', ${SELLER_ID}, '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'ledger_entries.entry_type',
    sql`insert into ledger_entries (id, seller_id, entry_type, amount_cents, occurred_at) values (${fixtureId('led', 1)}, ${SELLER_ID}, 'not_an_entry_type', 100, '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'conversations.kind',
    sql`insert into conversations (id, kind, created_at, last_message_at) values (${fixtureId('cnv', 2)}, 'not_a_kind', '2026-01-01T00:00:00.000Z', '2026-01-01T00:00:00.000Z')`,
  ],
  [
    'messages.sender_type',
    sql`insert into messages (id, conversation_id, sender_type, sender_id, body, sent_at) values (${fixtureId('msg', 1)}, ${CONVERSATION_ID}, 'not_an_actor', ${SELLER_ID}, 'Body', '2026-01-01T00:00:00.000Z')`,
  ],
]

for (const [column, statement] of INVALID_STATUS_INSERTS) {
  test(`${column} refuses a value outside its union`, async () => {
    const db = openDatabase(IN_MEMORY_DATABASE)
    await migrateToLatest(db)
    await seedParentRows(db)

    await assert.rejects(() => statement.execute(db), /CHECK constraint failed/)

    await db.destroy()
  })
}
