# Orders

Checkout, payment, fulfillment, and the ways an order comes apart again.
Code: `app/models/order.rb`, `app/models/order_placement.rb`,
`app/models/fulfillment.rb`, `app/models/refund.rb`,
`app/controllers/shop/checkouts_controller.rb`,
`app/controllers/shop/order_payments_controller.rb`,
`app/controllers/shop/cancellations_controller.rb`,
`app/controllers/seller/declines_controller.rb`,
`app/controllers/admin/cancellations_controller.rb`,
`app/controllers/admin/refunds_controller.rb`, `lib/tasks/orders.rake`.

## Checkout to a paid, seller-notified order

Question: from a submitted checkout form to a seller's "Item sold"
notification, what runs, and where does a guest's flow diverge from a
verified customer's?

```mermaid
sequenceDiagram
    actor Customer
    participant Checkout as Shop::CheckoutsController
    participant Order
    participant Notification
    participant Pay as Shop::OrderPaymentsController

    Customer->>Checkout: POST /checkout (email, shipping, card?)
    Checkout->>Order: Order.place(cart:, customer:, email:, email_verified:, shipping:)
    Order->>Order: OrderPlacement.plan(cart items), inside the transaction
    alt a line is blocked
        Order-->>Checkout: order (unsaved), blocked_lines
        Checkout-->>Customer: 422, every blocked line and its reason
    else every line is placeable
        Order->>Order: snapshot items, split per seller, take stock, empty the cart
        Order-->>Checkout: order (pending_verification | awaiting_payment)
    end

    alt the buyer's email is verified
        Checkout->>Order: pay!(card_number)
        Order->>Order: charge, status -> paid, hold escrow per fulfillment
        Order->>Notification: item_sold(fulfillment) per seller
        Checkout-->>Customer: redirect /orders/:id
    else guest, email unverified
        Checkout->>Checkout: send_magic_link(email, redirect_to: /orders/:id/pay)
        Checkout-->>Customer: redirect /orders/:id, "check your email"
        Note over Customer: verifies by magic link (see identity.md), lands on /orders/:id/pay
        Customer->>Pay: POST /orders/:id/pay (card_number)
        Pay->>Order: mark_awaiting_payment! (pending_verification -> awaiting_payment)
        Pay->>Order: pay!(card_number)
        Order->>Order: charge, status -> paid, hold escrow per fulfillment
        Order->>Notification: item_sold(fulfillment) per seller
        Pay-->>Customer: redirect /orders/:id
    end
```

Caveats: a declined card sets `payment_failed` and restores the stock
placement took (`Order::RELEASES_STOCK` -> `Listing#restore_stock!` -> `sold ->
for_sale` when the listing had reached zero). A retry — guest or signed-in —
posts back to the same `POST /orders/:id/pay`, which is why
`Shop::OrderPaymentsController#create` calls `mark_awaiting_payment!` before
`pay!` on every hit: the call is a no-op once the order is already
`awaiting_payment`. `pay!` writes one `payments` row per attempt, so two
declines followed by an approval leave three rows on the order. An address
checkout left incomplete never reaches an `orders` row: `Order` validates the
email and the shipping fields, `Order.place` hands an invalid order back
unsaved, and `Shop::CheckoutsController` re-renders the form with the
`INCOMPLETE` alert and the values that were submitted.

## Placement availability

Question: what stops a cart from becoming an order, and how does a customer
learn about it?

A cart sits for as long as its owner leaves it there, so what it holds is
judged fresh: `OrderPlacement.plan` folds a set of lines (cart items at
checkout, order items at a retried charge) into placeable lines and blocked
lines, each blocked line carrying one `OrderPlacement::Line`-derived reason —
`removed`, `off_sale`, `sold_out`, or `short_stock` (`OrderPlacement.reason_for`).
A removal outranks whatever the status says; a sold listing outranks a bare
quantity of zero reading as merely out of stock.

```mermaid
flowchart TD
    A[line: listing + requested quantity] --> B{actively removed?}
    B -- yes --> R[removed]
    B -- no --> C{status == sold?}
    C -- yes --> S[sold_out]
    C -- no --> D{status != for_sale?}
    D -- yes --> O[off_sale]
    D -- no --> E{available quantity < 1?}
    E -- yes --> S
    E -- no --> F{requested > available?}
    F -- yes --> H[short_stock]
    F -- no --> P[placeable]
```

