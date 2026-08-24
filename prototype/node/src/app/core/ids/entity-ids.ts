/**
 * One prefix per table, shared with the PHP and Rails prototypes so the same
 * row reads the same in all three logs. `docs/alignment.md` §1 is the table
 * this file spells; a prefix here is never reused for another table.
 *
 * `sessions` and `transactions` name no table: they prefix the `sid` cookie
 * and the `txn_id` a unit of work logs.
 */
import type { PrefixedId } from './prefixed-id.ts'

export const ID_PREFIXES = {
  admins: 'adm',
  sellers: 'sel',
  customers: 'cus',
  customer_merges: 'cmg',
  customer_blocks: 'blk',
  magic_links: 'mlk',
  listings: 'lst',
  listing_events: 'lev',
  listing_faqs: 'faq',
  listing_removals: 'rmv',
  carts: 'crt',
  cart_items: 'cti',
  favorites: 'fav',
  orders: 'ord',
  order_items: 'oit',
  payments: 'pay',
  fulfillments: 'ful',
  ledger_entries: 'led',
  payouts: 'pyt',
  conversations: 'cnv',
  messages: 'msg',
  notifications: 'ntf',
  outbox_messages: 'obx',
  page_view_counts: 'pvc',
  sessions: 'ses',
  transactions: 'txn',
} as const

/** Every prefix the application is allowed to mint an id under. */
export type IdPrefix = (typeof ID_PREFIXES)[keyof typeof ID_PREFIXES]

export type AdminId = PrefixedId<typeof ID_PREFIXES.admins>
export type SellerId = PrefixedId<typeof ID_PREFIXES.sellers>
export type CustomerId = PrefixedId<typeof ID_PREFIXES.customers>
export type CustomerMergeId = PrefixedId<typeof ID_PREFIXES.customer_merges>
export type CustomerBlockId = PrefixedId<typeof ID_PREFIXES.customer_blocks>
export type MagicLinkId = PrefixedId<typeof ID_PREFIXES.magic_links>
export type ListingId = PrefixedId<typeof ID_PREFIXES.listings>
export type ListingEventId = PrefixedId<typeof ID_PREFIXES.listing_events>
export type ListingFaqId = PrefixedId<typeof ID_PREFIXES.listing_faqs>
export type ListingRemovalId = PrefixedId<typeof ID_PREFIXES.listing_removals>
export type CartId = PrefixedId<typeof ID_PREFIXES.carts>
export type CartItemId = PrefixedId<typeof ID_PREFIXES.cart_items>
export type FavoriteId = PrefixedId<typeof ID_PREFIXES.favorites>
export type OrderId = PrefixedId<typeof ID_PREFIXES.orders>
export type OrderItemId = PrefixedId<typeof ID_PREFIXES.order_items>
export type PaymentId = PrefixedId<typeof ID_PREFIXES.payments>
export type FulfillmentId = PrefixedId<typeof ID_PREFIXES.fulfillments>
export type LedgerEntryId = PrefixedId<typeof ID_PREFIXES.ledger_entries>
export type PayoutId = PrefixedId<typeof ID_PREFIXES.payouts>
export type ConversationId = PrefixedId<typeof ID_PREFIXES.conversations>
export type MessageId = PrefixedId<typeof ID_PREFIXES.messages>
export type NotificationId = PrefixedId<typeof ID_PREFIXES.notifications>
export type OutboxMessageId = PrefixedId<typeof ID_PREFIXES.outbox_messages>
export type PageViewCountId = PrefixedId<typeof ID_PREFIXES.page_view_counts>

/** The `sid` cookie's value: one browser, across sign-ins and sign-outs. */
export type SessionId = PrefixedId<typeof ID_PREFIXES.sessions>

/** The `txn_id` every log line written inside one unit of work carries. */
export type TransactionId = PrefixedId<typeof ID_PREFIXES.transactions>

/** Whoever a message or an audit row names as the actor behind it. */
export type ActorId = SellerId | CustomerId | AdminId
