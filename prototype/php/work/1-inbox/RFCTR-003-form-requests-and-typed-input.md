---
id: RFCTR-003
type: refactor
status: open
created: 2026-08-23
---

# RFCTR-003: Form requests and typed input for every write route

## Problem
`app/Http/Requests/` holds two classes; six controllers validate inline and index the resulting `mixed` array: `Shop/CheckoutController.php:89-102` (nine fields with a `$isVerified ? 'required' : 'nullable'` card rule, plus a private `shippingAddress(array)` mapper at `:107-118` that restates seven keys), `Auth/CustomerLoginController.php:29-32`, `Auth/SellerLoginController.php:26-28`, `Shop/OrderPaymentController.php:37`, `Shop/CartController.php:32`, `Seller/ListingStatusController.php:20-24` (which maps enum cases to strings for `Rule::in` and re-hydrates with `ListingStatus::from`). At PHPStan level max these are 14 "Cannot access offset on mixed" errors. `ShippingAddress` (`app/Domain/Orders/ShippingAddress.php:7-15`) has seven positional strings and three positional call sites; `CheckoutPurchaser` (`app/Domain/Shop/CheckoutPurchaser.php`) is a static factory for `Purchaser` living in a different namespace from the type it builds. `PlaceOrder.php:30-36` flattens the address by hand where `ListingDraft::attributes()` shows the pattern. `CheckoutController.php:87` documents `array<string, string>` while `shipping_line2` is nullable.

## Goal
Request input crosses into the application as typed objects built by form requests, with no array indexing in controllers.

## Outcome
- Every write route is backed by a `FormRequest` with `rules()` and, where a domain object is the payload, a `to*()` accessor returning it (shipping address, purchaser, card number, quantity, email + redirect, listing status).
- Conditional requirements use rule objects (`Rule::requiredIf`, `Rule::enum(...)->only(...)`); the enum status is read with `$request->enum()`.
- `ShippingAddress` and `Purchaser` have named constructors for their real construction sites, and the order attributes are produced by the value object; `CheckoutPurchaser` is gone.
- Controllers contain no `validate()` calls and no private mapping helpers; the PHPStan `mixed`-offset errors are gone.
- Validation tests assert through the HTTP layer as today; validation-error cases that repeat become datasets.

## Why it matters
Form requests are the Laravel answer to typed input; the storefront's most important form is the one still doing it by hand.

## Discovery notes
- `app/Http/Requests/Seller/ListingRequest.php` (`toDraft()`, `$this->string()`, `$this->integer()`) is the house pattern.
- `CheckoutRequest::rules()` can read the visitor from the request attributes the middleware sets to decide whether the card is required.
- `OrderStatus::forPlacement(Purchaser)` shows the "named constructor on the type" style for `Purchaser::forCheckout(...)`.

## Related work
- RFCTR-002
- MAINT-001
