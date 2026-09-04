# Orders

Checkout, payment, fulfillment, and the sad half: cancel, sweep, decline, and
refund. Code: `app/Actions/Orders/`, `app/Actions/Fulfillment/`,
`app/Actions/Escrow/IssueRefund.php`,
`app/Http/Controllers/Shop/CheckoutController.php`,
`app/Http/Controllers/Shop/OrderPaymentController.php`,
`app/Domain/Orders/OrderStatus.php`, `app/Domain/Orders/FulfillmentStatus.php`,
`app/Domain/Fulfillment/`.

## Checkout to a paid, seller-notified order

Question: from a submitted checkout form to a seller's "item sold"
notification, what runs, and where does a guest's flow diverge from a
verified customer's?

```mermaid
sequenceDiagram
    actor Customer
    participant Checkout as CheckoutController
    participant Place as PlaceOrder
    participant Finalize as FinalizeOrder
    participant Listener as NotifySellerOfSale
    participant Pay as OrderPaymentController

    Customer->>Checkout: POST /checkout (email, shipping, card?)
    Checkout->>Place: __invoke(cart, purchaser, shipping, now)
    Place->>Place: create order, status = OrderStatus::forPlacement(purchaser)
    Place-->>Checkout: order (pending_verification | awaiting_payment)

    alt purchaser's email is verified
        Checkout->>Finalize: __invoke(order, card_number, now)
        Finalize->>Finalize: charge, status -> paid, hold escrow per fulfillment
        Finalize->>Listener: OrderPaid (after commit)
        Listener->>Listener: ItemSold notification per seller
        Checkout-->>Customer: redirect /orders/{order}
    else guest, email unverified
        Checkout->>Checkout: SendMagicLink(email, redirect_to=/orders/{order}/pay)
        Checkout-->>Customer: redirect /orders/{order} "check your email"
        Note over Customer: verifies by magic link (see identity.md), lands on /orders/{order}/pay
        Customer->>Pay: POST /orders/{order}/pay (card_number)
        Pay->>Finalize: __invoke(order, card_number, now)
        Finalize->>Finalize: charge, status -> paid, hold escrow per fulfillment
        Finalize->>Listener: OrderPaid (after commit)
        Listener->>Listener: ItemSold notification per seller
        Pay-->>Customer: redirect /orders/{order}
    end
```

Caveats: `OrderPaid` is dispatched inside the action's transaction and the
listener implements `ShouldHandleEventsAfterCommit`, so a rolled-back charge
tells nobody. A declined card sets `payment_failed` and restores the stock
`PlaceOrder` took (`ListingStatus::Sold -> ForSale` when the listing had
reached zero); the order page and `/orders/{order}/pay` both post to
`FinalizeOrder` again for a retry. `FinalizeOrder` writes one `payments` row
per attempt.

## A cart that went stale between the page and the submit

Question: a shopper's cart holds a line that is no longer for sale, sold out,
or short of stock by the time they submit — what refuses the order, and what
does the shopper see?

`App\Domain\Orders\OrderPlacementPlan::for()` is the pure decision: it takes
a list of `PlaceableLine`s (what a line asks for, against what the listing
behind it allows right now) and folds them into `isPlaceable(): bool` plus a
`blocked` list of every line standing in the way, each carrying an
`UnavailableReason` — `Removed`, `OffSale`, `SoldOut`, or `ShortStock`. It
reads nothing itself: `Cart::placementPlan()` and `Order::placementPlan()`
build the lines from their own `items.listing` relation, so the plan stays
testable with no database. `Removed` reads an admin's listing removal:
both builders pass the listing's `hasActiveRemoval()`, so a removed listing
blocks its line whatever its ordinary status says.

`PlaceOrder` builds the plan **inside its own transaction**, from listing rows
it reads **for update** (`Listing`'s `lockedForPlacement` scope: `order by id`,
`for update`). `Listing::sell()` computes the new quantity and status in PHP
from the row it read and writes the pair back, so the row has to be held from
that read until the commit: a second checkout for the same listing blocks on
the lock, and reads the quantity the first one committed instead of
overwriting it with arithmetic from a read taken before. A plan that is not
placeable throws `App\Domain\Orders\OrderPlacementRefused` with every blocked
line, instead of stopping at the first one. `FinalizeOrder` asks the same
question before a retry retakes stock (`OrderStatus::retakesStockOnRetry()`,
reached only from `payment_failed`): a decline hands a listing's stock back to
the storefront, so a retry has to find it still available before it claims
that stock again, and it takes the same rows for update — as does the restock
a decline writes.

