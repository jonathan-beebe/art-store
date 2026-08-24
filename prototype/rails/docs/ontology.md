# Domain ontology

A two-sided marketplace for hand-made art: sellers list one-of-a-kind or
limited-quantity pieces, customers browse and buy them. Payment is captured
at checkout but held in escrow per seller until the customer confirms
delivery, then settled into a weekly payout. An admin runs the desk both
sides reach for support.

Question: what are the entities in the product, and how does value move
between them at the concept level? (Table-level shape: `docs/data-model.md`.
Sequence and state detail: `docs/orders.md`, `docs/escrow.md`,
`docs/identity.md`, `docs/messaging.md`, `docs/admin.md`.)

```mermaid
flowchart LR
    subgraph sellerSide["Seller side"]
        seller["Seller"]
        listing["Listing"]
    end
    subgraph customerSide["Customer side"]
        customer["Customer"]
        cart["Cart"]
        order["Order"]
    end
    subgraph moneySide["Money"]
        platform["Platform"]
        payment["Payment"]
        fulfillment["Fulfillment"]
        ledger["Ledger entry"]
        payout["Payout"]
    end

    seller -->|"lists"| listing
    listing -->|"added to"| cart
    customer -->|"holds"| cart
    cart -->|"becomes"| order
    customer -->|"places"| order
    order -->|"charged via"| payment
    order -->|"splits by seller into"| fulfillment
    seller -->|"ships"| fulfillment
    fulfillment -->|"produces"| ledger
    platform -->|"takes fee from"| fulfillment
    ledger -->|"settles into"| payout
    payout -->|"pays"| seller
    fulfillment -->|"reversed by"| refund["Refund"]
    refund -->|"sends back to"| customer
```

Smaller catalog, identity and messaging concepts (listing event, favorite,
cart item, order item, magic link, customer merge, notification, conversation,
message, listing FAQ) sit off this diagram —
they support the entities shown rather than carrying their own flow. So do two
moderation concepts, Listing removal and Customer block, which a platform
Admin raises against the Catalog and Roles sides rather than money moving
through them. Each gets its own section below.

## Roles

### Seller

**Who/what.** A person or studio who lists art for sale and gets paid out.

**Why it exists.** Supplies the catalog; the platform's other side of the
marketplace.

**Lifecycle.** None — a seller row exists from first sign-in and does not
change state. `email_verified_at` is set the first time they sign in through
a magic link.

**Relates to.**
- owns Listings
- ships Fulfillments
- receives Ledger entries and Payouts
- receives Notifications
- is the seller side of Conversations, and publishes Listing FAQs

**In code.** `Seller` (table `sellers`).

### Customer

**Who/what.** A storefront visitor. Every visitor gets a row, verified or
not — see "verified customer" and "guest checkout" in Vocabulary notes.

**Why it exists.** Holds favorites, a cart, and orders across a visit, so
browsing state survives before anyone gives an address.

**Lifecycle.** None as a status field, but a row moves through three
conditions: anonymous (`email` null) → guest with an unverified address
(email set, `email_verified_at` null) → verified (`email_verified_at` set).
`Customer#anonymous?` reads the first.

**Relates to.**
- has Favorites, a Cart, and Listing events
- places Orders
- receives Notifications
- is the customer side of Conversations
- may be the source or the target of a Customer merge
- may carry an active Customer block, which stops them shopping and messaging

**In code.** `Customer` (table `customers`).

### Customer block

**Who/what.** An admin's record that a customer may not shop or message,
independent of anything else about their account.

**Why it exists.** Moderation has to be able to stop a bad actor from
buying or sending messages without deleting their history.

**Lifecycle.** Raised with a reason; lifted (`lifted_at` set) restores
standing. At most one active block per customer — a partial unique index
(`WHERE lifted_at IS NULL`) enforces it at the row level, the same shape as a
Listing removal.

