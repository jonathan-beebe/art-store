---
id: RFCTR-002
type: refactor
status: resolved
created: 2026-08-23
---

# RFCTR-002: Policies, scoped route bindings, and a seller base controller

## Problem
Authorization is hand-rolled in nine places and no `app/Policies` exists. Seller controllers take `string $listing` / `string $fulfillment` and re-query through the signed-in seller (`app/Http/Controllers/Seller/ListingController.php:50-53`, `ListingStatusController.php:18`, `ListingActivityController.php:20-21`, `NotificationController.php:20`, `ShipmentController.php:17`, `OrderController.php:38`) so route model binding never fires on the seller side while the storefront binds (`Listing $listing`, `Order $order`). The storefront checks ownership with `abort_unless(... === $this->visitor()->id, 404)` (`Shop/ShopController.php:54-59`, `Shop/AccountController.php:27`) and the nested route `routes/shop.php:32-33` re-checks parent/child by hand at `DeliveryConfirmationController.php:17` instead of `scopeBindings()`. `auth('seller')->user()` is dereferenced in twelve places across eight controllers with no null handling (19 PHPStan errors), and `ListingController::ownedListing` duplicates `ListingStatusController`'s inline lookup while the storefront already has a `ShopController` base with `visitor()`. Blade gates the ship and deliver forms on status by hand (`seller/orders/show.blade.php:67`, `shop/order.blade.php:70`) and the controllers re-assert the same rule (`ShipmentController.php:19`, `DeliveryConfirmationController.php:18`). `app/Http/Controllers/Controller.php` is empty though it exists to host `AuthorizesRequests`. `OrderPaymentController::pay` (`:37-39`) validates before it authorizes, and `show` (`:22-26`) redirects to login where `pay` 404s for the same condition.

## Goal
Ownership and state rules are declared once in policies, and both sites read the same way: bind the model, authorize, act.

## Outcome
- Policies exist for `Listing`, `Fulfillment`, `Order`, and `Notification`; seller controllers receive bound models and call `authorize()`; storefront controllers do the same for orders and notifications, keeping the documented 404 (not 403) for another customer's resources.
- The `{order}/fulfillments/{fulfillment}` route uses scoped bindings and the hand check is gone.
- A `SellerController` base mirrors `ShopController`: `seller()` returns a non-null `Seller`, and no controller dereferences `auth('seller')->user()` directly.
- Blade uses `@can` for the ship and deliver forms, backed by the same policy methods the controllers authorize with.
- `OrderPaymentController` authorizes before validating, and `show`/`pay` answer an unverified visitor the same way.
- Existing seller-A-vs-seller-B and customer-A-vs-customer-B tests still pass; new tests cover a fulfillment id from another order (404), confirming delivery twice (404 at HTTP, refusal at the action, escrow released once), and the policies directly.
- PHPStan errors from nullable guard users are gone.

## Why it matters
Policies and route binding are the parts of Laravel a reviewer looks for first; hand-rolled ownership reads as a port from another framework.

## Discovery notes
- `Response::denyAsNotFound()` from a policy keeps the 404 behavior `ShopController.php:50-53` argues for.
- Policies can be guard-aware: the seller guard's user is a `Seller`, the customer guard's is a `Customer`; the anonymous storefront visitor is resolved by middleware, so `Gate::forUser($visitor)` or passing the visitor explicitly may be needed where no guard is authenticated.
- Route parameters on the seller side bind by id; the storefront keeps `{listing:slug}`.
- BUG-001 removes the state pre-flight checks in `ShipmentController`/`DeliveryConfirmationController`; this ticket runs after it and adds the `@can` view gating on top of policy methods (`ship`, `confirmDelivery`).

## Related work
- BUG-001
- MAINT-001

## Working

### Where each question is answered

| Question | Answered by | On a miss |
| --- | --- | --- |
| Is this row the actor's? | policy `view` / `update` / `pay` / `markRead`, via `authorize()` | `Response::denyAsNotFound()` → 404 |
| Is this fulfillment part of this order? | `->scopeBindings()` on the nested route | 404 from binding |
| Is this move legal right now? | the action, through the domain enum's `transitionTo()` | `DomainRuleViolation` → BUG-001's `back()->withErrors()` |
| Is the form worth offering? | policy `ship` / `confirmDelivery`, via `@can` / `@visitorCan` | the form is not rendered |

The split is the reason a policy carries two shapes of method. `view` and
`update` answer ownership alone, so `authorize()` never turns a state refusal
into a 403 error page — BUG-001 chose "the form's page with the reason on it"
for a double-click, and that answer survives here. `ship` and `confirmDelivery`
fold `canTransitionTo()` in on top of ownership so the views have one
expression to gate on instead of a hand-written status comparison; nothing
authorizes them, and they carry a plain `Response::deny()` for the state half
so the 404 stays reserved for ownership.

### How the HTTP tests' expectations for a second ship/confirm changed

They did not. `POST /seller/orders/{id}/shipment` on a shipped fulfillment and
`POST /orders/{o}/fulfillments/{f}/delivered` on a delivered one still redirect
back and render the domain's message, exactly as BUG-001 left them. What
changed is that neither form is on the page to submit twice by accident: the
`@can`/`@visitorCan` gates hide them.

