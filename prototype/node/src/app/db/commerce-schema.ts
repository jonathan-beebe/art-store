import type { Generated, Selectable } from 'kysely'
import type { ActorType } from '../core/auth/actor-type.ts'
import type { LedgerEntryType } from '../core/escrow/ledger-entry-type.ts'
import type { ListingEventType } from '../core/listings/listing-event-type.ts'
import type { ListingStatus } from '../core/listings/listing-status.ts'
import type { ConversationKind } from '../core/messaging/conversation-kind.ts'
import type { RemovalKind } from '../core/moderation/listing-removal.ts'
import type { FulfillmentStatus } from '../core/orders/fulfillment-status.ts'
import type { OrderStatus } from '../core/orders/order-status.ts'
import type { DeclineReason } from '../core/payments/decline-reason.ts'
import type { PaymentStatus } from '../core/payments/payment-status.ts'
import type { Timestamp } from './timestamp.ts'

/** A calendar day, `YYYY-MM-DD`. Sorts and compares as text. */
export type Day = string

export type ListingsTable = {
  id: Generated<number>
  sellerId: number
  title: string
  slug: string
  description: string | null
  medium: string | null
  dimensions: string | null
  priceCents: number
  quantity: number
  status: ListingStatus
  /** Relative to `public/`; null while the listing shows a generated placeholder. */
  imagePath: string | null
  createdAt: Timestamp
  updatedAt: Timestamp
}

export type ListingEventsTable = {
  id: Generated<number>
  listingId: number
  customerId: number | null
  eventType: ListingEventType
  occurredAt: Timestamp
}

export type FavoritesTable = {
  id: Generated<number>
  customerId: number
  listingId: number
  createdAt: Timestamp
}

export type ListingRemovalsTable = {
  id: Generated<number>
  listingId: number
  adminId: number
  kind: RemovalKind
  reason: string
  createdAt: Timestamp
  liftedAt: Timestamp | null
}

export type CustomerBlocksTable = {
  id: Generated<number>
  customerId: number
  adminId: number
  reason: string
  createdAt: Timestamp
  liftedAt: Timestamp | null
}

export type CartsTable = {
  id: Generated<number>
  customerId: number
  createdAt: Timestamp
}

export type CartItemsTable = {
  id: Generated<number>
  cartId: number
  listingId: number
  quantity: number
}

export type OrdersTable = {
  id: Generated<number>
  customerId: number
  email: string | null
  status: OrderStatus
  shippingName: string
  shippingLine1: string
  shippingLine2: string | null
  shippingCity: string
  shippingRegion: string
  shippingPostalCode: string
  shippingCountry: string
  subtotalCents: number
  totalCents: number
  placedAt: Timestamp
  finalizedAt: Timestamp | null
  cancelledAt: Timestamp | null
}

export type OrderItemsTable = {
  id: Generated<number>
  orderId: number
  listingId: number
  sellerId: number
  /** Title and price as they were at checkout, so an edited listing cannot rewrite an order. */
  title: string
  unitPriceCents: number
  quantity: number
}

export type PaymentsTable = {
  id: Generated<number>
  orderId: number
  status: PaymentStatus
  amountCents: number
  cardLastFour: string
  declineReason: DeclineReason | null
  processedAt: Timestamp
}

export type FulfillmentsTable = {
  id: Generated<number>
  orderId: number
  sellerId: number
  status: FulfillmentStatus
  carrier: string | null
  trackingNumber: string | null
  subtotalCents: number
  /** Priced once at placement; every later step moves the stored `netCents`. */
  feeCents: number
  netCents: number
  shippedAt: Timestamp | null
  deliveredAt: Timestamp | null
}

export type PayoutsTable = {
  id: Generated<number>
  sellerId: number
  periodStart: Day
  periodEnd: Day
  amountCents: number
  paidAt: Timestamp
}

export type LedgerEntriesTable = {
  id: Generated<number>
  sellerId: number
  fulfillmentId: number | null
  payoutId: number | null
  entryType: LedgerEntryType
  /** Signed: `held` and `released` are positive, `paid_out` is negative. */
  amountCents: number
  occurredAt: Timestamp
}

export type NotificationsTable = {
  id: Generated<number>
  sellerId: number | null
  customerId: number | null
  adminId: number | null
  subject: string
  body: string
  url: string | null
  createdAt: Timestamp
  readAt: Timestamp | null
}

export type PageViewCountsTable = {
  id: Generated<number>
  site: string
  /** The route's pattern (`/art/:slug`), not the concrete URL. */
  pathPattern: string
  day: Day
  count: number
}

/**
 * `kind` decides which two participant columns are filled and which subject
 * column, if any, names what the thread is about.
 */
export type ConversationsTable = {
  id: Generated<number>
  kind: ConversationKind
  sellerId: number | null
  customerId: number | null
  adminId: number | null
  listingId: number | null
  fulfillmentId: number | null
  createdAt: Timestamp
  lastMessageAt: Timestamp
}

/** `readAt` is the other participant's marker: a thread has exactly two sides. */
export type MessagesTable = {
  id: Generated<number>
  conversationId: number
  senderType: ActorType
  senderId: number
  body: string
  sentAt: Timestamp
  readAt: Timestamp | null
}

/** A row exists only while the entry is published; unpublishing deletes it. */
export type ListingFaqsTable = {
  id: Generated<number>
  listingId: number
  question: string
  answer: string
  sourceMessageId: number | null
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
  fulfillments: FulfillmentsTable
  payouts: PayoutsTable
  ledgerEntries: LedgerEntriesTable
  notifications: NotificationsTable
  pageViewCounts: PageViewCountsTable
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
export type Fulfillment = Selectable<FulfillmentsTable>
export type Payout = Selectable<PayoutsTable>
export type LedgerEntry = Selectable<LedgerEntriesTable>
export type Notification = Selectable<NotificationsTable>
export type Conversation = Selectable<ConversationsTable>
export type Message = Selectable<MessagesTable>
export type ListingFaq = Selectable<ListingFaqsTable>