**Relates to.**
- belongs to one Customer and the Admin who raised it
- while active: `Customer#can_shop?` is false, which stops adding to a cart,
  checking out, paying, and posting a message (as either side of a thread or
  a listing question) — browsing, favorites, and reading threads are
  unaffected
- **left behind by a Customer merge** — a block stays on the row an admin
  named even when that row is later merged into a verified account; see
  "Customer merge" below and `docs/review.md`'s known gaps

**In code.** `CustomerBlock` (table `customer_blocks`), written by
`Customer#block!(reason:, by:)` and read through `Customer#active_block` /
`Customer#blocked?` / `Customer#can_shop?`. See
[`admin.md`](admin.md#what-a-removal-or-a-block-actually-does).

### Anonymous visitor

**Who/what.** Not a separate table — a Customer row with `email = nil`,
identified by a signed `customer_id` cookie rather than sign-in.

**Why it exists.** Lets a visitor favorite, cart, and check out as a guest
before proving an email address.

**Lifecycle.** Ends when the visitor verifies an address: the row is either
claimed in place or merged into an existing verified Customer (see Customer
merge).

**Relates to.**
- is a Customer
- resolved per request by `CustomerIdentity#current_customer` (see
  `docs/identity.md`)

**In code.** No separate model or enum — `Customer#anonymous?`,
`Customer.claim`.

### Admin

**Who/what.** An operator of the platform, working the admin site at
`/admin`.

**Why it exists.** Someone has to answer a seller's or a customer's support
thread and look up either side's account.

**Lifecycle.** None. Admin rows are seeded — `Admin.claim` finds a row and
creates none, so an address with no `admins` row reaches no session.

**Relates to.**
- reads Sellers and Customers on the admin dashboard and their account pages
- is the admin side of `admin_seller` and `admin_customer` Conversations
- receives Notifications
- raises Listing removals and Customer blocks, and lifts them
- runs the weekly Payout for every seller (§ Platform, below)
- reconciles every Seller's balance and cancels an order or refunds a
  Fulfillment on either side's behalf

**In code.** `Admin` (table `admins`), with `Admin.claim`, `Admin.on_duty`
(the first admin by id — who a support thread opens against) and
`Admin#display_name`.

### Platform

**Who/what.** The marketplace as a party to the money: the side that takes a
cut of each sale and settles seller payouts. A role played by the code; the
Admin is the person who works the desk.

**Why it exists.** Names who the platform fee belongs to and who runs the
weekly payout job.

**Lifecycle.** None.

**Relates to.**
- takes a Platform fee from each Fulfillment's subtotal
- runs Payouts (`payouts:run`)

**In code.** `Fulfillment::PLATFORM_FEE_PERCENT`; no model — the platform
holds no row of its own.

## Catalog

### Listing

**Who/what.** One piece (or a small run of identical pieces) a seller has
for sale.

**Why it exists.** The unit of inventory and the storefront's unit of
browsing.

**Lifecycle.** `draft → for_sale → sold`, plus `archived` from `draft` or
`for_sale`, plus `sold → for_sale` (a declined charge restores the stock it
took). Search and browse show only `for_sale` listings; a `sold` listing keeps
its own page. See "sold" in Vocabulary notes.

**Relates to.**
- belongs to one Seller
- records Listing events
- favorited by Customers via Favorite
- held in Cart items, sold as Order items
- the subject of `listing_question` Conversations, and publishes Listing FAQs
- may carry an active Listing removal, which takes it off the storefront
  independent of its own status

**In code.** `Listing` (table `listings`), which carries the `status` enum,
`TRANSITIONS`, `on_storefront`, `purchasable?`, `take_stock!` /
`restore_stock!` and the field validations.

### Listing removal

**Who/what.** An admin's record that a listing is off the storefront,
independent of its own `draft`/`for_sale`/`sold`/`archived` status.

**Why it exists.** Moderation has to be able to pull a piece down (a
complaint, a policy issue) without the seller's own status controls fighting
it, and without losing the reason once it is lifted.

**Lifecycle.** Raised with a `kind` (`temporary` | `permanent`) and a reason;
`temporary` can be lifted (`lifted_at` set), `permanent` cannot. At most one
active removal per listing — a partial unique index (`WHERE lifted_at IS
NULL`) enforces it at the row level. Raising a new removal over an already
active one is refused; the way to change a removal's terms is to lift it and
raise a fresh one.

**Relates to.**
- belongs to one Listing and the Admin who raised it
- while active: takes the listing off `Listing.on_storefront` (browse,
  search, and its own `/art/:slug` page all 404), drops it from a cart or a
  retried charge as the `removed` `OrderPlacement` reason, and hides the
  `for_sale` button on the seller's own listing page

**In code.** `ListingRemoval` (table `listing_removals`), written by
`Listing#remove!(kind:, reason:, by:)` and read through
`Listing#active_removal` / `Listing#actively_removed?`. See
[`admin.md`](admin.md#what-a-removal-or-a-block-actually-does).

### Listing event

**Who/what.** One recorded interaction with a listing: a view, a favorite,
an unfavorite, or a cart-add.

**Why it exists.** Feeds the seller's per-listing activity numbers (views,
favorites, cart adds) and the dashboard's daily activity timeline.

**Lifecycle.** None — write-once, timestamped fact.

**Relates to.**
- belongs to one Listing
- optionally attributed to one Customer (`customer_id` nullable)

**In code.** `ListingEvent` (table `listing_events`), whose `event_type` enum
is `view` | `favorite` | `unfavorite` | `cart_add`, written by
`Listing#record_event!`.

### Favorite

**Who/what.** A customer's bookmark on a listing.

**Why it exists.** Lets a customer track pieces of interest without adding
them to the cart.

**Lifecycle.** None — exists or does not; toggled on and off.

**Relates to.**
- belongs to one Customer and one Listing
- toggling one also records a Listing event (`favorite`/`unfavorite`)

**In code.** `Favorite` (table `favorites`), toggled by
`Customer#toggle_favorite`, which returns `:added` or `:removed` and records
the matching listing event.

## Buying

### Cart

**Who/what.** A customer's in-progress selection, held until checkout.

**Why it exists.** Lets a customer collect items from multiple sellers
before placing one order.

**Lifecycle.** None as a status; exists per customer, spawns an Order on
checkout. `carts.customer_id` is not unique — a Customer merge folds the two
sides into one cart rather than leaving a second row, but the column itself
carries no constraint, so `Customer#current_cart` still picks whichever row
holds the most items if a customer ever ends up with more than one by some
other path.

**Relates to.**
- belongs to one Customer
- contains Cart items

**In code.** `Cart` (table `carts`), which carries `add`, `remove`,
`item_count`, `subtotal` and `subtotals_by_seller`.

### Cart item

**Who/what.** One listing and quantity held in a cart.

**Why it exists.** The line the cart totals and the order are built from.

**Lifecycle.** None — created on add, deleted on remove or on checkout.

**Relates to.**
- belongs to one Cart
- references one Listing

**In code.** `CartItem` (table `cart_items`), written by `Cart#add` and
`Cart#remove`; `CartItem#total` is the line's unit price times its quantity.

### Order

**Who/what.** A customer's purchase, possibly spanning several sellers.

**Why it exists.** The record of a transaction from checkout through
delivery; the parent of the per-seller Fulfillments.

**Lifecycle.** `pending_verification` (guest) or `awaiting_payment`
(verified) → `paid` or `payment_failed` → `partially_shipped` / `shipped` →
`delivered`; `cancelled` while nobody has paid, `refunded` once every
Fulfillment has been declined or refunded.
A multi-seller order's status rolls up from its Fulfillments
(`Order#roll_up_status!`). Full diagram: `docs/orders.md`.

**Relates to.**
- placed by one Customer
- contains Order items
- attempts Payments
- splits by seller into Fulfillments
- triggers an "Item sold" Notification to each seller when it reaches `paid`

**In code.** `Order` (table `orders`), which carries the `status` enum,
`TRANSITIONS`, the shipping fields it validates, `place`, `pay!`,
`mark_awaiting_payment!`, `roll_up_status!`, `cancel!` and `sweep_stale`.

### Order item

**Who/what.** A snapshot of one purchased listing: title, unit price, and
quantity as they were at checkout.

**Why it exists.** An order reads the same after the seller edits or deletes
the listing behind it.

**Lifecycle.** None — written once at order placement.

**Relates to.**
- belongs to one Order
- references the Listing it was bought from, tagged with the selling Seller

**In code.** `OrderItem` (table `order_items`).

### Payment

**Who/what.** One charge attempt against an order's card.

**Why it exists.** Records the outcome (approved or declined) of trying to
collect the order total; a retry after a decline is a new attempt, not an
edit.

**Lifecycle.** `approved` or `declined`, one row per attempt — the order's
current payment is the latest row.

**Relates to.**
- belongs to one Order
- decided by a Card decision

**In code.** `Payment` (table `payments`), which carries the `status` and
`decline_reason` enums and the decline messages, written by `Order#pay!`.

### Fulfillment

**Who/what.** One seller's slice of an order — what that seller owes to ship
and what they're owed once it's delivered.

**Why it exists.** An order can span sellers; escrow and shipping status are
tracked per (order, seller) pair rather than per order.

**Lifecycle.** `awaiting_shipment → shipped → delivered`, plus two terminal
branches that reverse it: `declined` (the seller pulls out, before shipping)
and `refunded` (the platform sends the money back, from any of the three
live statuses). Full diagram: `docs/orders.md`.

**Relates to.**
- belongs to one Order and one Seller
- produces Ledger entries when the order is paid (`held`), when delivered
  (`released`), when included in a Payout (`paid_out`), and when declined or
  refunded (`refunded`)
- carries the Platform fee taken from its subtotal, forgone on a declined or
  refunded fulfillment
- the subject of the `fulfillment` Conversation its two sides keep
- may be reversed by a Refund, which it produces when declined or refunded

**In code.** `Fulfillment` (table `fulfillments`), transitioned by
`Fulfillment#ship!` / `Fulfillment#deliver!` / `Fulfillment#decline!` /
`Fulfillment#refund!`. See "Vocabulary notes" for the seller portal's name
for this entity.

## Money

### Money

**Who/what.** An integer-cents amount; the type every price, fee, and
balance in the system is expressed in.

**Why it exists.** Avoids float rounding on currency; every arithmetic
operation (add, multiply, percent) works in whole cents.

**Lifecycle.** None — an immutable value.

**Relates to.**
- used by Listing price, Order totals, Payment amount, Fulfillment
  subtotal/fee/net, Ledger entry amount, Payout amount

**In code.** `Money` (value object; no table — stored as `*_cents`
integer columns).

### Platform fee

**Who/what.** The percentage of an item's subtotal the platform keeps.

**Why it exists.** The platform's revenue; the reason "seller net" is less
than the sale subtotal.

**Lifecycle.** None — computed once at order placement, stored on the
Fulfillment row (`fee_cents`, `net_cents`) rather than recomputed later.

**Relates to.**
- computed from a Fulfillment's subtotal (10%,
  `Fulfillment::PLATFORM_FEE_PERCENT`)
