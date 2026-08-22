# Orders

Checkout, payment, and fulfillment. Code: `app/actions/orders/`,
`app/actions/fulfillments/`, `app/controllers/shop/checkouts_controller.rb`,
`app/controllers/shop/order_payments_controller.rb`,
`app/domain/orders/order_status.rb`, `app/domain/orders/fulfillment_status.rb`.

## Checkout to a paid, seller-notified order

Question: from a submitted checkout form to a seller's "Item sold"
notification, what runs, and where does a guest's flow diverge from a
verified customer's?

```mermaid
sequenceDiagram
    actor Customer
    participant Checkout as Shop::CheckoutsController
    participant Place as Orders::PlaceOrder
    participant Finalize as Orders::FinalizeOrder
    participant Notify
    participant Pay as Shop::OrderPaymentsController

    Customer->>Checkout: POST /checkout (email, shipping, card?)
    Checkout->>Place: call(cart:, purchaser:, shipping:, now:)
    Place->>Place: create order, status = OrderStatus.for_placement(purchaser)
    Place-->>Checkout: order (pending_verification | awaiting_payment)

    alt purchaser's email is verified
        Checkout->>Finalize: call(order:, card_number:, now:)
        Finalize->>Finalize: charge, status -> paid, hold escrow per fulfillment
        Finalize->>Notify: item_sold(order, fulfillment.net) per seller
        Checkout-->>Customer: redirect /orders/:id
    else guest, email unverified
        Checkout->>Checkout: SendMagicLink(email, redirect_to: /orders/:id/pay)
        Checkout-->>Customer: redirect /orders/:id, "check your email"
        Note over Customer: verifies by magic link (see identity.md), lands on /orders/:id/pay
        Customer->>Pay: POST /orders/:id/pay (card_number)
        Pay->>Pay: MarkAwaitingPayment (pending_verification -> awaiting_payment)
        Pay->>Finalize: call(order:, card_number:, now:)
        Finalize->>Finalize: charge, status -> paid, hold escrow per fulfillment
        Finalize->>Notify: item_sold(order, fulfillment.net) per seller
        Pay-->>Customer: redirect /orders/:id
    end
```

Caveats: a declined card sets `payment_failed` and restores the stock
`PlaceOrder` took (`Domain::Orders::OrderStock` -> `sold -> for_sale` when the
listing had reached zero). A retry — guest or signed-in — posts back to the
same `POST /orders/:id/pay`, which is why `Shop::OrderPaymentsController#create`
calls `MarkAwaitingPayment` before `FinalizeOrder` on every hit: the call is a
no-op once the order is already `awaiting_payment`. `FinalizeOrder` writes one
`payments` row per attempt, so two declines followed by an approval leave
three rows on the order.

## Order status

Question: what are the legal `OrderStatus` transitions?

```mermaid
stateDiagram-v2
    [*] --> pending_verification : guest checkout, email unverified
    [*] --> awaiting_payment : verified customer, order placed
    pending_verification --> awaiting_payment : email verified
    pending_verification --> cancelled
    awaiting_payment --> paid : card approved
    awaiting_payment --> payment_failed : card declined
    awaiting_payment --> cancelled
    payment_failed --> paid : retry, card approved
    payment_failed --> payment_failed : retry, card declined again
    payment_failed --> cancelled
    paid --> partially_shipped : one fulfillment shipped, at least one not
    paid --> shipped : every fulfillment shipped
    partially_shipped --> shipped
    shipped --> delivered : every fulfillment delivered
    delivered --> [*]
```

Source of truth: `Domain::Orders::OrderStatus::TRANSITIONS`, verified by
`test/domain/orders/order_status_test.rb`. `cancelled` has no route to it from the UI in this
prototype — the transition exists in the domain but no action calls it. A
guest order cannot jump straight from `pending_verification` to `paid`: the
table has no such edge, so `FinalizeOrder` on an unverified order raises
`Domain::TransitionError` — this is the one place the Rails design departs
from the PHP spike, where `pending_verification -> paid` was legal.
`OrderStatus.from_fulfillments` rolls a multi-seller order up from its
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

Source of truth: `Domain::Orders::FulfillmentStatus::TRANSITIONS`, verified by
`test/domain/orders/fulfillment_status_test.rb`. `Fulfillments::MarkShipped` notifies the
customer ("Order shipped") and calls `Orders::RollUpOrderStatus`;
`Fulfillments::ConfirmDelivered` releases the fulfillment's held escrow (see
`docs/escrow.md`) and also rolls the order status up. Delivery confirmation is
the customer clicking a button on the order page — a stand-in for carrier
tracking in this prototype. A seller's own order page
(`/seller/orders/:id`) shows one fulfillment, not the whole order — see
"Vocabulary notes" in `docs/ontology.md`.
