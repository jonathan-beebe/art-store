# Data model

Generated from `database/migrations/`. Laravel's own tables (`sessions`,
`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`) are omitted —
nothing in the domain reads or writes them directly.

Question: what tables exist, what does each row mean, and how do they
connect?

## Identifiers

Every primary key and every foreign key below is text 30 characters long:
a three-letter table prefix, an underscore, and the 26-character body of a
ULID in uppercase Crockford base32 — `ord_01J5X3M9A2K8YB7Q4R6T1V0WZE`. The id
is the public identifier; there is no second column and no separate order
number. `docs/alignment.md` §1 fixes the format and the prefix table the three
prototypes share.

| Table            | Prefix | Table            | Prefix |
| ---------------- | ------ | ---------------- | ------ |
| admins           | `adm`  | listing_faqs     | `faq`  |
| sellers          | `sel`  | carts            | `crt`  |
| customers        | `cus`  | cart_items       | `cti`  |
| customer_merges  | `cmg`  | favorites        | `fav`  |
| customer_blocks  | `blk`  | orders           | `ord`  |
| magic_links      | `mlk`  | order_items      | `oit`  |
| listings         | `lst`  | payments         | `pay`  |
| listing_events   | `lev`  | fulfillments     | `ful`  |
| conversations    | `cnv`  | ledger_entries   | `led`  |
| messages         | `msg`  | payouts          | `pyt`  |
| notifications    | `ntf`  | refunds          | `rfd`  |
| listing_removals | `rmv`  | page_view_counts | `pvc`  |

`App\Domain\Identifiers\PrefixedId` reads and refuses the format;
`App\Models\Concerns\HasPrefixedUlid` mints an id from the application clock
when a row is created and turns a value carrying another table's prefix into
the site's 404 at route-model binding. Laravel's own tables keep the
framework's keys.

Rows are ordered by `created_at` — or by the domain's own instant, `sent_at`
for a message and `placed_at` for an order — never by the id alone. Second
resolution leaves ties, so the id breaks them; a ULID sorts in the order it
was minted.

`page_view_counts` and `listing_events` live in the analytics store
(`docs/alignment.md` §2.6), a SQLite file of its own beside this database.
They are drawn in the diagram below for their shape; the two relationship
lines running into `listing_events` are dotted because they are logical
only — no foreign key crosses the two files, so the columns that would
otherwise carry `FK` carry a reference note instead.