- taken by the Platform

**In code.** `Fulfillment.fee_for` / `Fulfillment.net_for` (no table —
persisted as `fulfillments.fee_cents`/`net_cents`).

### Ledger entry

**Who/what.** One movement of escrowed money for one seller.

**Why it exists.** An auditable trail of every hold, release, and payout,
rather than a single mutable balance column.

**Lifecycle.** Written once per movement: `held` (order paid), `released`
(fulfillment delivered), `paid_out` (included in a payout run — negative
amount), `refunded` (a fulfillment declined or refunded — negative amount).
A seller's balance is folded per fulfillment, not per entry type: a refund's
timing (before release, after release, after payout) changes what it does to
the balance in a way one formula over a seller's entry-type totals cannot
express, so `LedgerEntry.balance` (through `Seller#escrow_balance`) sums one
fulfillment at a time and adds the parts together. Flowchart and the fold's
formula: `docs/escrow.md`.

**Relates to.**
- belongs to one Seller
- produced by one Fulfillment (`held`/`released`/`refunded`) or one Payout
  (`paid_out`)

**In code.** `LedgerEntry` (table `ledger_entries`), written by
`LedgerEntry.hold` / `.release` / `.pay_out` / `.refund` and folded by
`LedgerEntry::Balance`. Column is `entry_type`, not `type` — see "Vocabulary
notes."

