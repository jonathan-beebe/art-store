---
id: FEAT-017
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-017: Order lifecycle back half — cancel, stale sweep, seller decline, admin refund

## Problem
`Order` has a `cancelled` status nothing reaches: no cancel route, no stale-order sweep (guest orders hold stock indefinitely), no seller decline, no admin cancel or refund, no reversal ledger entry type, and no `refunds` table. `docs/alignment.md` §4 fixes the shared state machines, the `refunds` row, the `refunded` ledger entry and its three timings, the sweep, the sad paths, and the surfaces.

## Goal
The whole money-and-stock lifecycle of an order — happy and sad — is reachable, refused where it must be, and visible on every site.

## Outcome
The order and fulfillment state machines in §4.1 are enforced by the enums and model rules; the customer can cancel an unpaid order; `make sweep` (a rake task) cancels stale `pending_verification` orders older than `STALE_ORDER_HOURS`; a seller can decline an `awaiting_shipment` fulfillment with a reason and stock returns; an admin can cancel an unpaid order and refund any fulfillment with a reason; every refund writes a `refunds` row and a `refunded` ledger entry so the balance fold matches §4.2 in all three timings; every sad path in §4.3 has a test; the surfaces in §4.4 exist on the storefront, seller portal, and admin site; the counterpart is notified; `docs/orders.md` and `docs/escrow.md` show the new diagrams.

## Why it matters
Refunds and declines decide whether sellers trust the platform and whether the ledger reconciles; the prototypes compete on exactly this.

## Discovery notes
Rich models: `Order#cancel!`, `Fulfillment#decline!(reason:, by:)`, `Fulfillment#refund!(reason:, by:)`, `Order.sweep_stale(before:)` inside `transaction` with `lock!`, refusals as a domain error the controllers map to a refusal page; the roll-up of order status from live fulfillments as one method. The admin order/fulfillment detail pages come with FEAT-019; sequence the two so the actions have a page to live on.

## Related work
- docs/alignment.md §4
- FEAT-019
- FEAT-003 (commerce core)

## Working

### What landed

**Schema.** `refunds` (`rfd_`, `id: :string`) with exactly §4.1's columns —
`order_id`, `fulfillment_id`, `payment_id`, `amount_cents`, `reason`,
`issued_by_type`, `issued_by_id`, `created_at` (no `updated_at`; nothing edits
a refund). `orders.refunded_cents` carries the running sum. `ledger_entries`
gains the `refunded` entry type (enum only — the column is a bare string).
`fulfillments` gains `declined` and `refunded`, `orders` gains `refunded`;
both are enum values, so neither needed a column change.

**State machines.** `Order::TRANSITIONS` adds `paid|partially_shipped|
shipped|delivered → refunded`, and `refunded` is terminal beside `cancelled`
— which is what refuses a card at a cancelled or refunded order.
`Fulfillment::TRANSITIONS` adds `awaiting_shipment → declined|refunded`,
`shipped → refunded`, `delivered → refunded`, with both new states terminal —
which is what refuses ship-after-decline and a second decline or refund.
`Order#rolled_up_status` rejects `Fulfillment#reversed?` rows first: no live
fulfillment left reads `refunded`, and the rest of the ladder
(`delivered`/`shipped`/`partially_shipped`/`paid`) runs over the live ones, so
one shipped fulfillment beside one declined one reads `shipped`.

**The writes.** `Order#cancel!(by:)`, `Order.sweep_stale(before:)`,
`Fulfillment#decline!(reason:, by:, at:)`,
`Fulfillment#refund!(reason:, by:, at:)`, `Refund.issue(...)`,
`LedgerEntry.refund(...)`. `decline!` and `refund!` share one private
`Fulfillment#reverse_to!` that opens the `refund.issue` story, opens the
transaction, `lock!`s, `validate!`s, moves the status, writes the refund, runs
the caller's block (stock restore and the notification), and rolls the order
up. Both guards run inside the transaction that writes, after the lock, as
§4.1 requires. No service objects; the two controllers per site are four
lines each.

