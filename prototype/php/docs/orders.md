# Orders

Checkout, payment, and fulfillment. Code: `app/Actions/Orders/`,
`app/Actions/Fulfillment/`, `app/Http/Controllers/Shop/CheckoutController.php`,
`app/Http/Controllers/Shop/OrderPaymentController.php`,
`app/Domain/Orders/OrderStatus.php`, `app/Domain/Orders/FulfillmentStatus.php`.

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
    participant Notify
    participant Pay as OrderPaymentController

    Customer->>Checkout: POST /checkout (email, shipping, card?)
    Checkout->>Place: __invoke(cart, purchaser, shipping, now)
    Place->>Place: create order, status = OrderStatus::forPlacement(purchaser)
    Place-->>Checkout: order (pending_verification | awaiting_payment)

    alt purchaser's email is verified
        Checkout->>Finalize: __invoke(order, card_number, now)
        Finalize->>Finalize: charge, status -> paid, hold escrow per fulfillment
        Finalize->>Notify: itemSold(order, fulfillment.net()) per seller
        Checkout-->>Customer: redirect /orders/{order}
    else guest, email unverified
        Checkout->>Checkout: SendMagicLink(email, redirect_to=/orders/{order}/pay)
        Checkout-->>Customer: redirect /orders/{order} "check your email"
        Note over Customer: verifies by magic link (see identity.md), lands on /orders/{order}/pay
        Customer->>Pay: POST /orders/{order}/pay (card_number)
        Pay->>Finalize: __invoke(order, card_number, now)
        Finalize->>Finalize: charge, status -> paid, hold escrow per fulfillment
        Finalize->>Notify: itemSold(order, fulfillment.net()) per seller
        Pay-->>Customer: redirect /orders/{order}
    end
```

Caveats: a declined card sets `payment_failed` and restores the stock
`PlaceOrder` took (`ListingStatus::Sold -> ForSale` when the listing had
reached zero); the order page and `/orders/{order}/pay` both post to
`FinalizeOrder` again for a retry. `FinalizeOrder` writes one `payments` row
per attempt.

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
    delivered --> [*]
```

Source of truth: `App\Domain\Orders\OrderStatus::transitions()`, verified by
`OrderStatusTest`. `Cancelled` has no route to it from the UI in this
prototype — the transition exists in the domain but no action calls it.
`OrderStatus::fromFulfillments()` rolls a multi-seller order up from its
fulfillments: any fulfillment that has shipped or delivered counts as
"departed"; a delivered fulfillment mixed with an unshipped one still reads
`partially_shipped`, not `paid`.

## Fulfillment status (per order × seller)

Question: what are the legal `FulfillmentStatus` transitions?

```mermaid
stateDiagram-v2
    [*] --> awaiting_shipment
    awaiting_shipment --> shipped : seller ships (MarkShipped: carrier + tracking)
    shipped --> delivered : customer confirms (ConfirmDelivered)
    delivered --> [*]
```

Source of truth: `App\Domain\Orders\FulfillmentStatus::transitions()`,
verified by `FulfillmentStatusTest`. `MarkShipped` notifies the customer
("Order shipped") and rolls the order status up; `ConfirmDelivered` releases
the fulfillment's held escrow (see `docs/escrow.md`) and rolls the order
status up. Delivery confirmation is the customer clicking a button on the
order page — a stand-in for carrier tracking in this prototype.