### Refund

**Who/what.** One movement of money back to a customer, reversing one
fulfillment.

**Why it exists.** A seller pulling out or the platform settling a dispute
both have to hand the money back and say why, in a way the order, the
seller's earnings page, and the platform's accounting all read the same row
for.

**Lifecycle.** Written once, by `Fulfillment#decline!` (the seller, only from
`awaiting_shipment`) or `Fulfillment#refund!` (the platform, from
`awaiting_shipment`, `shipped`, or `delivered`) — never edited afterward. At
most one refund per fulfillment, enforced by a unique index as well as by
both fulfillment transitions being terminal.

**Relates to.**
- belongs to one Order, one Fulfillment, and the Payment it reverses
- issued by a Seller (decline) or an Admin (refund) — `issued_by_type` /
  `issued_by_id` name which, with no foreign key
- always the whole Fulfillment's `subtotal_cents` — no partial line refunds
  in this cut
- adds to `orders.refunded_cents` and writes the `refunded` Ledger entry

**In code.** `Refund` (table `refunds`), written by `Refund.issue`. See
`docs/orders.md` and `docs/escrow.md`.

### Payout

**Who/what.** One weekly settlement of a seller's released-but-unpaid
escrow.

**Why it exists.** Turns "released" money (owed but sitting in the ledger)
into a single dated disbursement a seller can point to.

