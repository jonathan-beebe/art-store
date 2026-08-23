---
id: BUG-005
type: bug
status: resolved
created: 2026-08-23
---

# BUG-005: Four routes read, branch, then write across separate transactions

## Problem
`app/sites/shop/routes/order-payments.ts:43,46,47,50` — `loadCustomerOrder`
reads (43); `markAwaitingPayment` opens transaction 1 and re-reads and writes
the status (46); the handler branches on `awaitsCard(order.status)` (47)
using the value that transaction returned; `finalizeOrder` opens transaction
2 and re-reads the order to charge it (50). The status the branch at 47
trusts is committed and stale by the time 50 acts on it. One user action runs
across three transactions.

`app/sites/shop/routes/fulfillments.ts:15,20,22` — `loadCustomerOrder`
computes `canConfirmDelivery` per fulfillment (15), the handler branches on
it (20), and `confirmDelivered` then opens its own transaction (22) where
`transitionFulfillment` throws `TransitionError` on an illegal move. Nothing
catches it here (compare `seller/routes/orders.ts:96-99`, which does), so a
double-click on "confirm delivered" is a 500 rather than a redirect, and the
escrow release read-then-write straddles two transactions.

`app/sites/seller/routes/listings.ts:175,176,181` — `activeListingRemoval({ db }, listing.id)`
(175) is read on the plain handle, the handler branches on
`isBlockedByRemoval` (176), and `changeListingStatus` opens its own
transaction (181) that re-reads only the status. An admin removal committed
between 175 and 181 does not stop the listing going back on sale. This is the
same rule as "a removed listing cannot go back on sale" — enforced only in
this route (`sites/seller/routes/listings.ts:175-178`;
`actions/listings/change-listing-status.ts:17-32` knows nothing about
removals). `sites/admin/routes/moderation.ts:37-41` states the opposite
doctrine explicitly in its own comment: "The refusal is the action's to
decide — a route that asked whether a removal could be lifted would be
holding the rule twice."

`app/sites/shop/routes/messages.ts:94,102` — `openConversation` commits a
`conversations` row (94), then `postMessage` opens a second transaction (102)
which can refuse the post for a blocked customer
(`actions/messaging/post-message.ts:76-78`). The catch at 107-111 flashes the
error and redirects, leaving an empty conversation row behind that the
customer's inbox now shows with no messages in it.

A related but smaller case: `app/sites/shop/routes/orders.ts:41` checks
`if (found === null || !isCancellable(found.order.status)) return renderNotFound(reply)`,
then `cancelOrder` (`actions/orders/cancel-order.ts:22`) runs
`transitionOrder(order.status, 'cancelled')`, which refuses the same set —
two statements of one rule, the route's copy existing only to answer 404
instead of 500.

## Goal
Each of these four user actions reads, branches, and writes inside one transaction.

## Outcome
- Each of the four user actions (pay, confirm delivery, change listing status, open-conversation-plus-first-message) is one transaction.
- A double-submitted delivery confirmation redirects with a flash instead of 500ing.
- A removed listing cannot be put back on sale through any caller of `changeListingStatus`.
- A refused first message leaves no empty conversation behind.

## Why it matters
"Any read-then-write runs inside a single transaction" — a rule stated
directly against all four of these. "Business rules live in core, actions
apply them; a route holding an enforcement the action does not is the rule
living in one layer only" — the removal check is the clearest instance,
directly contradicting the doctrine stated in the admin moderation route's
own comment. "Core returns explicit results rather than throwing for expected
business cases" applies to the uncaught `TransitionError` on the delivery
double-submit.

## Discovery notes
Pay route: one `runInTransaction` around the `markAwaitingPayment` /
`finalizeOrder` pair, threading the transacted context into both actions.

Delivery confirmation: wrap the gate and `confirmDelivered` in one
transaction and catch `TransitionError` into a flash, the way the seller ship
route already does.

Listing status: move the removal read and the `isBlockedByRemoval` check into
`changeListingStatus` itself, throwing `TransitionError` with that message —
the route already catches `TransitionError` and flashes it, so the handler
shrinks. `availableListingTransitions` stays where it is for drawing the
form.

Conversation open + first message: one `runInTransaction` around both. Note
that joining without a savepoint means a caught inner error inside an outer
transaction does not undo the inner writes — let the error escape the
transaction and catch it outside, so the outer transaction is the one that
rolls back.

`isCancellable`: acceptable as a fast 404 if deliberate, but say so in a
comment naming `transitionOrder` as the authority, or drop the route-level
check and let the route catch `TransitionError` the way the seller and admin
routes do.

Files expected to touch: `app/sites/shop/routes/order-payments.ts`,
`app/sites/shop/routes/fulfillments.ts`, `app/sites/seller/routes/listings.ts`,
`app/actions/listings/change-listing-status.ts`,
`app/sites/shop/routes/messages.ts`, `app/sites/shop/routes/orders.ts`.

