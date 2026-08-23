---
id: RFCTR-002
type: refactor
status: open
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