**Sweep.** `Order.sweep_stale(before:)` locks the matching rows
(`pending_verification`, `placed_at` before the cutoff, `order(:id).lock`) in
one transaction and cancels each. `Order.stale_before(at:)` reads
`config.x.orders.stale_hours` (`STALE_ORDER_HOURS`, default 24, set in the new
`config/initializers/orders.rb` beside the magic-link initializer's pattern).
`make sweep` runs `orders:sweep[AS_OF]`, matching `make payouts`.

**Ledger.** See "The one real design decision" below.

**Surfaces.** Customer: Cancel button on a cancellable order, the refunded
total beside the status, and a line per refund under the fulfillment it
reverses with the amount and the reason. Seller: a Decline form (reason,
maxlength 500) on `awaiting_shipment` fulfillments, an outcome block replacing
the shipment block on a reversed one, and a new Movements table on the
earnings page listing every ledger entry — which is where a `refunded`
movement shows. Admin: Cancel beside the status on `/admin/orders/:id`, a
Refunds table on the order and on the fulfillment, a Refund form on
`/admin/fulfillments/:id`, and a Refund link in each fulfillment row (on the
order page and on the fulfillments list) pointing at that form. `refunded` and
`declined` appear in both lists' status filters automatically, from the enums.

**Notifications.** `Notification.fulfillment_declined` (customer),
`Notification.fulfillment_refunded` (customer and seller),
`Notification.order_cancelled` (customer and every seller on the order, on an
admin cancel only).

**Logging.** `order.cancel` (`will`/`did`, `actor_type` from `Current` —
`customer` from the storefront, `admin` from the admin site),
`order.sweep` (`will`, a `doing` per order, `did` with `order_count`),
`fulfillment.decline`, `refund.issue` (`refund_id`, `fulfillment_id`,
`amount_cents`, `reason`), and `ledger.write` at `debug` for the `refunded`
entry. `Story#doing` is new — §2.2 allows the phase and the sweep is the first
caller. Every refusal is `refused` at `info`, because `TransitionError` and
`ActiveRecord::RecordInvalid` are already in `Story::REFUSALS`.

### The one real design decision: the ledger cannot fold by entry type

§4.2's three timings are not satisfiable by any formula over per-type totals.
Write H, R, P, F for the `held`, `released`, `paid_out`, `refunded` totals.
Timing 1 (refund before release) has H=net, R=0, F=−net and must give
held=0; timing 2 (refund after release) has H=net, R=net, F=−net and must
also give held=0. Solving `held = H − R + a·F` gives a=1 from the first and
a=0 from the second. There is no such a.

Two ways out. Writing a synthetic `released` entry on a pre-release refund
makes the two cases identical and keeps the type fold — but §4.2 enumerates
that timing's entries as exactly "`held` +net, `refunded` −net" and says
"nothing releases", so an extra row would contradict the contract.

Taken instead: **fold per fulfillment**. `LedgerEntry.balance` groups by
`(fulfillment_id, entry_type)` (`balances_by_seller` adds `seller_id`) — still
one query each — and `LedgerEntry::Balance.fold` sums one part per
fulfillment:

```
still_held = released == 0
held      = held − released + (still_held ? refunded : 0)
available = released + paid_out + (still_held ? 0 : refunded)
paid_out  = −paid_out
```

A refund lands where that fulfillment's money stands. Entries naming no
fulfillment — a payout — fold as a group of their own under the same rule,
which is what puts the payout's negative straight into `available`.

The three timings then read, for one $450.00 sale (net $405.00):

| Timing | Entries | held | available | paid out |
| --- | --- | --- | --- | --- |
| Before release | `held +40500`, `refunded −40500` | $0.00 | $0.00 | $0.00 |
| After release | + `released +40500` | $0.00 | $0.00 | $0.00 |
| After payout | + `paid_out −40500` | $0.00 | **−$405.00** | $405.00 |

"Available drops" in timing 2 is visible with a second released sale in the
same balance: `test/models/ledger_entry_test.rb` builds that case and asserts
the other sale's net survives. The negative in timing 3 carries with no
bookkeeping of its own: `Balance#payable?` is already `available > 0`, so a
payout of ≤ 0 writes no `payouts` row and no `paid_out` entry, and the payout
run reads the whole history (`occurred_by(period.ends_at)`) rather than one
week at a time, so a later sale nets against it. Two payout tests pin that.

### Decisions on ambiguities

1. **`issued_by_type` is not a polymorphic association.** §4.1 fixes the
   column's values as `seller` | `admin`; Rails' polymorphic `belongs_to`
   would store `Seller`/`Admin` and `constantize` the column. Overriding
   `polymorphic_name` on `Seller` would also change `notifications.recipient_type`,
   which the messaging code and its log payload read. So `Refund` stores the
   two strings through an `enum` and keeps `issued_by_id` as a plain column
   with no foreign key. `Refund.issue` reads the name off
   `by.model_name.singular`, so a `Seller` and an `Admin` both work with no
   case of their own.
2. **A refund needs a payment, so an uncharged fulfillment is refused.**
   §4.3's "refund an unpaid order's fulfillment → refused" is enforced by
   `Fulfillment#must_be_charged` against `Order#charged?` (`paid`,
   `partially_shipped`, `shipped`, `delivered`, `refunded`) rather than by
   querying for an approved payment. Same answer — an order reaches those
   statuses only through an approved card — and it costs no query, which
   matters because `Fulfillment#refundable?` is read once per row on the
   admin fulfillments list, whose `count_queries` guard is pinned.
3. **The refund form lives on `/admin/fulfillments/:id`, and the order page's
   fulfillment rows link to it.** §5 says the action attaches at both. A
   reason field is a textarea, and a textarea inside a table cell repeated per
   row is worse than a link; the row gets a "Refund" cell that appears only
   while the move is open. `docs/admin.md` records it.
4. **`cancel!` takes `by:` and notifies only for an admin.** §4.4 lists
   "seller ← admin refund/cancel; customer ← admin refund/cancel" and does not
   list a customer's own cancel or the sweep, so neither writes a
   notification. The sweep passes `by: :system`.
5. **No `cancelled_at` column.** §4.1 fixes the `refunds` columns exactly and
   names no timestamp for a cancel; `updated_at` already records when. The
   reason for a decline or refund lives on the `refunds` row, which is where
   the seller and customer pages read it from
   (`Fulfillment#refund_reason`).
6. **`Order.sweep_stale` names the system on its lines.** `Current` is reset
   per request and per test, so a sweep run outside a request would otherwise
   write lines with no `actor_type`. It wraps its story in
   `Current.set(actor_type: "system", actor_id: nil)`, which makes §2.3's
   "`actor_type` says which" true wherever the sweep is called from.
7. **`orders.refunded_cents` was added by rewriting `CreateOrders` in place**
   (§1 allows it) rather than as a new migration; `refunds` is a new migration
   (`20260824000101`). Rewriting alone is not enough to regenerate
   `db/schema.rb` — `db:migrate` loads the existing schema first and then runs
   only what is pending — so `src/db/schema.rb` was deleted and rebuilt from
   the migrations. Anyone repeating that trick needs the same step.

### Deliberately left out

- **`/admin/accounting` and `/admin/ledger` (FEAT-020).**
  `Fulfillment.fees_earned_cents` and `Fulfillment.fees_refunded_cents` fold
  the forgone platform fee over `settled` (has a `held` entry) split by
  `live`/`reversed`, and are tested. The pages that report them are FEAT-020's.
  The ledger browser's `refunded` filter value works from the enum the moment
  that page exists. Recorded as known gap 13 in `docs/review.md`.
- **Seeds.** `db/seeds/order_history.rb` still walks placement → delivery →
  payout and shows no decline or refund. Known gap 14, with a suggested next
  step.
- **Partial refunds.** §4.1 fixes a refund at the whole fulfillment subtotal
  for this cut. Recorded as known gap 5 (it replaces the old "no order
  cancellation" gap, which this ticket closes).

### Deviations from the contract

None on §4's states, rules, sad paths, surfaces, or §2.3's payloads. Two
notes:

- §4.2 describes the balance as "still a fold" — it is, but over fulfillments
  rather than entry types, for the reason argued above. The entries written
  are exactly the ones §4.2 enumerates.
- §4.2's timing 2 says "available balance drops"; with a single fulfillment in
  the ledger it drops to zero, not below. A negative available balance appears
  in timing 3, which is where the contract's "carried and netted against the
  next payout" bites. Both are tested.

### Numbers

Before: 882 runs, 2956 assertions, 0 failures, 1706/1706 lines.
After: 969 runs, 3338 assertions, 0 failures, 1859/1859 lines (100%).
`make lint` clean, 245 files inspected. `make sweep` and
`make sweep AS_OF=...` both run; `make fresh` reseeds.

### Fix-up

A reviewer found three defects in this ticket's commit.

1. **`Shop::CancellationsController` answered 404 for a paid order it owned.**
   `create` pre-checked `order.cancellable?` and raised
   `ActiveRecord::RecordNotFound` when it was false, so a customer's own paid
   order and a stranger's order both came back 404 — collapsing two of §4.3's
   sad paths into one. Rewritten to match `Admin::CancellationsController` and
   `Seller::DeclinesController`: ownership scoping (`order_of_customer`) still
   404s a stranger's order, and `order.cancel!` runs unconditionally so a
   state refusal comes back as a `TransitionError` the controller rescues into
   `flash[:alert]`, with `order.cancel`'s `refused` line at `info` in the log.
   `cancellable?` is now unused in this controller (it still backs the "Cancel
   this order" button's visibility on the order page). Swept every controller
   this commit added or touched — `admin/cancellations_controller.rb`,
   `admin/fulfillments_controller.rb`, `admin/orders_controller.rb`,
   `admin/refunds_controller.rb`, `seller/declines_controller.rb`,
   `seller/earnings_controller.rb`, `shop/cancellations_controller.rb`,
   `shop/orders_controller.rb` — for the same
   `raise ActiveRecord::RecordNotFound unless <domain predicate>` shape; none
   of the other seven had it.

2. **`Fulfillment#ship!` and `#deliver!` validated their guard outside the
   transaction, with no lock.** `decline!`/`refund!` (via `reverse_to!`)
   already ran `lock!` then `validate!` then `update!` inside `transaction
   do`; `ship!` and `deliver!` ran `validate!` first and locked nothing, so a
   `ship!` racing a `decline!` could read the pre-decline status, pass its
   guard, and overwrite a `declined` row back to `shipped`. Moved the lock and
   the guard inside the transaction in both methods, in the same order
   `reverse_to!` uses.

3. **`make migrate` on an existing database silently misses a rewritten
   migration.** `orders.refunded_cents` was added by rewriting `CreateOrders`
   in place rather than as a new migration (§1 allows this), but a rewritten
   migration keeps its original version stamp, so a developer database that
   already ran it never re-runs it and never gets the column.
   `README.md`'s Database section now says `make fresh` is the way to pick up
   a rewritten migration on an existing database.

Tests added: `shop/cancellations_controller_test.rb` — the paid-order refusal
(flash text, order unchanged), the refusal's log payload (`refused` at
`info`), and a stranger's paid order still 404ing, replacing the test that
pinned the 404. `fulfillment_test.rb` — a stale in-memory read racing a
decline (for `ship!`) and racing a refund (for `deliver!`), pinning that the
lock takes effect before the guard runs.

Numbers after the fix-up: 973 runs, 3357 assertions, 0 failures, 1861/1861
lines (100%). `make lint` clean, 245 files inspected.
