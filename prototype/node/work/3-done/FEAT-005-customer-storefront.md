---
id: FEAT-005
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-005: Customer storefront — browse, favorite, cart, guest checkout, pay, orders

## Problem
`/` is a placeholder. Customers need to browse hand-made art, favorite and cart pieces anonymously, check out as a guest with email verification before the card, pay with the fake card (success and failure), watch their order ship, confirm delivery, and cancel before payment.

## Goal
A first-time visitor goes from the home page to a paid order without creating an account up front, and the whole flow reads as bright, open, and about the art.

## Outcome
- Home: paged grid of `for_sale`, un-removed listings with large imagery; search by text and filter by medium.
- Listing page `/art/:slug`: image, title, seller's shop name, price, description, published FAQs (FEAT-007 fills them; render an empty state now), favorite toggle, add to cart; a view event is recorded; `sold` shows as sold out; draft / archived / removed answer 404.
- Favorites page; cart page with quantities, remove, totals.
- Checkout: email + shipping address; a verified signed-in customer also enters the card on the same form and lands on the order page paid; a guest gets a magic link (debug alert) whose redirect is `/orders/:id/pay`, where the card form appears after verification.
- Pay page: 4242 pays; a decline shows the reason and a retry form with the stock returned; a blocked customer cannot check out and is told so.
- Orders list and order page with per-seller fulfillments, carrier + tracking once shipped, a confirm-delivery button, and a cancel button while cancellable.
- Account page with notifications and mark-as-read; sign-out.
- Integration tests walk guest checkout, signed-in checkout, decline-then-retry, cancel, and the 404 cases.

## Why it matters
The customer flow is the demo; guest checkout with verify-before-card is the ordering a hosted payment element will need in the real product.

## Discovery notes
Reference: `prototype/rails/src/app/controllers/shop/**`, `app/views/shop/**`, `app/domain/shop/**`, and `docs/orders.md`. Theme: white background, generous spacing, one accent, images first, the site name small.
- All rules come from FEAT-003 actions and predicates; the route decides nothing about the domain. Search / paging are pure helpers under `app/core/shop/`.
- The storefront `preHandler` from FEAT-002 supplies `currentCustomer`; a blocked customer is a core predicate, render a notice.
- Touch only `app/sites/shop/**`, `app/core/shop/**`, and one registration line in `app/app.ts`. FEAT-004 / FEAT-006 / FEAT-008 run in parallel — commit with an explicit pathspec.

## Related work
- `prototype/rails/work/3-done/FEAT-005-customer-storefront.md`
- `__local__/retro.md` items 3 and 5.

## Working

### Routes

All of these live in `app/sites/shop/`. Everything under `storefrontRoutes`
inherits `resolveCustomerIdentity`, so a customer row exists on every request.

| Method | Path                                             | File                               | Notes                                             |
| ------ | ------------------------------------------------ | ---------------------------------- | ------------------------------------------------- |
| GET    | `/`                                              | `routes/home.ts`                   | Grid of `for_sale`, un-removed listings; `q`      |
|        |                                                  |                                    | searches title/description/medium, `medium`       |
|        |                                                  |                                    | filters, `page` pages at 12                       |
| GET    | `/art/:slug`                                     | `routes/listings.ts`               | Records a `view`; 404 for draft / archived /      |
|        |                                                  |                                    | removed                                           |
| POST   | `/art/:slug/favorite`                            | `routes/favorites.ts`              | Toggles; returns to the referer when it is local  |
| GET    | `/favorites`                                     | `routes/favorites.ts`              |                                                   |
| GET    | `/cart`                                          | `routes/carts.ts`                  |                                                   |
| POST   | `/cart/:slug`                                    | `routes/carts.ts`                  | Blocked customers refused; a sold-out piece       |
|        |                                                  |                                    | redirects with an alert                           |
| POST   | `/cart/:slug/remove`                             | `routes/carts.ts`                  | POST, not DELETE: an HTML form sends no other     |
|        |                                                  |                                    | verb                                              |
| GET    | `/checkout`                                      | `routes/checkout.ts`               | Empty cart redirects to `/cart`                   |
| POST   | `/checkout`                                      | `routes/checkout.ts`               | Verified buyer pays here; a guest leaves with a   |
|        |                                                  |                                    | magic link                                        |
| GET    | `/orders`                                        | `routes/orders.ts`                 |                                                   |
| GET    | `/orders/:id`                                    | `routes/orders.ts`                 | Per-seller fulfillments, tracking,                |
|        |                                                  |                                    | confirm-delivery, cancel, retry card              |
| POST   | `/orders/:id/cancel`                             | `routes/orders.ts`                 | 404 once the order is past cancelling             |
| GET    | `/orders/:id/pay`                                | `routes/order-payments.ts`         | Behind `requireVerifiedCustomer`;                 |
|        |                                                  |                                    | `markAwaitingPayment` on every hit                |
| POST   | `/orders/:id/pay`                                | `routes/order-payments.ts`         | Blocked customers refused                         |
| POST   | `/orders/:id/fulfillments/:fulfillmentId/delivered` | `routes/fulfillments.ts`           |                                                   |
| POST   | `/account/notifications/:id/read`                | `routes/notifications.ts`          |                                                   |
| GET    | `/account`                                       | `app/sites/auth/sign-in-routes.ts` | FEAT-002's route, extended through the new        |
|        |                                                  |                                    | `accountView` option                              |

