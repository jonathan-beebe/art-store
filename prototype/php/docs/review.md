# Review against the brief

Every requirement in `__local__/prompt.md`, its status, and the route and test
class that prove it. Verified on FEAT-008 against a clean first run: 471 tests
green, 98.20% line coverage overall, 100% on `app/Domain`.

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Seller portal

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Create an account | done | `auth.seller.login`, `auth.seller.send`, `auth.magic.verify` | `Auth\SellerLoginControllerTest`, `Auth\MagicLinkVerificationControllerTest` |
| Add items straight after sign-in | done | `seller.listings.create`, `seller.listings.store` | `Seller\ListingControllerTest` |
| Manage listings | done | `seller.listings.index`, `.edit`, `.update`, `.status` | `Seller\ListingControllerTest`, `Seller\ListingStatusControllerTest`, `Policies\ListingPolicyTest` |
| Activity per listing: views, favorites, cart adds | done | `seller.listings.show` | `Seller\ListingActivityControllerTest`, `Domain\Reports\ActivityTimelineTest` |
| Reports on sales | done | `seller.earnings` (Sales table) | `Seller\EarningsControllerTest` |
| Tools for fulfillment | done | `seller.orders.index`, `.show`, `.ship` | `Seller\OrderControllerTest`, `Seller\ShipmentControllerTest`, `Policies\FulfillmentPolicyTest` |
| Accumulated earnings and payouts | done | `seller.earnings`, `seller.earnings.payout` | `Seller\EarningsControllerTest`, `Seller\PayoutControllerTest`, `Actions\Escrow\RunWeeklyPayoutTest` |
| Flow: account → add items → `for_sale` reaches the storefront | done | the chain above plus `shop.home` | `Tests\SmokeTest` |
| Magic links, no passwords | done | `auth.magic.verify` | `Auth\MagicLinkVerificationControllerTest` |
| Theme: vanilla controls, system type, semantic HTML, stock Tailwind | done | `layouts/seller` | none (visual) |

The portal uses `table`, `dl`, `fieldset`/`legend`, `address`, and `caption`
with no component library and no font download; `Dockerfile` and
`resources/css/app.css` carry stock Tailwind v4 only.

## Customer site

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Browse | done | `shop.home` (search + medium filter), `shop.listing` | `Shop\StorefrontControllerTest`, `Shop\ListingControllerTest`, `Domain\Shop\ListingSearchTest` |
| Favorite | done | `shop.favorites`, `shop.favorites.toggle` | `Shop\FavoriteControllerTest`, `Actions\Favorites\ToggleFavoriteTest` |
| Purchase | done | `shop.cart.add`, `shop.checkout.place`, `shop.order.pay.submit` | `Shop\CartControllerTest`, `Shop\CheckoutControllerTest`, `Shop\OrderPaymentControllerTest`, `Policies\OrderPolicyTest` |
| Anonymous customer id per visitor | done | every `shop.*` route, via `customer.identity` | `Http\Middleware\ResolveCustomerIdentityTest` |
| Anonymous ids merge into the account on sign-in | done | `auth.magic.verify` | `Actions\Customers\MergeAnonymousCustomerTest`, `Domain\Customers\CustomerIdentityPlanTest` |
| Magic links, no passwords | done | `auth.customer.login`, `auth.customer.send` | `Auth\CustomerLoginControllerTest` |
| Fake card 4242 4242 4242 4242 | done | `shop.order.pay.submit` | `Domain\Payments\FakeCardTest` |
| Failed payments | done | same route, retry form on `shop.order` | `Shop\OrderPaymentControllerTest`, `Actions\Orders\FinalizeOrderTest` |
| Guest checkout, verification before finalizing | done | `shop.checkout.place` → `auth.magic.verify` → `shop.order.pay` | `Shop\CheckoutControllerTest`, `Tests\SmokeTest` |
| Whole purchase and fulfillment flow mocked | done | the chain above plus `seller.orders.ship`, `shop.order.delivered` | `Tests\SmokeTest`, `Actions\Orders\OrderLifecycleTest` |
| Theme: bright, open, wares over brand | done | `layouts/shop` | none (visual) |

## Fulfillment, escrow, payout

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Tell sellers an item sold | done | `seller.notifications.index` | `Seller\NotificationControllerTest`, `Domain\Notifications\NotificationMessageTest`, `Policies\NotificationPolicyTest` |
| Walk sellers through fulfillment | done | `seller.orders.show`, `seller.orders.ship` | `Seller\ShipmentControllerTest`, `Actions\Fulfillment\MarkShippedTest` |
| Notify customers of shipment | done | `shop.account` inbox | `Shop\AccountControllerTest` |
| Escrow held on payment, released on delivery | done | `shop.order.delivered` | `Actions\Fulfillment\ConfirmDeliveredTest`, `Domain\Escrow\LedgerBalanceTest`, `Policies\FulfillmentPolicyTest` |
| Report of sold goods and funds due | done | `seller.earnings` | `Seller\EarningsControllerTest` |
| Pay out at the end of every week | done | `payouts:run`, `seller.earnings.payout` | `Console\Commands\RunWeeklyPayoutsTest`, `Domain\Escrow\PayoutPeriodTest` |

## Tech stack

