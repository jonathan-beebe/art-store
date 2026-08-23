# Data model

Generated from `src/db/schema.rb` (version `2026_08_23_000105`). Active
Storage's own tables (`active_storage_attachments`, `active_storage_blobs`,
`active_storage_variant_records`) are omitted — nothing in the domain reads
or writes them directly; `Listing#image_url` falls back to a generated
placeholder when no blob is attached. `solid_cable_messages` is omitted the
same way — Solid Cable owns it, and it holds the broadcast queue rather than
any part of the domain.

Question: what tables exist, what does each row mean, and how do they
connect?

```mermaid
erDiagram
    sellers {
        id id PK
        string email UK
        string name
        string shop_name
        timestamp email_verified_at
    }
    customers {
        id id PK
        string email UK "nullable, anonymous rows have none"
        string name
        timestamp email_verified_at
    }
    customer_merges {
        id id PK
        id anonymous_customer_id FK "UK, -> customers"
        id customer_id FK "-> customers, the verified survivor"
    }
    listings {
        id id PK
        id seller_id FK
        string title
        string slug UK
        integer price_cents
        integer quantity "default 1"
        string status "draft|for_sale|sold|archived"
        string medium
        string dimensions
        text description
    }
    listing_events {
        id id PK
        id listing_id FK
        id customer_id FK "nullable"
        string event_type "view|favorite|unfavorite|cart_add"
        timestamp occurred_at
    }
    favorites {
        id id PK
        id customer_id FK
        id listing_id FK
    }
    carts {
        id id PK
        id customer_id FK "not unique - a merge can leave two"
    }
    cart_items {
        id id PK
        id cart_id FK
        id listing_id FK
        integer quantity
    }
    orders {
        id id PK
        id customer_id FK
        string email
        string status
        string shipping_name
        string shipping_line1
        string shipping_line2 "nullable"
        string shipping_city
        string shipping_region
        string shipping_postal_code
        string shipping_country
        integer subtotal_cents
        integer total_cents
        timestamp placed_at
        timestamp finalized_at "nullable, set on paid"
    }
    order_items {
        id id PK
        id order_id FK
        id listing_id FK
        id seller_id FK
        string title "snapshot"
        integer unit_price_cents "snapshot"
        integer quantity
    }
    payments {
        id id PK
        id order_id FK "one row per attempt"
        string status "approved|declined"
        integer amount_cents
        string card_last_four
        string decline_reason "nullable"
        timestamp processed_at
    }
    fulfillments {
        id id PK
        id order_id FK "UK with seller_id"
        id seller_id FK
        string status "awaiting_shipment|shipped|delivered"
        string carrier "nullable"
        string tracking_number "nullable"
        integer subtotal_cents
        integer fee_cents
        integer net_cents
        timestamp shipped_at "nullable"
        timestamp delivered_at "nullable"
    }
    payouts {
        id id PK
        id seller_id FK "UK with period_start"
        date period_start
        date period_end
        integer amount_cents
        timestamp paid_at
    }
    ledger_entries {
        id id PK
        id seller_id FK
        id fulfillment_id FK "nullable"
        id payout_id FK "nullable"
        string entry_type "held|released|paid_out"
        integer amount_cents "signed"
        timestamp occurred_at
    }
    notifications {
        id id PK
        string recipient_type "Seller|Customer|Admin"
        id recipient_id "polymorphic, no FK"
        string subject
        text body
        string url "nullable"
        timestamp read_at "nullable"
    }
    admins {
        id id PK
        string email UK
        string name
        timestamp email_verified_at
    }
    conversations {
        id id PK
        string kind "admin_seller|admin_customer|fulfillment|listing_question"
        id seller_id FK "nullable, filled by the kind"
        id customer_id FK "nullable, filled by the kind"
        id admin_id FK "nullable, filled by the kind"
        string subject_type "nullable, Listing|Fulfillment"
        id subject_id "polymorphic, no FK"
        timestamp last_message_at
    }
    messages {
        id id PK
        id conversation_id FK
        string sender_type "Seller|Customer|Admin"
        id sender_id "polymorphic, no FK"
        text body
        timestamp read_at "nullable, read by the side that did not send it"
    }
    listing_faqs {
        id id PK
        id listing_id FK
        text question
        text answer
        id source_message_id FK "nullable, -> messages"
        timestamp published_at "the row exists only while published"
    }
    magic_links {
        id id PK
        string token_digest UK
        string email
        string actor_type "seller|customer|admin"
        string redirect_to "nullable"
        timestamp expires_at
        timestamp consumed_at "nullable"
    }

    sellers ||--o{ listings : owns
    sellers ||--o{ order_items : sold_via
    sellers ||--o{ fulfillments : ships
    sellers ||--o{ ledger_entries : entries
    sellers ||--o{ payouts : receives
    sellers ||--o{ notifications : receives
    customers ||--o{ listing_events : records
    customers ||--o{ favorites : has
    customers ||--o{ carts : has
    customers ||--o{ orders : places
    customers ||--o{ notifications : receives
    customers ||--o{ customer_merges : "merged from (anonymous)"
    customers ||--o{ customer_merges : "merged into (verified)"
    listings ||--o{ listing_events : has
    listings ||--o{ favorites : favorited_in
    listings ||--o{ cart_items : held_in
    listings ||--o{ order_items : sold_as
    carts ||--o{ cart_items : contains
    orders ||--o{ order_items : contains
    orders ||--o{ payments : attempts
    orders ||--o{ fulfillments : split_by_seller
    fulfillments ||--o{ ledger_entries : produces
    payouts ||--o{ ledger_entries : settles
    admins ||--o{ notifications : receives
    admins ||--o{ conversations : "admin side"
    sellers ||--o{ conversations : "seller side"
    customers ||--o{ conversations : "customer side"
    listings ||--o{ conversations : "subject of a listing_question"
    fulfillments ||--o{ conversations : "subject of a fulfillment thread"
    conversations ||--o{ messages : holds
    listings ||--o{ listing_faqs : publishes
    messages ||--o| listing_faqs : "answer an entry came from"
```