```mermaid
erDiagram
    sellers {
        text id PK
        string email UK
        string name
        string shop_name
        timestamp email_verified_at
    }
    customers {
        text id PK
        string email UK "nullable, anonymous rows have none"
        string name
        timestamp email_verified_at
    }
    admins {
        text id PK
        string email UK
        string name "nullable"
        timestamp email_verified_at "nullable"
    }
    customer_blocks {
        text id PK
        text customer_id FK "indexed with lifted_at"
        string reason "shown to the shopper on refusal"
        timestamp lifted_at "nullable, null while the block is active"
    }
    listing_removals {
        text id PK
        text listing_id FK "indexed with lifted_at"
        string kind "temporary | permanent"
        string reason "shown to the seller on their own listing page"
        timestamp lifted_at "nullable, null while the removal stands"
    }
    page_view_counts {
        text id PK
        string site "shop | seller | admin, read off the route pattern"
        string path_pattern "the route's pattern, not the concrete URL"
        date day "unique with site and path_pattern"
        unsigned count "incremented by the roll-up's upsert"
    }
    customer_merges {
        text id PK
        text anonymous_customer_id FK "UK, -> customers"
        text customer_id FK "-> customers, the verified survivor"
    }
    listings {
        text id PK
        text seller_id FK
        string title
        string slug UK
        unsigned price_cents
        unsigned quantity "default 1"
        string status "draft|for_sale|sold|archived"
    }
    listing_events {
        text id PK
        text listing_id "analytics store, references listings.id"
        text customer_id "nullable, analytics store, references customers.id"
        string type "view|favorite|..."
        timestamp occurred_at
    }
    favorites {
        text id PK
        text customer_id FK
        text listing_id FK
    }
    carts {
        text id PK
        text customer_id FK "not unique - a merge can leave two"
    }
    cart_items {
        text id PK
        text cart_id FK
        text listing_id FK
        unsigned quantity
    }
    orders {
        text id PK
        text customer_id FK
        string email
        string status
        string shipping_name
        string shipping_line1
        string shipping_city
        string shipping_region
        string shipping_postal_code
        string shipping_country
        unsigned subtotal_cents
        unsigned total_cents
        unsigned refunded_cents "sum of this order's refunds"
        timestamp placed_at
        timestamp finalized_at "nullable, set on paid"
    }
    order_items {
        text id PK
        text order_id FK
        text listing_id FK
        text seller_id FK
        string title "snapshot"
        unsigned unit_price_cents "snapshot"
        unsigned quantity
    }
    payments {
        text id PK
        text order_id FK "one row per attempt"
        string status "approved|declined"
        unsigned amount_cents
        string card_last_four
        string decline_reason "nullable"
        timestamp processed_at
    }
    fulfillments {
        text id PK
        text order_id FK "UK with seller_id"
        text seller_id FK
        string status "awaiting_shipment|shipped|delivered|declined|refunded"
        string carrier "nullable"
        string tracking_number "nullable"
        unsigned subtotal_cents
        unsigned fee_cents
        unsigned net_cents
    }
    refunds {
        text id PK
        text order_id FK
        text fulfillment_id FK "UK, one refund per fulfillment"
        text payment_id FK "nullable, the charge it reverses"
        unsigned amount_cents "always the whole fulfillment subtotal"
        string reason "1-500 chars"
        string issued_by_type "seller|admin"
        text issued_by_id "id within that table"
    }
    payouts {
        text id PK
        text seller_id FK "UK with period_start"
        date period_start
        date period_end
        unsigned amount_cents
        timestamp paid_at
    }
    ledger_entries {
        text id PK
        text seller_id FK
        text fulfillment_id FK "nullable"
        text payout_id FK "nullable"
        string type "held|released|paid_out|refunded"
        int amount_cents "signed"
        timestamp occurred_at
    }
    notifications {
        text id PK
        string type "the notification class that wrote the row"
        string notifiable_type "seller|customer (morph alias)"
        text notifiable_id "id within that table"
        json data "subject, body, url"
        timestamp read_at "nullable"
    }
    conversations {
        text id PK
        string kind "admin_seller|admin_customer|fulfillment|listing_question"
        string subject_key UK "kind + participant ids, e.g. listing_question:ssel_01J…:ccus_01J…:llst_01J…"
        text seller_id FK "nullable, indexed with last_message_at"
        text customer_id FK "nullable, indexed with last_message_at"
        text admin_id FK "nullable, indexed with last_message_at"
        text listing_id FK "nullable, the listing_question subject"
        text fulfillment_id FK "nullable, the fulfillment subject"
        timestamp last_message_at "nullable, the inbox sort"
    }
    messages {
        text id PK
        text conversation_id FK "cascade on delete"
        string sender_type "seller|customer|admin morph alias"
        text sender_id "the id within the table sender_type names"
        text body "<= 2000 characters"
        timestamp sent_at
        timestamp read_at "nullable, indexed with conversation_id"
    }
    listing_faqs {
        text id PK
        text listing_id FK "cascade on delete"
        string question "<= 500 characters"
        text answer "<= 2000 characters"
        text source_message_id FK "nullable, -> messages, null on delete"
        timestamp published_at "not null, the row exists only while published"
    }
    magic_links {
        text id PK
        string token_hash UK
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
    customers ||..o{ listing_events : records
    customers ||--o{ favorites : has
    customers ||--o{ carts : has
    customers ||--o{ orders : places
    customers ||--o{ customer_blocks : blocked_by
    customers ||--o{ customer_merges : "merged from (anonymous)"
    customers ||--o{ customer_merges : "merged into (verified)"
    sellers ||--o{ conversations : participates_in
    customers ||--o{ conversations : participates_in
    admins ||--o{ conversations : participates_in
    listings ||--o{ conversations : asked_about
    fulfillments ||--o{ conversations : asked_about
    conversations ||--o{ messages : holds
    listings ||--o{ listing_faqs : publishes
    messages ||--o{ listing_faqs : lifted_from
    listings ||..o{ listing_events : has
    listings ||--o{ favorites : favorited_in
    listings ||--o{ cart_items : held_in
    listings ||--o{ order_items : sold_as
    carts ||--o{ cart_items : contains
    orders ||--o{ order_items : contains
    orders ||--o{ payments : attempts
    orders ||--o{ fulfillments : split_by_seller
    listings ||--o{ listing_removals : taken_off_sale_by
    orders ||--o{ refunds : sends_back
    fulfillments ||--o| refunds : settled_by
    payments ||--o{ refunds : reversed_by
    fulfillments ||--o{ ledger_entries : produces
    payouts ||--o{ ledger_entries : settles
```