| Requirement | Status | Evidence |
| --- | --- | --- |
| PHP | done | `Dockerfile` — `php:8.3-cli`, PHP 8.3.33 |
| Laravel | done | `composer.json` — `laravel/framework ^13.17` |
| SQLite | done | `config/database.php`, `database/database.sqlite` |
| Tailwind | done | `package.json` — `tailwindcss` v4 through Vite |
| Semantic HTML + CSS | done | `resources/views/**` |
| No JavaScript required | done | no `<script>` in any Blade file; `resources/js` does not exist; Vite input is `resources/css/app.css` alone |

## Development workflow

| Requirement | Status | Evidence |
| --- | --- | --- |
| Everything dockerized, nothing on the host | done | `Dockerfile`, `docker-compose.yml`, `Makefile` |
| All source in `src` | done | `prototype/php/src/` |
| Tests are sidecars next to the code | partial | 4 files under `app/` have no sidecar (see Known gaps); every other non-trivial file has one |
| `/test*` and `/tdd*` skills | partial | process, not visible in the artifacts; the shape they call for (sidecars, pure core tests, HTTP tests for the shell) holds |
| `/work-*` skills for work items | done | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — 8 tickets |
| `/write-*` skills | partial | process; comments in the tree are decision records, no restatements, no adverbs |
| TDD flow | partial | process; each ticket's `## Working` notes record the flow |
| Measure coverage, keep it high | done | `make coverage` — 98.20% overall, 100% `app/Domain` |
| Functional core / imperative shell | done | `app/Domain/**` is pure (no I/O, no clock, no random); actions and controllers sequence it. No controller holds a domain `if` — every branch reads a domain predicate (`OrderPayment::isPayableBy`, `ListingAvailability::isPurchasable`, `MagicLinkStatus`) or a shell fact (auth, empty cart, missing row) |
| `/diagramming` for docs | done | `docs/architecture.md` (Mermaid: deployables, layers, ER, order state machine); FEAT-007 adds the sequence and flow diagrams |

## Goal

| Requirement | Status | Evidence |
| --- | --- | --- |
| Back-office for artists to create an account, list art, manage sales | done | `/seller/**` |
| Customer site for browsing | done | `/` |
| Mocked cart and payment with a fake card, success and failure | done | `Domain\Payments\FakeCard` — 4242… approves, 4000…0002 and 4000…9995 decline, anything else is an invalid number |
| Magic links for both sides, printed to the screen in a debug alert | done | `SessionFlashMagicLinkDelivery` → `partials/debug-alert` |
| A hook where email goes later | done | `Support\MagicLinkDelivery\MailMagicLinkDelivery` (bound by `config/magic_links.php` → `delivery=mail`), `Actions\Notifications\Notify::deliverByEmail()` |
| Guest checkout requiring verification before the order finalizes | done | `Shop\CheckoutController::place` |
| Work queued and delivered by agents | done | `work/journal.md` — FEAT-001 … FEAT-008 |
| Delivered in `./prototype/php/` with a complete README and a docs folder | done | `README.md`, `docs/` |

## Known gaps

1. **Four files under `app/` have no sidecar test**: `Actions/Auth/SignInSeller`,
   `Actions/Auth/SignInCustomer`, `Actions/Customers/ClaimCustomerIdentity`,
   `Actions/Customers/ResolveCustomerFromCookie`. All four are at 100% line
   coverage through `Auth\MagicLinkVerificationControllerTest` and
   `Actions\Customers\MergeAnonymousCustomerTest`.
2. **Eloquent models are at 86.07% line coverage.** The 17 uncovered lines are
   inverse `belongsTo` relations no caller reads yet (`Cart::customer`,
   `LedgerEntry::seller`, `ListingEvent::listing`, `Notification::customer`,
   `Payment::order`, `Payout::seller`, and the like). Either a caller or a
   deletion closes this.
3. **`AppServiceProvider` has one uncovered line**: the throw for an unknown
   `magic_links.delivery` value.
4. **Mail delivery throws.** `MailMagicLinkDelivery::deliver()` raises
   `LogicException`, and `Notify::deliverByEmail()` is empty. Setting
   `MAGIC_LINK_DELIVERY=mail` breaks sign-in. This is the hook, not an
   implementation.
5. **The payout button pays every seller**, not the signed-in one. It is
   labelled a debug control on `seller.earnings` and the flash says so.
6. **Shipment tracking is a text field.** No carrier integration; the customer
   confirms delivery from the order page.
7. **No order cancellation route.** `OrderStatus::Cancelled` exists in the
   domain with no way to reach it over HTTP.
8. **`FEAT-005`'s source landed inside `FEAT-004`'s commits.** A history
   artifact of parallel agents; the tree is correct.

## Suggested next steps

1. Give the four untested action classes their sidecars, and delete or use the
   unread model relations. Closes gaps 1 and 2.
2. Implement `MailMagicLinkDelivery` against Laravel's mailer and give `Notify`
   the same port shape. Closes gap 4 and removes the debug alert from the
   demo path.
3. Scope the payout button to the signed-in seller, or move it behind an
   artisan-only path and keep `payouts:run` as the single entry point.
4. Add order cancellation for `pending_verification` orders, with the stock
   returned to the listing.
5. Replace the placeholder SVG images with real uploads in the seeder so the
   storefront demo shows artwork.