**Lifecycle.** Created once per (seller, period) by `payouts:run`; no status
field — its existence is the state. Re-running the same period is a no-op
(the `paid_out` entry it wrote is dated inside the period it settles, so the
balance nets to zero on the next run).

**Relates to.**
- belongs to one Seller
- settles Ledger entries (writes one `paid_out` entry per payout)
- covers one Payout period

**In code.** `Payout` (table `payouts`), created by `Payout.run_weekly`.

### Payout period

**Who/what.** The Monday–Sunday window a payout run settles.

**Why it exists.** Gives `payouts:run` a pure, deterministic window
(`PayoutPeriod.ending_before(as_of)`) instead of "everything released so
far."

**Lifecycle.** None — a value computed fresh from a moment in time.

**Relates to.**
- bounds which Ledger entries a Payout run settles

**In code.** `PayoutPeriod` (`app/models/payout_period.rb` — a value object
with no table; persisted as `payouts.period_start`/`period_end`).

## Identity and messaging

### Magic link

**Who/what.** A one-time, expiring, hashed token that signs someone in
without a password.

**Why it exists.** The product is passwordless for both sellers and
customers.

**Lifecycle.** `usable` → `expired` (past `expires_at`) or `consumed` (used
once). Sequence diagrams: `docs/identity.md`.

**Relates to.**
- matched to a Seller or a Customer by `email` string, not a foreign key (a
  seller and a customer can share an email address)
- carries an `actor_type` and an optional post-verification redirect

**In code.** `MagicLink` (table `magic_links`, column `token_digest`,
`actor_type` enum), issued by `MagicLink.issue` and spent through
`MagicLink.find_by_token`, `#usable?` and `#consume`.