`Order.place` builds the plan as the first statement inside the transaction
that takes the stock, against the listing rows as they stand at that moment,
after `OrderPlacement.lock_listings` locks them (`Listing.lock`, in ascending
id order, so two placements locking the same listings cannot deadlock). Two
shoppers cannot both take the last piece because Rails' SQLite3 adapter opens
every top-level transaction with `BEGIN IMMEDIATE`, which serializes writers:
a second placement's transaction cannot begin until the first has committed
or rolled back, so its plan is always built against the first's outcome —
the row lock states that intent in code (and is what would carry the
guarantee on an adapter that runs transactions concurrently) but is not,
under SQLite, what stops the interleaving.

A blocked plan rolls the transaction back (`ActiveRecord::Rollback`) and
`Order.place` hands back an unsaved order carrying `blocked_lines`, named and
reasoned; `Shop::CheckoutsController#create` re-renders checkout at 422 with
every blocked line, rather than letting `Listing#take_stock!`'s `ArgumentError`
reach the customer as a 500. `Shop::CartsController#show` runs the same plan
read-only against the cart, so the cart page marks each blocked line and
disables the checkout control while any exists.

A charge can also reclaim stock: a retry from `payment_failed` to `paid`
takes the stock the earlier decline had restored. `Order#pay!` builds the same
plan from the order's own items before that retry, and a listing sold out
from under the order while it sat unpaid refuses the charge the same way —
`Shop::OrderPaymentsController#create` re-renders the pay page at 422 with the
blocked line, and no payment row is written. `removed` is implemented but
unreachable today: no admin removal exists in this prototype, so
`Listing#actively_removed?` always answers `false`. FEAT-021 backs it with a
`listing_removals` row.

Both refusals log through `Story`: `order.place` and `order.pay` write a
`refused` line at `info`, carrying `blocked_lines` (listing id, title, reason)
in `data` — never `failed` at `error`, since the world is unchanged.

## Order status

Question: what are the legal `Order` status transitions?

```mermaid
stateDiagram-v2
    [*] --> pending_verification : guest checkout, email unverified
    [*] --> awaiting_payment : verified customer, order placed
    pending_verification --> awaiting_payment : email verified
    pending_verification --> cancelled : customer, admin, or the sweep
    awaiting_payment --> paid : card approved
    awaiting_payment --> payment_failed : card declined
    awaiting_payment --> cancelled : customer or admin
    payment_failed --> paid : retry, card approved
    payment_failed --> payment_failed : retry, card declined again
    payment_failed --> cancelled : customer or admin
    paid --> partially_shipped : one fulfillment shipped, at least one not
    paid --> shipped : every fulfillment shipped
    partially_shipped --> shipped
    shipped --> delivered : every fulfillment delivered
    paid --> refunded : every fulfillment declined or refunded
    partially_shipped --> refunded
    shipped --> refunded
    delivered --> refunded
    delivered --> [*]
    cancelled --> [*]
    refunded --> [*]
```

Source of truth: `Order::TRANSITIONS`, verified by
`test/models/order_test.rb`. A guest order cannot jump straight from
`pending_verification` to `paid`: the table has no such edge, so `Order#pay!`
on an unverified order raises `TransitionError` — this is the one place the
Rails design departs from the PHP spike, where `pending_verification -> paid`
was legal. A `cancelled` or `refunded` order has no edge out, so a card
posted at one is refused by the same table.

Everything from `paid` upward is rolled up rather than asked for.
`Order#roll_up_status!` reads the fulfillments and keeps only the ones nobody
pulled the money back on (`Fulfillment#reversed?` — `declined` or `refunded`):
no live fulfillment left is `refunded`; all live delivered is `delivered`; all
live departed is `shipped`; any live departed is `partially_shipped`;
otherwise `paid`. So one shipped fulfillment beside one declined one reads
`shipped`, and a delivered one beside an unshipped one still reads
`partially_shipped`.

## Cancel, sweep, decline, refund

Question: what are the ways an order comes apart, who may do each, and what
moves when they do?