SQLite, the prototype's development and test database, has no row lock: it runs
one write transaction at a time, so a second checkout is turned away by the
database rather than losing the update, and its query grammar compiles the
clause away. The lock is what makes a database that runs write transactions
concurrently hold the second one until the first commits, so no test here can
interleave two checkouts to show it. `ListingTest` compiles the scope
with a grammar that has the clause and asserts on it; `PlaceOrderTest` and
`FinalizeOrderTest` assert the plan is judged against rows read inside the
transaction rather than whatever the caller loaded before it.

`OrderPlacementRefused` carries its blocked lines two ways: `getMessage()`
reads as one sentence, and `refusalData()` (the `App\Domain\CarriesRefusalData`
contract) hands `Story::tell()` a `blocked` array — `listing_id`, `title`,
`reason` per line — that lands in the `order.place` or `order.pay` `refused`
log line's `data` (docs/alignment.md §2.3) without `Story` knowing what kind
of refusal it caught.

`CheckoutController::place` catches `OrderPlacementRefused` separately from
every other `DomainRuleViolation`: it re-renders the checkout page itself at
422, naming every blocked line and its reason, with the submitted form flashed
back so the shopper retypes nothing — except the card, which nothing flashes
(`ShopRequest::CARD_FIELDS`, and `bootstrap/app.php` for the two flashes the
framework does on its own: the validation redirect and a
`DomainRuleViolation`'s `back()->withInput()`). `<x-card-fields>` renders
`old('card_number')` back into the field, so a flashed number would be written
into the response body and held in session storage. A refusal that is not
about a specific line — a blocked customer — still redirects to the cart, the
page that already holds every line. `OrderPaymentController::pay` answers a
stale retry the same way, re-rendering `/orders/{order}/pay` at 422.

The cart page (`CartController::show`) reads `Cart::placementPlan()` to mark
each blocked line with its reason and disable the checkout control —
`<button disabled>`, not merely hidden or styled away — while any line is
blocked.

## Order status

Question: what are the legal `OrderStatus` transitions?

```mermaid
stateDiagram-v2
    [*] --> pending_verification : guest checkout, email unverified
    [*] --> awaiting_payment : verified customer, order placed
    pending_verification --> paid : card approved
    pending_verification --> payment_failed : card declined
    pending_verification --> cancelled
    awaiting_payment --> paid : card approved
    awaiting_payment --> payment_failed : card declined
    awaiting_payment --> cancelled
    payment_failed --> paid : retry, card approved
    payment_failed --> cancelled
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

Source of truth: `App\Domain\Orders\OrderStatus::transitions()`, verified by
`OrderStatusTest`. `Cancelled` is reached by `CancelOrder` — from the customer's
button, the admin console, or the stale sweep — and `refunded` by the roll-up
once no fulfillment is left live.

`OrderStatus::fromFulfillments()` rolls a multi-seller order up from its
**live** fulfillments — the ones that are neither `declined` nor `refunded`
(`FulfillmentStatus::isLive()`). Among those, any that has shipped or delivered
counts as "departed"; a delivered fulfillment mixed with an unshipped one still
reads `partially_shipped`, not `paid`. An order whose fulfillments are all
settled rolls up to `refunded`, and `orders.refunded_cents` carries the sum of
its refunds.

## Cancelling an order nothing has been charged for

Question: who can cancel, what happens to the stock, and what stops a paid
order from being cancelled?

```mermaid
sequenceDiagram
    actor Asker as Customer / admin / sweep
    participant Cancel as CancelOrder
    participant Listings as listings
    participant Orders as orders
    participant Listener as NotifyOfCancellation

    Asker->>Cancel: __invoke(order, now)
    Cancel->>Orders: refresh inside the transaction
    alt status releases stock on cancel (pending_verification, awaiting_payment)
        Cancel->>Listings: lockedForPlacement, restock every line
    else payment_failed
        Note over Cancel: the declined card already handed the stock back
    end
    Cancel->>Orders: status.transitionTo(cancelled)
    Cancel->>Listener: OrderCancelled (after commit)
    Listener->>Listener: PurchaseCancelled to the customer, SaleCancelled to each seller
```

Caveats: `CancelOrder` re-reads the order's status **inside** the transaction
that writes, so an order paid between the page and the submit is refused rather
than cancelled out from under the money — `OrderStatus::transitionTo()` has no
`paid -> cancelled` edge. The storefront route authorizes `view` (another
customer's order is a 404) and lets the action phrase the refusal;
`OrderPolicy::cancel` is what the button on the order page is shown by.
`OrderStatus::releasesStockOnCancel()` is the one predicate that decides
whether stock comes back: a `payment_failed` order already restocked when the
card was declined.

## The stale-order sweep

Question: what stops an abandoned guest checkout from holding stock forever?

`make sweep` runs `orders:sweep` (`App\Console\Commands\SweepOrders`), also
scheduled hourly in `routes/console.php`. It cancels every
`pending_verification` order whose `placed_at` is older than
`config('orders.stale_hours')` — `STALE_ORDER_HOURS`, default `24` — through
the same `CancelOrder` every other cancel path uses, so the stock comes back
the same way.

It is idempotent by construction: it selects `pending_verification` and leaves
`cancelled`, so a second run over the same window finds nothing. It never
touches `awaiting_payment` — a verified customer still has a card form open —
and never anything younger than the cutoff. `SweepStaleOrders` calls
`Story::asSystem()` first, so its `order.sweep` lines and the `order.cancel`
each order writes carry `actor_type: system` (docs/alignment.md §2.1).

## Fulfillment status (per order × seller)

Question: what are the legal `FulfillmentStatus` transitions?

```mermaid
stateDiagram-v2
    [*] --> awaiting_shipment
    awaiting_shipment --> shipped : seller ships (MarkShipped: carrier + tracking)
    shipped --> delivered : customer confirms (ConfirmDelivered)
    awaiting_shipment --> declined : seller declines (DeclineFulfillment: reason)
    awaiting_shipment --> refunded : admin refunds a silent seller
    shipped --> refunded : admin settles a dispute
    delivered --> refunded : admin settles a dispute
    delivered --> [*]
    declined --> [*]
    refunded --> [*]
```

Source of truth: `App\Domain\Orders\FulfillmentStatus::transitions()`,
verified by `FulfillmentStatusTest`. `MarkShipped` rolls the order status up
and dispatches `FulfillmentShipped`, which `NotifyCustomerOfShipment` turns
into the customer's "Order shipped" notification after the commit; `ConfirmDelivered` releases
the fulfillment's held escrow (see `docs/escrow.md`) and rolls the order
status up. Delivery confirmation is the customer clicking a button on the
order page — a stand-in for carrier tracking in this prototype. Every one of
these transitions also appends its row to the fulfillment's event log, below.

## The fulfillment event log and the seller's flow

Question: `fulfillments.status` says a parcel is awaiting shipment — what
does the seller know that the column does not, and where is it written?

`docs/alignment.md` §4.5 owns the flow diagram, the lane table, the two
kinds of writer, and the closed-vocabulary consequence. This section is the
PHP realization: the classes, the sequence, and the two mechanisms alignment
leaves to the stack.

A **flow** (`ffl_`) is a seller's ordered list of **steps** (`ffs_`) between
paid and shipped. A step carries a `key` unique inside the flow, the words
the seller gave it, a `position` unique inside the flow, and a
`FlowStepAction` — `none`, or `print_label` for the step that answers the
printable label page. `FulfillmentFlowSeeder` gives every seller one default
flow, *Label printed* then *Packed*. A listing may name a flow
(`listings.fulfillment_flow_id`); a listing that names none ships by its
seller's default, which is what `Fulfillment::flowInEffect()` reads.

`App\Actions\Fulfillment\AppendFulfillmentEvent` is the only writer of the
table. The transitions (`MarkShipped`, `ConfirmDelivered`,
`DeclineFulfillment`, `RefundFulfillment`) call it inside the transaction
that writes `fulfillments.status`; `FulfillmentEventKind::forStatus()` names
the kind. `CompleteFlowStep` calls it for a step, appending `step_completed`
with the step id, and with the carrier and tracking number when the step
prints a label.

One default flow per seller is a partial unique index,
`(seller_id) where is_default = 1` — SQLite and Postgres both take the clause,
and Blueprint has none, so the migration writes it as a statement.

`fulfillment_events` is unique on `(fulfillment_id, fulfillment_flow_step_id)`,
so a step completed twice is refused by the database as well as by the
action. A unique index counts each null as its own value, so the transition
rows — which name no step — are outside the constraint. The row also copies
`step_label`: a seller who later drops the step from their flow leaves the
log still saying what they did, and the foreign key nulls out rather than
cascading the history away. The panel on the order page draws the flow as it
stands now; the log keeps each step's words as they were, so a step renamed
after the fact reads one way in the panel and another in the feed, which is
what a record is for. `FulfillmentEvent::stepLabel()` refuses a completion
that kept no words.

Whether a parcel has started is read from the completions, not from the
steps: `FulfillmentProgress::hasStarted()` counts every `step_completed` row,
so removing a step the seller had already done leaves the parcel where it
was. Which steps are behind it is read against the flow as it stands, so the
panel never ticks a step that is gone.

```mermaid
sequenceDiagram
    actor Seller
    participant Step as FlowStepController
    participant Complete as CompleteFlowStep
    participant Append as AppendFulfillmentEvent
    participant Label as ShippingLabelController

    Seller->>Step: POST /seller/orders/{f}/steps/{step}
    Step->>Complete: __invoke(f, step, carrier?, tracking?, now)
    Complete->>Complete: take the fulfillment for update
    Complete->>Complete: refuse unless awaiting_shipment
    Complete->>Complete: refuse unless progress admits this step
    Complete->>Append: NewFulfillmentEvent::stepCompleted(...)
    Append-->>Complete: fulfillment_events row
    alt the step prints a label
        Step-->>Seller: redirect /seller/orders/{f}/label
        Seller->>Label: GET the printable page
    else
        Step-->>Seller: redirect /seller/orders/{f}
    end
```

Reading the log is pure. `FulfillmentProgress::of(steps, completedStepIds)`
answers which steps are behind the parcel, which is next, and whether the
flow is done; it reads the flow **as it stands now**, so an event naming a
step the seller has since removed leaves the rest of the order untouched.
`FulfillmentLane::of(status, progress)` sorts the parcel onto the desk —
`docs/alignment.md` §4.5 has the lane table.

Caveats: a step is completed only from `awaiting_shipment`, only when it is
the one in front, and only by the seller who owns the fulfillment — another
seller's fulfillment or another seller's step answers 404, and the other two
are `DomainRuleViolation`s judged inside the transaction that appends, the
way every fulfillment transition is. `SaveFulfillmentFlow` writes the whole
flow from the seller's form in one transaction, keeping the rows the form
names by id (a rename keeps the events pointing at them) and parking
surviving positions on negatives while it refills the range from zero,
because `(fulfillment_flow_id, position)` is unique and SQLite judges it row
by row.

## Decline and refund

Question: who can send a fulfillment's money back, what happens to the stock,
and what refuses a second one?

```mermaid
flowchart TD
    decline["Seller: DeclineFulfillment(reason)\nawaiting_shipment only"] --> restock["Restock this seller's lines\n(lockedForPlacement, sold -> for_sale)"]
    restock --> issue
    refund["Admin: RefundFulfillment(reason)\nawaiting_shipment | shipped | delivered"] --> nostock["No stock moves"]
    nostock --> issue["IssueRefund"]
    issue --> row["refunds row (rfd_): order, fulfillment,\napproved payment, subtotal, reason, issuer"]
    issue --> ledger["ledger_entries: refunded, -net_cents"]
    issue --> total["orders.refunded_cents += subtotal"]
    issue --> event["RefundIssued -> NotifyOfRefund"]
    event --> rollup["RollUpOrderStatus over the live fulfillments"]
```

Caveats: both actions re-read the fulfillment's status **inside** the
transaction that writes it, so "decline after ship", "ship after decline",
"refund twice", and "refund what was already declined" are all refused by
`FulfillmentStatus::transitionTo()` against the row as it stands at write time,
not as the page rendered it. `IssueRefund` refuses an order no card ever
cleared (`OrderStatus::hasBeenPaid()`), which is what stops a refund on an
unpaid order — the fulfillments exist from the moment the order is placed.
`refunds` has `unique(fulfillment_id)`: the amount is always the whole
subtotal, so a second row would be a second full refund.

A decline is the seller's and restores stock; a refund is the admin's and does
not — the pieces are with the customer, or with a seller who is not answering
for them. The seller who declined is not notified of their own decision;
an admin refund tells both sides.