This ticket depends on BUG-004 landing first: both touch the identity/action
family's shape and the transaction wrapping here is cleaner once every action
BUG-004 touches already takes `ActionContext`.

## Related work
- 04-data-layer.md — "The pay route runs one user action across three transactions"
- 04-data-layer.md — "Delivery confirmation gate sits outside the transaction, and a double-submit 500s"
- 04-data-layer.md — "Removal gate for a status change is read outside the status-change transaction"
- 03-core-shell.md — "'A removed listing cannot go back on sale' is enforced only in the seller route"
- 04-data-layer.md — "Opening a conversation and posting the first message are two transactions"
- 03-core-shell.md — "`isCancellable` checked in the route and again by the transition table"
- BUG-004 (land before this ticket)
- BUG-003 (adjacent transaction-boundary ticket; no ordering dependency)

## Working

Re-validated the problem against the current tree: BUG-004 had already
landed, and all four line-number claims in the ticket still matched the code
(`markAwaitingPayment`/`finalizeOrder` already took `ActionContext` and
already called `runInTransaction`, which just needed to be shared across a
route-level branch rather than opened twice).

Changes:
- `app/sites/shop/routes/order-payments.ts` — new `chargeVerifiedOrder`
  helper wraps `markAwaitingPayment` + the `awaitsCard` branch + `finalizeOrder`
  in one `runInTransaction`, following `checkout.ts`'s `checkOutCart` idiom.
  Returns `null` when the order lands somewhere unpayable, and the route turns
  that into the existing 404.
- `app/sites/shop/routes/fulfillments.ts` — dropped the route's own
  `canConfirmDelivery` pre-check (the field stays on `find-customer-order.ts`
  for the template's button); the route now calls `confirmDelivered` and
  catches `TransitionError` into a flash + redirect, matching
  `seller/routes/orders.ts`'s `ship`/`refuseShipment` pattern. `confirmDelivered`
  was already one transaction internally, so this closes the two-transaction
  gap (stale gate read vs. the action's own read) rather than restructuring
  the action.
- `app/actions/listings/change-listing-status.ts` — now reads
  `activeListingRemoval` on the transacted context and throws `TransitionError`
  (same message the route used) before calling `transitionListing`, so any
  caller refuses a removal-blocked status change. `app/sites/seller/routes/listings.ts`'s
  `changeStatus` lost its own removal read/check; `activeListingRemoval` and
  `availableListingTransitions` stay imported there for `show()`/`index()`
  rendering.
- `app/sites/shop/routes/messages.ts` — the `/art/:slug/questions` handler now
  runs `openConversation` + `postMessage` inside one `runInTransaction`,
  letting `postMessage`'s `TransitionError` (empty body or a blocked customer)
  escape the transaction uncaught and be caught at the route, so the whole
  transaction — including the conversation insert — rolls back. The
  fulfillment-message route (`openConversation` only, no first message) was
  untouched — it isn't the two-transaction case the ticket names.
- `app/sites/shop/routes/orders.ts` — added a one-line comment on the cancel
  route's `isCancellable` check naming `transitionOrder` as the authority, per
  the ticket's "acceptable as a fast 404 if deliberate, but say so" option.
  No behavior change; `cancelOrder`'s own refusal already covers the same set.

Left alone: `finalize-order.ts`, `mark-awaiting-payment.ts`, and
`confirm-delivered.ts` action signatures — all three already accepted a
transacted `ActionContext` and needed no change.

Test changes: two existing `fulfillments.test.ts` tests changed from
asserting a bare 404 to asserting a 302 redirect with a flash, since dropping
the route-level gate moves that refusal through `TransitionError` (matches the
ticket's stated outcome — "redirects with a flash instead of 500ing" — and the
ship-route precedent). Added: a `change-listing-status.test.ts` action test
that a `sold` listing under an active removal refuses `for_sale` even though
`sold → for_sale` is otherwise a legal transition (isolates the removal rule
from the ordinary transition-table refusal); a matching route-level test in
`seller/routes/listings.test.ts`; two `messages.test.ts` tests asserting a
refused first question (empty body, blocked customer) leaves zero
`conversations` rows.

Verified: `npm run check` green after two rounds of waiting out concurrent
workers' mid-flight state elsewhere in the tree (`app/plugins/error-pages.ts`,
`app/delivery/outbox-notification-delivery.ts`, the three site `index.test.ts`
404-page suites) — none of those files are in this ticket's territory. Test
count 1442 → 1446 (4 new: 2 messages.ts, 1 change-listing-status.test.ts, 1
seller listings.test.ts); coverage 99.54%/96.74%/99.81% line/branch/function,
above the 95/90 gate.
