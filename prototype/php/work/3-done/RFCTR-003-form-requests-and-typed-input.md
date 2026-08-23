---
id: RFCTR-003
type: refactor
status: resolved
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

## Working

### The shape every write route now has

```
route binds the model
  └─ FormRequest::authorize()   policy for the bound model → Response (404 on a denial)
  └─ FormRequest::rules()       reads the bound model / the resolved visitor where a rule depends on them
  └─ FormRequest::to*()         hands the controller a domain object or a typed scalar
       └─ controller            redirect decisions, then the action
```

| Route | Request | Hands over |
| --- | --- | --- |
| `shop.cart.add` | `Shop\AddToCartRequest` | `quantity()` |
| `shop.checkout.place` | `Shop\CheckoutRequest` | `toShippingAddress()`, `toPurchaser($visitor)`, `email()`, `cardNumber()` |
| `shop.order.pay.submit` | `Shop\PayOrderRequest` | `cardNumber()` |
| `auth.customer.send`, `auth.seller.send` | `Auth\SendMagicLinkRequest` | `email()`, `redirectTo()` |
| `seller.listings.store`, `.update` | `Seller\ListingRequest` | `toDraft()` |
| `seller.listings.status` | `Seller\ChangeListingStatusRequest` | `status()` |
| `seller.orders.ship` | `Seller\MarkShippedRequest` | `carrier()`, `trackingNumber()` |

`Shop\ShopRequest` is the storefront base, mirroring `ShopController`: it
exposes `visitor()` so `CheckoutRequest::rules()` can decide the card rule and
`PayOrderRequest::authorize()` can name the actor the storefront never signs
in on a guard.

### Authorization moved into four form requests

`ListingRequest`, `MarkShippedRequest`, `ChangeListingStatusRequest`, and
`PayOrderRequest` call the policy in `authorize()`; the matching
`$this->authorize()` / `authorizeVisitor()` lines are gone from
`ListingController::update`, `ShipmentController`, `ListingStatusController`,
and `OrderPaymentController::pay`. `Gate::inspect()` returns the same
`Response` the controller raised, so `denyAsNotFound()` still answers 404, and
three new tests assert the ordering directly: an invalid form posted at another
seller's or customer's row answers 404 with no validation errors in the
session. `ListingController::edit` and `OrderPaymentController::show` keep
their controller-side `authorize()`; they are GETs with no form request.

### `Rule::enum()->only([])` admits everything

An archived listing has no transitions, and `Enum::isDesirable()` falls through
to `true` when `only` is empty — so the rule would have accepted `for_sale` for
an archived listing and the refusal would have come from the domain as a
`default` error key instead of a `status` one.
`ChangeListingStatusRequest::rules()` answers an empty transition list with
`['prohibited']`. Confirmed by removing the branch: two dataset cases fail with
"A listing cannot move from archived to archived."

### `PayOrderRequest` validates the card before the controller redirects

RFCTR-002 put `authorize` → `elsewhere()` → validate in that order. A form
request runs before the controller, so the order is now `authorize` →
validate → `elsewhere()`. The behavior RFCTR-002 named is unchanged (an
unverified visitor posting a card still gets the sign-in redirect, another
customer still gets 404 first); what changed is that an unverified visitor
posting an *empty* card now gets a `card_number` validation error rather than
the sign-in redirect. No form produces that submission — the pay page redirects
an unverified visitor away before rendering the field. Making the rule
`Rule::requiredIf(OrderPayment::isPayableBy(...))` would restore it exactly, at
the cost of the same domain predicate in the rule and the redirect.

### Value objects

- `ShippingAddress` has a private constructor and `ShippingAddress::to(...)`;
  all three call sites (`CheckoutRequest`, `CommerceTestCase`,
  `OrderHistorySeeder`) pass named arguments. `attributes()` mirrors
  `ListingDraft::attributes()` and `PlaceOrder` spreads it into `Order::create`.
- `Purchaser::forCheckout(...)` replaces `App\Domain\Shop\CheckoutPurchaser`,
  which is deleted; its two tests moved into `PurchaserTest`.
- `Customer::isVerified()` is new, next to `isAnonymous()`. It replaced
  `CheckoutController::isVerified()` and the two
  `email_verified_at !== null` reads in `OrderPaymentController`, and it is
  what `CheckoutRequest`'s `Rule::requiredIf` asks.

### Tests

Sidecars added for all seven form requests; `tests/SidecarsTest.php` lost three
exceptions (`ListingRequest`, `MarkShippedRequest`, `ShippingAddress`).
Field-level validation moved out of the controller sidecars into the request
sidecars as datasets, so each rule is asserted once, where it lives:

| Moved from | To |
| --- | --- |
| `Seller\ListingControllerTest` (2 datasets) | `Requests\Seller\ListingRequestTest` |
| `Seller\ShipmentControllerTest` (2 tests) | `Requests\Seller\MarkShippedRequestTest` (1 dataset) |
| `Seller\ListingStatusControllerTest` (1 dataset) | `Requests\Seller\ChangeListingStatusRequestTest` |
| `Shop\CheckoutControllerTest` (1 test) | `Requests\Shop\CheckoutRequestTest` |
| `Shop\OrderPaymentControllerTest` (1 test) | `Requests\Shop\PayOrderRequestTest` |
| `Auth\{Customer,Seller}LoginControllerTest` (3 tests) | `Requests\Auth\SendMagicLinkRequestTest` |

The `to*()` accessors are tested directly off `Request::create(...)`, which
needs no middleware; the rules are tested through HTTP.

### Numbers

- Tests: 538 → 586 (1237 → 1359 assertions), full suite green.
- PHPStan: 19 → 4. Every `mixed`-offset error is gone, along with the
  `argument.type` errors that followed from them in the two auth controllers,
  `CheckoutController`, and `ListingRequest`. The remaining 4 are in files this
  ticket does not touch: `ToggleFavorite`, `OrderStatus::fromFulfillments`,
  and the two generic-key return types in `DashboardController` and
  `ListingActivityController`.
- Pint: clean.

### Left out

- `StorefrontController::submitted()` stays a private helper. It reads two
  query parameters on a GET route that validates nothing; a form request there
  would carry an empty `rules()`.
- The write routes that submit no fields (`shop.favorites.toggle`,
  `shop.order.delivered`, `seller.notifications.read`,
  `shop.account.notifications.read`, `seller.earnings.payout`, both sign-outs)
  get no form request. There is no input to type and no policy call to move:
  each already binds its model and authorizes in the controller.
- `Purchaser::email` stays `?string`. Making it non-nullable would remove the
  last nullable-argument question around the type, and it belongs with
  RFCTR-005's domain polish alongside `orders.email`.

### Files touched that the ticket does not name

- `app/Models/Customer.php` — `isVerified()`.
- `tests/Pest.php` — binds the three `app/Http/Requests` directories to their
  base classes.
- `docs/architecture.md` — the Coordination row, a paragraph in
  **Authorization** on where a write route authorizes, the Pest binding list,
  and the suite count (538 → 586).
- `docs/review.md` — the request sidecars added to eight mapping rows.