`GET/POST /login`, `POST /logout` stay as FEAT-002 registered them.

### What exists

**Core** (`app/core/shop/`, pure, sidecar tested, no database):

| Module                      | Exports                                                                           |
| --------------------------- | --------------------------------------------------------------------------------- |
| `listing-search.ts`         | `ListingSearch`, `parseListingSearch`, `searchLikePattern`                        |
| `listing-page.ts`           | `ListingPage`, `listingPage({ requested, size, totalCount })`                     |
| `checkout-form.ts`          | `CheckoutForm`, `parseCheckoutForm`, `missingCheckoutParts`, `isCheckoutComplete` |
| `checkout-purchaser.ts`     | `purchaserForCheckout`                                                            |
| `shop-name.ts`              | `shopName({ shopName, email })`                                                   |
| `status-label.ts`           | `statusLabel`                                                                     |
| `day-label.ts`              | `dayLabel`                                                                        |
| `blocked-shopper-notice.ts` | `blockedShopperNotice`                                                            |

**Read-only queries** (`app/sites/shop/queries/`): `find-storefront-listings`
(`countStorefrontListings`, `findStorefrontListings`, `findStorefrontMedia`,
`toStorefrontListing`), `find-listing-by-slug`, `find-listing-on-storefront`,
`find-favorite-listings` (+ `isListingFavorited`), `find-customer-orders`,
`find-customer-order`, `find-customer-notifications` (+
`findCustomerNotification`).

**Site helpers** (`app/sites/shop/`): `shop-page.ts` (`shopPage`,
`renderNotFound`), `storefront-customer.ts`, `refuse-blocked-customer.ts`,
`customer-order.ts`, `decline-notice.ts`, `checkout-fields.ts`,
`customer-account-view.ts`, `storefront-fixtures.ts` (test fixtures).

**Views** (`app/sites/shop/views/`): `layout`, `home`, `listing`, `favorites`,
`cart`, `checkout`, `orders`, `order`, `pay`, `account`, `login`, `not-found`,
and `partials/listing-card`, `partials/card-fields`, `partials/decline-notice`.

### Decisions

- **Templates cannot import, so `shopPage(data)` hands every page the same
  helpers** — `formatCents`, `listingImageSource`, `shopName`, `statusLabel`,
  `dayLabel` — plus a default `searchTerm` for the header's search box. A route
  reads `reply.render('cart', shopPage({ title: 'Cart', ... }))`.
- **`signInRoutes` gained an `accountView(request)` option.** FEAT-002 owns
  `/account` for all three sites; the storefront's version needs notifications,
  and a second `/account` route would collide. The option is additive and every
  other site keeps the old page.
- **`findListingOnStorefront` is the one place a slug becomes a visible
  listing.** The listing page, the favorite toggle, and add-to-cart all need
  "read it, read its removals, ask `isOnStorefront`"; consolidating it also
  gives them `isPurchasable` already answered, so no route holds the predicate's
  third argument. Removing a cart line deliberately uses the plain
  `findListingBySlug`: a piece that left the storefront is exactly the one a
  customer wants out of their cart.
