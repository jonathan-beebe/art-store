import type { ColumnType, Generated, Selectable } from 'kysely'
import type { PageViewSite } from '../core/analytics/page-view-site.ts'
import type { ActorType } from '../core/auth/actor-type.ts'
import type { LedgerEntryType } from '../core/escrow/ledger-entry-type.ts'
import type {
  ActorId,
  AdminId,
  CartId,
  CartItemId,
  ConversationId,
  CustomerBlockId,
  CustomerId,
  FavoriteId,
  FulfillmentId,
  LedgerEntryId,
  ListingEventId,
  ListingFaqId,
  ListingId,
  ListingRemovalId,
  MessageId,
  NotificationId,
  OrderId,
  OrderItemId,
  OutboxMessageId,
  PageViewCountId,
  PaymentId,
  PayoutId,
  RateLimitWindowId,
  RefundId,
  SellerId,
} from '../core/ids/entity-ids.ts'
import type { ListingEventType } from '../core/listings/listing-event-type.ts'
import type { ListingStatus } from '../core/listings/listing-status.ts'
import type { ConversationKind } from '../core/messaging/conversation-kind.ts'
import type { RemovalKind } from '../core/moderation/listing-removal.ts'
import type { FulfillmentStatus } from '../core/orders/fulfillment-status.ts'
import type { OrderStatus } from '../core/orders/order-status.ts'
import type { RefundIssuerType } from '../core/orders/refund.ts'
import type { DeclineReason } from '../core/payments/decline-reason.ts'
import type { PaymentStatus } from '../core/payments/payment-status.ts'
import type { RateLimitName } from '../core/rate-limit/rate-limit-name.ts'
import type { Cents } from '../core/money.ts'
import type { Timestamp } from './timestamp.ts'

/** A calendar day, `YYYY-MM-DD`. Sorts and compares as text. */
export type Day = string

/** A money column: it reads back as `Cents`, and a write hands it the plain
 * integer the driver stores. */
type MoneyColumn = ColumnType<Cents, number, number>

export type ListingsTable = {
  id: ListingId
  sellerId: SellerId
  title: string
  slug: string
  description: string | null
  medium: string | null
  dimensions: string | null
  priceCents: MoneyColumn
  /** Defaults to 1 in the migration. */
  quantity: Generated<number>
  /** Defaults to `'draft'` in the migration. */
  status: Generated<ListingStatus>
  /** Relative to `public/`; null while the listing shows a generated placeholder. */
  imagePath: string | null
  createdAt: Timestamp
  updatedAt: Timestamp
}

export type ListingEventsTable = {
  id: ListingEventId
  listingId: ListingId
  customerId: CustomerId | null
  eventType: ListingEventType
  occurredAt: Timestamp
}

export type FavoritesTable = {
  id: FavoriteId
  customerId: CustomerId
  listingId: ListingId
  createdAt: Timestamp
}

export type ListingRemovalsTable = {
  id: ListingRemovalId
  listingId: ListingId
  adminId: AdminId
  kind: RemovalKind
  reason: string
  createdAt: Timestamp
  liftedAt: Timestamp | null
}

export type CustomerBlocksTable = {
  id: CustomerBlockId
  customerId: CustomerId
  adminId: AdminId
  reason: string
  createdAt: Timestamp
  liftedAt: Timestamp | null
}

export type CartsTable = {
  id: CartId
  customerId: CustomerId
  createdAt: Timestamp
}

export type CartItemsTable = {
  id: CartItemId
  cartId: CartId
  listingId: ListingId
  quantity: number
  createdAt: Timestamp
}

export type OrdersTable = {
  id: OrderId
  customerId: CustomerId
  email: string | null
  status: OrderStatus
  shippingName: string
  shippingLine1: string
  shippingLine2: string | null
  shippingCity: string
  shippingRegion: string
  shippingPostalCode: string
  shippingCountry: string
  subtotalCents: MoneyColumn
  totalCents: MoneyColumn
  /** The `refunds` rows against this order, summed. Defaults to 0 in the migration. */
  refundedCents: Generated<Cents>
  placedAt: Timestamp
  finalizedAt: Timestamp | null
  cancelledAt: Timestamp | null
}

export type OrderItemsTable = {
  id: OrderItemId
  orderId: OrderId
  listingId: ListingId
  sellerId: SellerId
  /** Title and price as they were at checkout, so an edited listing cannot rewrite an order. */
  title: string
  unitPriceCents: MoneyColumn
  quantity: number
  createdAt: Timestamp
}

export type PaymentsTable = {
  id: PaymentId
  orderId: OrderId
  status: PaymentStatus
  amountCents: MoneyColumn
  cardLastFour: string
  declineReason: DeclineReason | null
  processedAt: Timestamp
}

/**
 * One reversal: the whole subtotal of one fulfillment, handed back to the
 * customer by the seller who declined it or the admin who refunded it.
 */
export type RefundsTable = {
  id: RefundId
  orderId: OrderId
  fulfillmentId: FulfillmentId
  /** The approved charge the money came in on. */
  paymentId: PaymentId
  amountCents: MoneyColumn
  reason: string
  issuedByType: RefundIssuerType
  /** A `sel_` id when the seller declined, an `adm_` id when the platform refunded. */
  issuedById: SellerId | AdminId
  createdAt: Timestamp
}

