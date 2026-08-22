# Escrow and payouts

Per-seller money tracked in `ledger_entries`, settled weekly into `payouts`.
Code: `app/domain/escrow/`, `app/actions/orders/finalize_order.rb`,
`app/actions/fulfillments/confirm_delivered.rb`,
`app/actions/escrow/run_weekly_payout.rb`, `lib/tasks/payouts.rake`.

## Ledger entry types through hold → release → payout

Question: what event writes each `ledger_entries.entry_type`, and what does
each one do to a seller's balance?

```mermaid
flowchart LR
    finalize["FinalizeOrder\norder -> paid"] -->|"held: +net"| held(("held"))
    held --> confirm["ConfirmDelivered\nfulfillment -> delivered"]
    confirm -->|"released: +net"| released(("released"))
    released --> run["RunWeeklyPayout\n(payouts:run)"]
    run -->|"paid_out: -available"| paidout(("paid_out"))
```

Caveats: `amount_cents` is signed — `held` and `released` are positive,
`paid_out` is negative (`Domain::Escrow::LedgerMovement.payout` negates it).
A seller's balance (`Domain::Escrow::LedgerBalance.from`) folds every entry:
`held = held_total − released_total`, `available = released_total +
paid_out_total` (adding a negative number nets it down), `paid_out =
−paid_out_total`. Only a seller with `available > 0` (`payable?`) gets a
payout row. The fee itself is computed once, in `Orders::PlaceOrder`, and
stored on the `fulfillments` row (`fee_cents`, `net_cents`) — `FinalizeOrder`
and `ConfirmDelivered` move `fulfillment.net` through escrow rather than
recomputing it.

## `payouts:run`

Question: how does the weekly payout task turn released escrow into
`payouts` rows?

```mermaid
sequenceDiagram
    participant CLI as payouts:run[AS_OF]
    participant Run as Escrow::RunWeeklyPayout
    participant Period as Domain::Escrow::PayoutPeriod
    participant Ledger as ledger_entries
    participant Payouts as payouts

    CLI->>Period: ending_before(as_of)
    CLI->>Run: call(as_of:)
    Run->>Ledger: occurred_by(period.ends_at), grouped by seller_id
    Run->>Run: LedgerBalance.from(movements) per seller
    loop each seller where balance.payable?
        Run->>Payouts: create!(seller, period, amount: balance.available)
        Run->>Ledger: create!(entry_type: paid_out, amount: -available, occurred_at: period.ends_at)
    end
    Run-->>CLI: [Payout, ...]
    CLI-->>CLI: print period label and seller totals
```

Caveats: the `paid_out` entry is dated at `period.ends_at`, not the moment the
task runs — that is what makes re-running the same period a no-op (the money
is already inside `occurred_at <= period.ends_at` on the next run, so it nets
to zero and `payable?` is false). `payouts` also has a unique index on
`(seller_id, period_start)`. `PayoutPeriod.ending_before` is pure —
Monday–Sunday, the most recently completed week as of `as_of`. The seller
portal's "Run weekly payout now" button (`Seller::PayoutsController#create`)
calls the same action for every seller, not just the one signed in.

## Worked example

A $100.00 listing, one unit, one seller, no other activity that period.

| Step | Action | `ledger_entries` written | Seller balance |
| --- | --- | --- | --- |
| Order placed, card approved | `Orders::FinalizeOrder`: `Fee.platform($100.00)` = $10.00, `Fee.net($100.00)` = $90.00 (computed at placement); `LedgerMovement.hold($90.00)` | `held +9000` | held $90.00, available $0.00 |
| Customer confirms delivery | `Fulfillments::ConfirmDelivered`: `LedgerMovement.release($90.00)` | `released +9000` | held $0.00, available $90.00 |
| `payouts:run` (period ends) | `Escrow::RunWeeklyPayout`: balance is payable, pays $90.00; `LedgerMovement.payout($90.00)` | `paid_out -9000` | available $0.00, paid out $90.00 lifetime |

`Fee.platform` is 10% of the item subtotal (`Domain::Escrow::Fee::PLATFORM_PERCENT`),
taken off the top; `net = subtotal − fee`. Both figures are computed once in
`Orders::PlaceOrder` and stored on the `fulfillments` row (`fee_cents:
1000`, `net_cents: 9000`) — `FinalizeOrder` and `ConfirmDelivered` read
`fulfillment.net` rather than recomputing it.