- **An incomplete checkout form renders 422 rather than redirecting.** The flash
  is a one-request cookie with no `flash.now`, and a redirect would throw away
  seven typed fields, so the page carries its own error block. FEAT-002's
  redirect-with-flash still fits the one-field sign-in form.
- **`SHIPPING_FIELDS` drives the form and the parse.** The view loops over it and
  `shippingFromForm` reads the body back through it, so a field cannot exist on
  one side only.
- **Blocking is a route-level `preHandler` factory**,
  `refuseBlockedCustomer(destination)`, so the visitor lands back where they
  were: the listing for add-to-cart, `/cart` for checkout, the order for paying.
  The listing and cart pages also render the notice and drop the buttons.
- **The header carries no cart or notification counts.** They would need a
  request-scoped lookup on every page including `/account`, which is registered
  outside the storefront plugin. The nav links stay plain.
- **Removal is `POST /cart/:slug/remove`.** An HTML form sends GET or POST, and
  there is no client JavaScript to fake a DELETE.
- **The 404 page is one page for every miss** — an unknown slug, a draft, a
  removed listing, someone else's order — so nothing on the storefront reveals
  whether a thing exists.

### Deviations from the Rails spike

- `Domain::Shop::Page` becomes one function returning the whole computed page
  (`offset`, `count`, `isFirst`, `nextNumber`, …) rather than a value object with
  methods, which is what an EJS template can use.
- `CheckoutForm#complete?` and `#missing_parts` become
  `isCheckoutComplete` / `missingCheckoutParts`, and the missing list includes
  `email`, so one list drives the error block.
- Rails had no moderation: the browse query drops listings with an active
  removal, `/art/:slug` answers 404 for them, and a blocked customer is refused
  at add-to-cart, checkout, and pay.
- `cancelled` is reachable — `POST /orders/:id/cancel` with a button on the order
  page while `isCancellable`.
- `shop_name_of` and `status_label` were helpers; they are pure core functions
  passed into the template instead.
- Theme: white, `max-w-6xl`, generous spacing, small `Art Store` wordmark, one
  accent (amber-600/700) on buttons and links, images first, no client
  JavaScript.

### Verified

- `npm run typecheck` clean; `eslint app/sites/shop app/core/shop app/sites/auth`
  clean (`complexity` 8, `max-depth` 3).
- This ticket's tests: **102 in 17 sidecar files** — 34 across eight
  `app/core/shop/*.test.ts`, 68 across nine `app/sites/shop/routes/*.test.ts`
  (home 8, listings 9, favorites 8, carts 9, checkout 7, orders 10,
  order-payments 7, fulfillments 5, notifications 5). All pass.
- Whole suite at the time of the commit: **964 tests, 959 pass**. The five
  failures are FEAT-006's in-flight admin work (`app/plugins/page-views.test.ts`,
  `app/plugins/site-render.test.ts`, all asserting on `/admin`); no storefront
  test fails.
- `npm run coverage`: **99.42% lines, 95.71% branches, 98.76% functions** against
  the 90 / 80 gate. Every `app/core/shop/*` file is 100/100/100; the lowest
  storefront file is `customer-account-view.ts` at 86.67% lines (its guard
  against running outside `requireVerifiedCustomer`).
- Live curl walk on <http://localhost:4000> with one seeded listing: home shows
  the piece with its shop and price → `?q=` narrows → `/art/:slug` renders with
  the add-to-cart form and the empty questions section → favorite → add to cart →
  `/checkout` (no card field for a guest) → `POST /checkout` 302 `/orders/1` with
  the debug alert printing the magic link → following the link 302
  `/orders/1/pay` → the card form → `4000 0000 0000 0002` leaves
  `Payment failed`, prints "Your card was declined.", and the listing's
  availability is back to 2 → `4242 4242 4242 4242` leaves `Paid` with the
  seller's fulfillment `Awaiting shipment` and the shipping address → `/orders`
  lists it → `/account` shows the verified address and the notifications
  section. `/art/nope`, `/orders/999`, `/orders/999/pay` all answer 404.