### Customer merge

**Who/what.** A record that an anonymous customer row was folded into an
already-verified one.

**Why it exists.** A visitor can browse anonymously on one device, then
verify an address that another device already claimed; the merge lets a
stale cookie on the first device keep resolving to the right account.

**Lifecycle.** None — written once by `Customer#absorb`; never undone.

**Relates to.**
- points one anonymous Customer at the verified Customer it merged into
- **folds** the anonymous customer's Cart (lines summed and clamped to
  stock), Favorites (a union — move a favorite the verified customer lacks,
  drop one they already hold), and Conversations (a duplicate thread's
  messages move onto the verified customer's own, the emptied thread is
  destroyed) into the verified customer's own
- **re-points** the anonymous customer's Orders, Listing events,
  Notifications, and Messages sent onto the verified customer
  (`Customer::REPOINTED_ASSOCIATIONS`)
- **leaves behind** the anonymous customer's Customer blocks — an active
  block does not follow the merge onto the verified account
  (`Customer::LEFT_BEHIND_ASSOCIATIONS`; see "Customer block" above and
  `docs/review.md`'s known gaps for the evasion this allows)

**In code.** `CustomerMerge` (table `customer_merges`), written by
`Customer#absorb` when `Customer.claim` finds both an anonymous row and an
account holding the address. `CustomerMergePlan` is the pure fold/partition
logic `#absorb` calls for the cart and favorites.

### Notification

**Who/what.** A message shown in a seller's, a customer's or an admin's
header: "Item sold", "Order shipped" or "New message."

**Why it exists.** Tells each side when the other side has acted, without
email.

**Lifecycle.** Unread → read (`read_at` set). No other state.

**Relates to.**
- belongs to exactly one Seller, Customer or Admin
- raised by an Order reaching `paid` (seller) or being cancelled by an admin
  (customer and every seller on it), a Fulfillment reaching `shipped`
  (customer) or being declined or refunded (customer, and the seller on an
  admin refund), or a Conversation gaining a message (the counterpart)
- carries the recipient's own path: the three sites read the same
  conversation under three URLs

**In code.** `Notification` (table `notifications`), addressed to a polymorphic
`recipient` and written by `Notification.item_sold`,
`Notification.order_shipped`, `Notification.order_cancelled`,
`Notification.fulfillment_declined`, `Notification.fulfillment_refunded` and
`Notification.new_message`.

### Conversation

**Who/what.** One thread between two actors: a seller and the desk, a
customer and the desk, a seller and a customer about an order, or a seller
and a customer about a listing.

**Why it exists.** Every pairing on the marketplace needs somewhere to talk,
and one thread per (kind, participants, subject) means "message this seller"
reaches the place the last message came from.

**Lifecycle.** None as a status. A thread is opened by the first message and
moves to the top of both inboxes on every message (`last_message_at`).

**Relates to.**
- names exactly two participants — which two is its `kind`
- may hang off a subject: a Listing (`listing_question`) or a Fulfillment
  (`fulfillment`)
- holds Messages
- files a Notification to the counterpart on every message
- travels with an anonymous Customer through a merge

**In code.** `Conversation` (table `conversations`), whose `KINDS` is the one
source for the participant pair, the subject class and the topic, with
`Conversation.open`, `.involving`, `#post!`, `#read_by!`,
`#counterpart_of`, `#topic` and `#thread_path_for`. Full detail:
`docs/messaging.md`.

### Message

**Who/what.** One thing one participant said in a conversation.

**Why it exists.** The unit a thread page renders, an unread count counts,
and an FAQ entry can be lifted from.

**Lifecycle.** Unread → read (`read_at` set when the other side opens the
thread). No other state.

**Relates to.**
- belongs to one Conversation
- sent by one Seller, Customer or Admin (polymorphic `sender`)
- may be the source of a Listing FAQ

**In code.** `Message` (table `messages`), with `BODY_LIMIT` (2000) and
`Message.unread_for`, the single definition of unread. `after_create_commit`
broadcasts the row to both sides and the counterpart's badge.

### Listing FAQ

**Who/what.** One answered question published on a listing page for every
visitor.

**Why it exists.** An answer a seller has already written is worth more on
the listing than in one buyer's thread.

**Lifecycle.** None — the row exists only while the entry is published, and
unpublishing deletes it.

**Relates to.**
- belongs to one Listing
- may record the Message its answer was lifted from (`source_message`,
  nullable — the entry outlives the thread)

**In code.** `ListingFaq` (table `listing_faqs`), with `QUESTION_LIMIT`
(500), `ANSWER_LIMIT` (2000), `ListingFaq.draft_from` (the pair a thread
offers) and `ListingFaq.publish`.

## Decisions

### Card decision / Fake card

**Who/what.** The outcome of running a submitted card number through the
prototype's fake payment processor: approved, or declined with a reason.

**Why it exists.** Stands in for a real payment gateway; a fixed set of test
numbers makes every checkout outcome reproducible.

**Lifecycle.** None — computed fresh per attempt from the card number, never
stored as its own row (its outcome becomes a Payment).

**Relates to.**
- decides a Payment's status and a Payment's decline reason
- drives the Order's `paid`/`payment_failed` transition

**In code.** `FakeCard`, read by `Order#pay!`.

### Listing status

**Who/what.** The lifecycle state of a Listing (see Catalog above).

**In code.** `Listing::TRANSITIONS` and the `Listing` `status` enum.

### Order status

**Who/what.** The lifecycle state of an Order (see Buying above).

**In code.** `Order::TRANSITIONS` and the `Order` `status` enum.

### Fulfillment status

**Who/what.** The lifecycle state of a Fulfillment (see Buying above).

**In code.** The `status` enum on `Fulfillment`.

## Vocabulary notes

- The seller portal's "Orders" page (`seller_orders` / `seller_order`,
  `Seller::OrdersController`) lists **Fulfillments**, not Orders — a seller's
  order is the one fulfillment that belongs to them.