The ticket's Outcome asked for "404 at HTTP" on a second confirm. That was
written before BUG-001 landed and traded that 404 for the rendered message;
BUG-001's decision stands, and the new coverage is the action-level half
(`ConfirmDeliveredTest`: the second call throws, `delivered_at` keeps the first
timestamp, and exactly one `released` ledger entry exists).

### The storefront has no guard user

`Authenticate::using('seller')` calls `Auth::shouldUse('seller')`, so on
`/seller/*` the seller is the request's default guard user and `$this->authorize()`
and `@can` both read them with no extra plumbing. The storefront visitor is a
`customers` row resolved from a cookie by middleware and is not authenticated
on any guard, so both sides of the storefront name them explicitly:

- Controllers: `ShopController::authorizeVisitor()`, one line over
  `Gate::forUser($this->visitor())->authorize(...)`. Used for every storefront
  authorization, including `/account`, where the customer guard *is*
  authenticated — one shape everywhere beats two.
- Views: `@visitorCan('confirmDelivery', $fulfillment)`, a `Blade::if` in
  `AppServiceProvider` that runs the same policies against `customer()`. A
  bare `@can` there would read the (empty) default guard and hide every form.

### Route model binding on the seller side

Every seller route now binds by id. Two knock-ons:

- `ListingActivityController` used the `withEventCounts` scope, which only
  exists on a query. `Listing::loadEventCounts()` runs the same three
  aggregates against a model already in hand; both read one private
  `Listing::eventCounts()` so they cannot drift.
- `Seller\OrderController::show` eager-loads the seller's own order items onto
  the bound fulfillment instead of filtering in the query.
- `Shop\OrderController::show` adds `fulfillments.order` to its eager loads:
  `FulfillmentPolicy::confirmDelivery` reads the order's customer, and one
  extra query beats one per fulfillment.

### OrderPaymentController

`show` and `pay` now run the same two checks in the same order before either
does anything else: authorize the order (404 for another customer), then
`elsewhere()` — back to the order once it is past paying, to sign-in while the
address is unverified. `pay` validates the card number after that, so an
unverified visitor posting a card gets the sign-in redirect the GET gives them
rather than the old 404.

### Tests added (28)

- `app/Policies/{Listing,Fulfillment,Order,Notification}PolicyTest.php` — 17
  tests: ownership allowed, the 404 shape of an ownership denial, the state
  window each offering ability opens, and that a state refusal is not a 404.
- `Shop\DeliveryConfirmationControllerTest` — a fulfillment id from another
  order is a 404 (the scoped binding; the hand check it replaced had no test).
- `Shop\OrderControllerTest` — no delivery confirmation offered once delivered.
- `Shop\OrderPaymentControllerTest` — an unverified visitor posting a card is
  sent to sign in; another customer is refused on GET and POST alike.
- `Actions\Fulfillment\ConfirmDeliveredTest` — confirming twice throws and
  releases escrow once.
- `App\Models\ListingTest` — the queried and loaded event counts agree.

Not duplicated: BUG-001 already covers the double confirm at HTTP.

### Left out

- `Shop\ListingController`'s `abort_unless(ListingAvailability::isOnStorefront(...), 404)`
  stays a controller check. It asks whether a listing is on the storefront at
  all, which is the same answer for every visitor; a policy would imply it
  turns on who is asking.
- `ListingRequest` and `MarkShippedRequest` validate before their controller
  authorizes, because a form request runs first by design. Both ownership tests
  post valid forms and still get 404. Moving ownership into the form requests'
  `authorize()` belongs with RFCTR-003.
- `ListingPolicy` has no `create`: a seller creating a listing owns it by
  construction, and `CreateListing` takes the seller.

### Numbers

- Tests: 510 → 538 (1185 → 1237 assertions), full suite green.
- PHPStan: 44 → 19 errors. All 25 removed were the nullable guard user and its
  knock-ons (`Cannot call method x() on App\Models\Seller|null`, the two
  `method.nonObject` errors that followed from it in `EarningsController` and
  `OrderController`, and the `mixed` offsets in `ListingStatusController` and
  `OrderPaymentController` that typed reads replaced). The remaining 19 sit in
  files this ticket does not touch — the auth controllers, `CartController`,
  `CheckoutController`, `ListingRequest`, `ToggleFavorite`, `OrderStatus` —
  plus two generic-key return types in `DashboardController` and
  `ListingActivityController` that predate it.
- Pint: clean.

### Files touched that the ticket does not name

- `app/Models/Listing.php` — `loadEventCounts()` and the shared aggregate list,
  forced by binding the activity route's model.
- `app/Providers/AppServiceProvider.php` — the `@visitorCan` directive.
- `tests/Pest.php` — binds `app/Policies` sidecars to `CommerceTestCase`.
- `docs/architecture.md` — the Authorization subsection, `app/Policies` in the
  layer table, the Pest binding list, and the stale suite count (485 → 538;
  BUG-001 had already left it behind).
- `docs/review.md` — the policy sidecars added to five mapping rows.