Caveats:

- `magic_links` has no foreign key to `sellers` or `customers` — it matches by
  `email` string plus `actor_type`, so it is drawn without a relationship line
  above. A seller, a customer and an admin can share an email address; each
  gets its own row in its own table.
- `notifications` addresses a polymorphic `recipient` (`recipient_type` is
  `Seller`, `Customer` or `Admin`), so the table carries no foreign key to any
  of them. An anonymous-customer merge re-points rows by `recipient_id` the
  same way it re-points `favorites` or `orders`.
- `conversations` fills two of its three participant columns and leaves the
  third null; which two is `kind`, and `Conversation::KINDS` is the one place
  that says so. `subject_type`/`subject_id` are polymorphic and null for the
  two support kinds. The indexes are one per participant column paired with
  `last_message_at` (the inbox's order) plus
  `(kind, subject_type, subject_id)` (find-or-open).
- `messages.read_at` is one column for both sides. Every kind has exactly two
  participants, so the reader of a message is always the participant who did
  not send it, and `Message.unread_for(reader)` is the single definition of
  unread.
- `listing_faqs` rows exist only while published: `published_at` is
  `null: false` and unpublishing deletes the row, so the storefront reads the
  table with no predicate of its own. `source_message_id` is nullable — an
  entry outlives the answer it was lifted from, and an entry can be written
  from scratch.
- `payments` is one row per charge attempt, not one row per order — a
  declined card followed by a retry leaves two rows. The order's current
  payment is the latest one (`order.payments.order(:id).last`).
- `ledger_entries.amount_cents` is signed: `held` and `released` are
  positive, `paid_out` is negative. See `docs/escrow.md`.
- `carts.customer_id` is not unique — `Customer#absorb` can
  re-point a second cart onto a customer that already has one
  (`Customer#current_cart` picks the one with the most items).
- Two columns are named `entry_type` / `event_type` rather than `type`:
  `type` is Active Record's reserved single-table-inheritance column, and
  renaming beat disabling inheritance on `LedgerEntry` and `ListingEvent`.
- `payment.decline_reason` and `payment.status` are both mapped by Rails
  `enum`, sharing an underlying `string` column each — `decline_reason` is
  `nil` on an approved payment.