export type FulfillmentsTable = {
  id: FulfillmentId
  orderId: OrderId
  sellerId: SellerId
  /** Defaults to `'awaiting_shipment'` in the migration. */
  status: Generated<FulfillmentStatus>
  carrier: string | null
  trackingNumber: string | null
  subtotalCents: MoneyColumn
  /** Priced once at placement; every later step moves the stored `netCents`. */
  feeCents: MoneyColumn
  netCents: MoneyColumn
  createdAt: Timestamp
  shippedAt: Timestamp | null
  deliveredAt: Timestamp | null
}

export type PayoutsTable = {
  id: PayoutId
  sellerId: SellerId
  periodStart: Day
  periodEnd: Day
  amountCents: MoneyColumn
  paidAt: Timestamp
}

export type LedgerEntriesTable = {
  id: LedgerEntryId
  sellerId: SellerId
  fulfillmentId: FulfillmentId | null
  payoutId: PayoutId | null
  entryType: LedgerEntryType
  /** Signed: `held` and `released` are positive, `paid_out` and `refunded` are negative. */
  amountCents: MoneyColumn
  occurredAt: Timestamp
}

export type NotificationsTable = {
  id: NotificationId
  sellerId: SellerId | null
  customerId: CustomerId | null
  adminId: AdminId | null
  subject: string
  body: string
  url: string | null
  createdAt: Timestamp
  readAt: Timestamp | null
}

/**
 * A message waiting to leave the application. Written inside the transaction
 * that caused it; `deliveredAt` is null exactly while the message is pending,
 * and the drain stamps it once the message has been written out.
 */
export type OutboxMessagesTable = {
  id: OutboxMessageId
  /** The email address the message is addressed to. */
  recipient: string
  subject: string
  body: string
  url: string | null
  createdAt: Timestamp
  deliveredAt: Timestamp | null
}

export type PageViewCountsTable = {
  id: PageViewCountId
  site: PageViewSite
  /** The route's pattern (`/art/:slug`), not the concrete URL. */
  pathPattern: string
  day: Day
  /** Defaults to 0 in the migration; the upsert always sets it explicitly. */
  count: Generated<number>
}

/**
 * One fixed-window rate-limit counter, `docs/alignment.md` §3: `name` is one
 * of `RATE_LIMIT_NAMES`, `key` is whatever the limit is keyed by (an email
 * address, a client ip, or an actor id — never the raw value in a log line),
 * and `windowStart` plus the limit's own window length decide when a fresh
 * row starts counting again.
 */
export type RateLimitWindowsTable = {
  id: RateLimitWindowId
  name: RateLimitName
  key: string
  windowStart: Timestamp
  /** Defaults to 0 in the migration; the upsert always sets it explicitly. */
  count: Generated<number>
}

/**
 * `kind` decides which two participant columns are filled and which subject
 * column, if any, names what the thread is about.
 */
export type ConversationsTable = {
  id: ConversationId
  kind: ConversationKind
  /** `subjectKey(subject)` (`core/messaging/conversation-subject.ts`), unique. */
  subjectKey: string
  sellerId: SellerId | null
  customerId: CustomerId | null
  adminId: AdminId | null
  listingId: ListingId | null
  fulfillmentId: FulfillmentId | null
  createdAt: Timestamp
  lastMessageAt: Timestamp
}

/** `readAt` is the other participant's marker: a thread has exactly two sides. */
export type MessagesTable = {
  id: MessageId
  conversationId: ConversationId
  senderType: ActorType
  senderId: ActorId
  body: string
  sentAt: Timestamp
  readAt: Timestamp | null
}

/** A row exists only while the entry is published; unpublishing deletes it. */
export type ListingFaqsTable = {
  id: ListingFaqId
  listingId: ListingId
  question: string
  answer: string
  sourceMessageId: MessageId | null
  publishedAt: Timestamp
}

/** The commerce half of the database. Identity tables arrive with their own ticket. */
export type CommerceTables = {
  listings: ListingsTable
  listingEvents: ListingEventsTable
  favorites: FavoritesTable
  listingRemovals: ListingRemovalsTable
  customerBlocks: CustomerBlocksTable
  carts: CartsTable
  cartItems: CartItemsTable
  orders: OrdersTable
  orderItems: OrderItemsTable
  payments: PaymentsTable
  refunds: RefundsTable
  fulfillments: FulfillmentsTable
  payouts: PayoutsTable
  ledgerEntries: LedgerEntriesTable
  notifications: NotificationsTable
  outboxMessages: OutboxMessagesTable
  pageViewCounts: PageViewCountsTable
  rateLimitWindows: RateLimitWindowsTable
  conversations: ConversationsTable
  messages: MessagesTable
  listingFaqs: ListingFaqsTable
}

/** Rows as they come back from a select — what actions hand to routes. */
export type Listing = Selectable<ListingsTable>
export type ListingEvent = Selectable<ListingEventsTable>
export type ListingRemoval = Selectable<ListingRemovalsTable>
export type CustomerBlock = Selectable<CustomerBlocksTable>
export type Cart = Selectable<CartsTable>
export type CartItem = Selectable<CartItemsTable>
export type Order = Selectable<OrdersTable>
export type OrderItem = Selectable<OrderItemsTable>
export type Payment = Selectable<PaymentsTable>
export type Refund = Selectable<RefundsTable>
export type Fulfillment = Selectable<FulfillmentsTable>
export type Payout = Selectable<PayoutsTable>
export type LedgerEntry = Selectable<LedgerEntriesTable>
export type Notification = Selectable<NotificationsTable>
export type OutboxMessage = Selectable<OutboxMessagesTable>
export type Conversation = Selectable<ConversationsTable>
export type Message = Selectable<MessagesTable>
export type ListingFaq = Selectable<ListingFaqsTable>