Caveats:

- `magic_links` has no foreign key to `sellers` or `customers` — it matches
  by `email` string plus `actor_type`, so it is drawn without a relationship
  line above. A seller and a customer can share an email address; each gets
  its own row in its own table.
- `notifications` is Laravel's own table, filled by the notification classes
  in `app/Notifications` and read back as
  `Illuminate\Notifications\DatabaseNotification`. Its rows carry a `ntf_` id
  rather than the UUID the framework mints, because
  `App\Notifications\PrefixedUlidNotification` sets one before the notification
  is sent. It names its recipient
  with a morph pair rather than a foreign key, so it is drawn without a
  relationship line above. `notifiable_type` holds the morph alias `seller`,
  `customer`, or `admin` — `AppServiceProvider` enforces that map from
  `App\Domain\Auth\ActorType`, so the column reads as words
  rather than class names. An anonymous-customer merge re-points the rows
  whose `notifiable_type` is `customer` through the morph relation.
- `messages.sender_type` holds the same three morph aliases, so a message
  names its sender the way a notification names its recipient and is drawn
  without a relationship line above.
- `conversations.subject_key` is the uniqueness spine of "one thread per
  subject": SQL treats null as distinct from null, so a composite unique
  index over the five nullable id columns would let a duplicate row through.
  The key folds the kind and those ids into one non-null string
  (`listing_question:ssel_01J…:ccus_01J…:llst_01J…`). It names the
  participants, so an
  anonymous-customer merge moves `customer_id` and `subject_key` together —
  see `docs/messaging.md` § "The merge".
- `listing_faqs` rows exist only while published: `published_at` is not null,
  and unpublishing deletes the row rather than clearing it.
  `source_message_id` records which answer an entry was lifted from and is
  `nullOnDelete`.
- `admins` is seeded, never written by a sign-up: `/admin/login` issues a
  magic link only for an address that already has a row, and
  `App\Actions\Auth\SignInAdmin` answers 404 rather than creating one. It
  holds no foreign key, so it is drawn without a relationship line above.
- `customer_blocks` keeps every block a customer has ever had; the active one
  is the row with `lifted_at` null. "At most one active block" is
  `BlockCustomer`'s rule rather than a partial unique index, which SQLite does
  not carry.
- `payments` is one row per charge attempt, not one row per order — a
  declined card followed by a retry leaves two rows. The order's current
  payment is the latest one by `processed_at` (`Order::latestPayment()`).
- `ledger_entries.amount_cents` is signed: `held` and `released` are
  positive, `paid_out` and `refunded` are negative. See `docs/escrow.md`.
- `refunds` has `unique(fulfillment_id)`: the amount is always the whole
  fulfillment subtotal, so a second row would be a second full refund. It
  names who issued it with `issued_by_type` / `issued_by_id` rather than a
  foreign key, because a seller and an admin live in different tables; the
  column holds the same `seller` / `admin` words the morph map uses, read back
  through `Refund::issuer()`. See `docs/orders.md`.
- `carts.customer_id` is not unique — `MergeAnonymousCustomer` can re-point
  a second cart onto a customer that already has one.
