---
id: BUG-004
type: bug
status: open
created: 2026-08-23
---

# BUG-004: Checkout 500s on a stale cart line instead of listing what is wrong

## Problem
`Listing#take_stock!` raises `ArgumentError` when a cart line exceeds stock or the listing has left the storefront, and neither `Order.place` nor `Shop::CheckoutsController` rescues it, so a customer whose cart went stale gets a 500. The cart page keeps the checkout button live on a sold-out or off-sale line. Node's `planOrderPlacement` returns every blocked line with a reason (`removed | off_sale | sold_out | short_stock`) and re-renders the list with 422; PHP `BUG-001` and Node `BUG-003` fixed the same fault.

## Goal
A customer learns everything wrong with their cart at once, before and at checkout, and never sees a 500 for it.

## Outcome
Checkout re-renders (422) with every blocked line and its reason; the cart page marks each blocked line and disables the checkout control while any exists; the pay page refuses an order that went stale between placement and payment with the same shape; tests cover each reason, the multi-line case, and the former 500.

## Why it matters
A 500 on checkout is a lost sale and the one bug all three prototypes were supposed to have fixed.

## Discovery notes
A PORO `OrderPlacement` (or a class method on `Order`) that folds the cart into placeable-or-blocked-lines keeps the decision testable; `take_stock!` then only runs on a placeable plan inside the transaction.

## Related work
- prototype/node BUG-003, prototype/php BUG-001
- FEAT-005 (storefront)

## Working

### What was built
- `app/models/order_placement.rb`: a PORO, `OrderPlacement`. Built from
  anything responding to `listing`/`quantity` (a `CartItem` at checkout, an
  `OrderItem` at a retried charge), so one classifier serves both callers.
  `OrderPlacement.reason_for(line)` is the pure classification function
  (`removed` > `sold_out` > `off_sale` > `sold_out` on zero quantity >
  `short_stock`, in that ranked order), matching Node's `unavailableReason`
  and the reason names exactly. `OrderPlacement.plan(items).ok?` /
  `.blocked_lines` is the instance API; `.notice_for(reason)` and
  `.log_payload(blocked_lines)` are shared formatters used by the views and
  the log lines.
- `Order.place`: the plan is now the first statement inside the placement
  transaction (`transaction do plan = OrderPlacement.plan(cart.items.includes(:listing)); raise ActiveRecord::Rollback unless plan.ok? ... end`),
  read against the listing rows as they stand at that moment. `take_stock!`
  (via `snapshot`) only runs on a placeable plan. A blocked plan rolls the
  transaction back and `Order.place` returns an unsaved order carrying
  `blocked_lines` (a new non-persisted `attr_accessor`, same shape as the
  existing "invalid shipping returns an unsaved order" path) rather than
  letting `Listing#take_stock!`'s `ArgumentError` escape as a 500.
- `Order#pay!`: a retry that reclaims stock (`payment_failed -> paid`, the
  only charge that calls `take_stock!` again — the first charge and any
  decline never do) now builds a plan from the order's own items
  (`restock_plan`) before touching stock, inside the same transaction as the
  charge. A blocked plan rolls the charge back — no payment row, no stock
  move, order left exactly where it was — and `#pay!` returns `self` with
  `blocked_lines` set, the same shape as `Order.place`'s refusal.
- `Shop::CheckoutsController#create`: checks `@order.blocked_lines.present?`
  before the existing `persisted?`/`incomplete` check and re-renders `:show`
  at 422 (`reject_unavailable`), listing every blocked line and its reason
  via a shared partial (`shop/_blocked_lines_notice`).
- `Shop::CartsController#show`: runs `OrderPlacement.plan` read-only against
  the cart's own items (no transaction, nothing at stake) and hands the view
  a `listing_id -> reason` map. The cart view marks each blocked `<li>` with
  `data-reason` and a "no longer available" style notice, and swaps the
  Checkout link for a disabled-looking `<span data-checkout-disabled>` while
  any line is blocked.
- `Shop::OrderPaymentsController#create`: same pattern as checkout —
  `order.pay!` then a 422 re-render of `:show` (`reject_unavailable`) when
  `blocked_lines` is present, reusing the same `_blocked_lines_notice`
  partial as checkout.
- `Listing#actively_removed?`: added, always `false`.

### The `removed` reason and FEAT-021
No admin removal exists in this prototype yet — no `listing_removals` table,
no admin action, nothing that stands over a listing independent of its
`status`. The ticket asked for the reason to be implemented and driven by
"whatever off-the-storefront predicate exists today"; the only such
predicate is status (`Listing::ON_STOREFRONT`), which the `off_sale`/`sold_out`
branches already own, so reusing it for `removed` would make `removed`
permanently shadow `off_sale` rather than being a distinct, testable branch.
Instead: `OrderPlacement.reason_for` ranks `removed` first exactly as Node
does, driven by a new `Listing#actively_removed?` predicate that always
returns `false` until FEAT-021 backs it with a `listing_removals` row (not
created here, per the brief). The reason is exercised by a unit test that
builds an `OrderPlacement::Line` directly with `removed: true` — the same way
the reachable reasons are exercised, minus the trip through `Listing`, since
nothing in today's app can set that flag to `true`. `removed` is unreachable
end-to-end today; that is the intended, documented gap FEAT-021 closes.

### Decisions on ambiguity
- **Refusal shape**: reused the existing "`Order.place` returns an unsaved
  order rather than raising" convention (already there for an invalid
  shipping address) instead of introducing a new exception type. `blocked_lines`
  is a plain `attr_accessor`, not a database column. `Story.tell`'s `REFUSALS`
  list (which auto-logs `refused` on a raise) is untouched — both refusal
  paths call `story.refused(...)` directly, the same way the pre-existing
  incomplete-address refusal already does.
- **GET /checkout**: left unchanged — no pre-flight availability check on the
  GET. The Outcome only asks for the POST to re-render with blocked lines;
  the cart page is what warns a shopper before they reach checkout. A stale
  cart reaching GET /checkout still shows the normal form; POSTing it is what
  triggers the 422.
- **First charge vs. retry**: only a retry from `payment_failed` to `paid`
  calls `take_stock!` again (`Order::RELEASES_STOCK` — the first charge and
  any decline never move stock into a claimed state), so only that path
  needed the plan/refusal treatment. `Shop::CheckoutsController#charge` (the
  first, same-request charge right after placement) is untouched — there is
  no gap between placement and that charge for stock to go stale in.
- **Cart page disabling**: rendered as a `<span data-checkout-disabled
  aria-disabled="true">` rather than a `disabled` attribute on an `<a>` (links
  have no such attribute); `data-checkout-disabled` is the test hook.

### Numbers
- Tests: 780 -> 807 runs (2608 -> 2687 assertions), 0 failures, 0 errors.
- Coverage: 100% line (1580/1580).
- `make check` (rubocop-rails-omakase -> assets -> test) green.
- One rubocop offense fixed along the way: `Style/RedundantReturn` on the
  last branch of `OrderPlacement.reason_for`.
- Observed one order-dependent flake in `Shop::ConversationsControllerTest`
  on a single full-suite run (`RecordNotFound` looking up a conversation);
  it passed in isolation and on two subsequent full-suite reruns. Unrelated
  to this ticket's files — not chased further here.