```mermaid
flowchart TD
    unpaid["order: pending_verification,<br/>awaiting_payment, payment_failed"]
    unpaid -->|"customer: POST /orders/:id/cancel"| cancel
    unpaid -->|"admin: POST /admin/orders/:id/cancellation"| cancel
    unpaid -->|"make sweep: Order.sweep_stale"| cancel
    cancel["Order#cancel!<br/>status -> cancelled<br/>stock restored"]

    paid["fulfillment: awaiting_shipment"]
    paid -->|"seller: POST /seller/orders/:id/decline"| decline
    decline["Fulfillment#decline!<br/>status -> declined<br/>stock restored<br/>Refund.issue"]

    any["fulfillment: awaiting_shipment,<br/>shipped, delivered"]
    any -->|"admin: POST /admin/fulfillments/:id/refund"| refund
    refund["Fulfillment#refund!<br/>status -> refunded<br/>stock stays sold<br/>Refund.issue"]

    decline --> issue
    refund --> issue
    issue["Refund.issue<br/>refunds row (rfd_)<br/>orders.refunded_cents += amount<br/>LedgerEntry.refund: refunded −net"]
    issue --> rollup["Order#roll_up_status!"]
```

- **Cancel** is only for an order nobody has paid for
  (`Order::CANCELLABLE`). `Order#cancel!` locks the row, asks
  `Order.transition` for the move — so a paid order is refused with
  `TransitionError` — and hands the stock back through the same
  `Order::RELEASES_STOCK` rule a declined card uses. Cancelling a
  `payment_failed` order restores nothing, because the decline already did.
  An admin's cancel notifies the customer and every seller who was going to
  ship; a customer cancelling their own order notifies nobody.
- **The sweep** (`make sweep`, `orders:sweep`, `Order.sweep_stale(before:)`)
  cancels every `pending_verification` order placed before the cutoff, in one
  transaction over rows it locks in id order. The cutoff is
  `STALE_ORDER_HOURS` (default `24`) behind the moment it is asked for. It
  names the system as the actor on its lines, and a second run finds nothing
  because the first moved the orders out of `pending_verification`.
- **Decline** is the seller's, from `awaiting_shipment` only. It puts that
  fulfillment's items back on the storefront (`sold -> for_sale` where the
  listing had sold out) and sends the whole `subtotal_cents` back.
- **Refund** is the platform's, from `awaiting_shipment`, `shipped`, or
  `delivered`. It moves no stock: the piece is with the customer, or nobody
  knows where it is.
- Both run their guard **inside** the transaction that writes, after
  `lock!`, so a second decline or refund racing the first is refused rather
  than paid twice. Both refuse a fulfillment on an order no card was approved
  for (`Fulfillment::UNCHARGED`), and both need a reason of 1–500 characters
  (`Refund::REASON_LIMIT`).

Refusals are `ActiveRecord::RecordInvalid` or `TransitionError`, so `Story`
writes them as `refused` at `info`: the seller portal re-renders its refusal
page at 422, the admin site redirects with the sentence in `flash[:alert]`,
and an ownership miss answers 404 like every other one.

## Fulfillment status (per order × seller)

Question: what are the legal `Fulfillment` status transitions?

```mermaid
stateDiagram-v2
    [*] --> awaiting_shipment
    awaiting_shipment --> shipped : seller ships (ship!: carrier + tracking)
    shipped --> delivered : customer confirms (deliver!)
    awaiting_shipment --> declined : seller pulls out (decline!)
    awaiting_shipment --> refunded : admin refunds (refund!)
    shipped --> refunded : admin refunds
    delivered --> refunded : admin refunds
    delivered --> [*]
    declined --> [*]
    refunded --> [*]
```

Source of truth: `Fulfillment::TRANSITIONS`, verified by
`test/models/fulfillment_test.rb`. `declined` and `refunded` are terminal, so
shipping after a decline and refunding twice are both refused by the table.
`Fulfillment#ship!` notifies the customer
("Order shipped") and calls `Order#roll_up_status!`; `Fulfillment#deliver!`
releases the fulfillment's held escrow (see `docs/escrow.md`) and also rolls
the order status up. Both refuse a move the table has no edge for, and `ship!`
refuses a carrier or a tracking number the seller left blank. Delivery confirmation is
the customer clicking a button on the order page — a stand-in for carrier
tracking in this prototype. A seller's own order page
(`/seller/orders/:id`) shows one fulfillment, not the whole order — see
"Vocabulary notes" in `docs/ontology.md`.