- "Verified customer" = a `customers` row with `email_verified_at` set.
- "Guest checkout" = an order placed by an anonymous customer
  (`OrderStatus::PENDING_VERIFICATION`); the order is not finalized (charged)
  until that customer verifies an email via magic link.
- "Sold" on a listing = `quantity` reached 0, not "no longer listed" — a
  `sold` listing keeps its storefront page; only `draft` and `archived`
  listings are unreachable.
- "Seller net" / a Fulfillment's `net_cents` = subtotal minus the Platform
  fee; this is the amount that moves through escrow (`held` → `released` →
  `paid_out`), not the sale's subtotal.
- "Available" (as in a seller's available balance,
  `LedgerEntry::Balance#available`) means released and not yet paid out — it
  does not mean "in the seller's bank account."
- An anonymous customer is not a distinct model — it is a `Customer` row
  with `email = nil`; "customer" in prose can mean either the anonymous or
  the verified case unless qualified.
- "Thread" and "conversation" are the same thing: the page says thread, the
  table and the model say `Conversation`.
- "The desk" = the `Admin.on_duty` row, which is the first admin by id. Both
  support buttons open against it, and no code assigns threads to anyone
  else.
- "Unread" is defined once, on the message: `read_at` is null and the reader
  did not send it (`Message.unread_for`). The nav badge, the inbox row badge
  and the marking done on opening a thread all read that one scope.
- "Published" on an FAQ entry = the row exists. There is no draft state and
  no `published` flag; unpublishing deletes the row.
- Seller-portal controllers are written in compact form (`class
  Seller::XController < Seller::BaseController`) — `app/models/seller.rb`
  already defines `Seller` as a class, so `module Seller` elsewhere under
  `app/` collides with the model. See `docs/architecture.md`.
