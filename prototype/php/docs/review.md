# Review against the brief

Every requirement in `__local__/prompt.md`, its status, and the route and test
class that prove it.

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Engineering quality

| Measure | Where it is set | Result |
| --- | --- | --- |
| Formatting | `src/pint.json` — `laravel` preset plus `strict_comparison`, `strict_param`, `void_return` | `make lint` clean over 322 files |
| Static analysis | `src/phpstan.neon` — PHPStan/Larastan, `level: max` over `app`, `database`, `routes`, `tests` | 0 errors, no `excludePaths`, no `ignoreErrors`, no baseline |
| Strict types | Pint's `declare_strict_types`, re-asserted by `tests/Arch.php` | every PHP file |
| Tests | Pest, sidecars beside the file they cover | 733 tests, 1643 assertions |
| Coverage | `make coverage` (pcov) | 100.0% of lines |
| Architecture rules | `tests/Arch.php` | 8 layer rules plus Pest's `laravel` and `security` presets |
| Sidecar rule | `tests/SidecarsTest.php` | every non-abstract class under `app/` has one; the exception list is empty |

## Seller portal

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Create an account | done | `auth.seller.login`, `auth.seller.send`, `auth.magic.verify` | `Auth\SellerLoginControllerTest`, `Requests\Auth\SendMagicLinkRequestTest`, `Auth\MagicLinkVerificationControllerTest` |
| Add items straight after sign-in | done | `seller.listings.create`, `seller.listings.store` | `Seller\ListingControllerTest`, `Requests\Seller\ListingRequestTest` |
| Manage listings | done | `seller.listings.index`, `.edit`, `.update`, `.status` | `Seller\ListingControllerTest`, `Seller\ListingStatusControllerTest`, `Requests\Seller\ListingRequestTest`, `Requests\Seller\ChangeListingStatusRequestTest`, `Models\ListingTest`, `Policies\ListingPolicyTest` |
| Activity per listing: views, favorites, cart adds | done | `seller.listings.show` | `Seller\ListingControllerTest`, `Domain\Reports\ActivityTimelineTest` |
| Reports on sales | done | `seller.earnings` (Sales table) | `Seller\EarningsControllerTest` |
| Tools for fulfillment | done | `seller.orders.index`, `.show`, `.ship` | `Seller\OrderControllerTest`, `Seller\ShipmentControllerTest`, `Requests\Seller\MarkShippedRequestTest`, `Policies\FulfillmentPolicyTest` |
| Accumulated earnings and payouts | done | `seller.earnings`, `seller.earnings.payout` | `Seller\EarningsControllerTest`, `Seller\PayoutControllerTest`, `Actions\Escrow\RunWeeklyPayoutTest` |
| Flow: account → add items → `for_sale` reaches the storefront | done | the chain above plus `shop.home` | `Tests\SmokeTest` |
| Magic links, no passwords | done | `auth.magic.verify` | `Auth\MagicLinkVerificationControllerTest` |
| Theme: vanilla controls, system type, semantic HTML, stock Tailwind | done | `<x-layouts.seller>` | none (visual) |

The portal uses `table`, `dl`, `fieldset`/`legend`, `address`, and `caption`
with no component library and no font download; `Dockerfile` and
`resources/css/app.css` carry stock Tailwind v4 only.

## Customer site

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Browse | done | `shop.home` (search + medium filter), `shop.listing` | `Shop\StorefrontControllerTest`, `Shop\ListingControllerTest`, `Domain\Shop\ListingSearchTest` |
| Favorite | done | `shop.favorites`, `shop.favorites.toggle` | `Shop\FavoriteControllerTest`, `Actions\Favorites\ToggleFavoriteTest` |
| Purchase | done | `shop.cart.add`, `shop.checkout.place`, `shop.order.pay.submit` | `Shop\CartControllerTest`, `Shop\CheckoutControllerTest`, `Shop\OrderPaymentControllerTest`, `Requests\Shop\AddToCartRequestTest`, `Requests\Shop\CheckoutRequestTest`, `Requests\Shop\PayOrderRequestTest`, `Policies\OrderPolicyTest` |
| Anonymous customer id per visitor | done | every `shop.*` route, via `customer.identity` | `Http\Middleware\ResolveCustomerIdentityTest` |
| Anonymous ids merge into the account on sign-in | done | `auth.magic.verify` | `Actions\Customers\MergeAnonymousCustomerTest`, `Domain\Customers\CustomerIdentityPlanTest` |
| Magic links, no passwords | done | `auth.customer.login`, `auth.customer.send` | `Auth\CustomerLoginControllerTest`, `Requests\Auth\SendMagicLinkRequestTest` |
| Fake card 4242 4242 4242 4242 | done | `shop.order.pay.submit` | `Domain\Payments\FakeCardTest` |
| Failed payments | done | same route, retry form on `shop.order` | `Shop\OrderPaymentControllerTest`, `Actions\Orders\FinalizeOrderTest` |
| Guest checkout, verification before finalizing | done | `shop.checkout.place` → `auth.magic.verify` → `shop.order.pay` | `Shop\CheckoutControllerTest`, `Requests\Shop\CheckoutRequestTest`, `Tests\SmokeTest` |
| Whole purchase and fulfillment flow mocked | done | the chain above plus `seller.orders.ship`, `shop.order.delivered` | `Tests\SmokeTest`, `Actions\Orders\OrderLifecycleTest` |
| Theme: bright, open, wares over brand | done | `<x-layouts.shop>` | none (visual) |

## Fulfillment, escrow, payout

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Tell sellers an item sold | done | `seller.notifications.index` | `Seller\NotificationControllerTest`, `Listeners\NotifySellerOfSaleTest`, `Notifications\ItemSoldTest`, `Policies\NotificationPolicyTest` |
| Walk sellers through fulfillment | done | `seller.orders.show`, `seller.orders.ship` | `Seller\ShipmentControllerTest`, `Requests\Seller\MarkShippedRequestTest`, `Actions\Fulfillment\MarkShippedTest` |
| Notify customers of shipment | done | `shop.account` inbox | `Shop\AccountControllerTest`, `Listeners\NotifyCustomerOfShipmentTest`, `Notifications\OrderShippedTest` |
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
| Tests are sidecars next to the code | done | every non-abstract, non-interface, non-enum, non-trait file under `app/` has a sidecar; `tests/SidecarsTest.php`'s exception list is empty |
| `/test*` and `/tdd*` skills | partial | process, not visible in the artifacts; the shape they call for (sidecars, pure core tests, HTTP tests for the shell) holds |
| `/work-*` skills for work items | done | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — 22 tickets |
| `/write-*` skills | partial | process; comments in the tree are decision records, no restatements, no adverbs |
| TDD flow | partial | process; each ticket's `## Working` notes record the flow |
| Measure coverage, keep it high | done | `make coverage` — 100.0% of lines |
| Functional core / imperative shell | done | `app/Domain/**` is pure (no I/O, no clock, no random); actions and controllers sequence it. No controller holds a domain `if` — every branch reads a domain predicate (`OrderPayment::isPayableBy`, `ListingAvailability::isPurchasable`, `MagicLinkStatus`) or a shell fact (auth, empty cart, missing row) |
| `/diagramming` for docs | done | Mermaid throughout `docs/`: deployables, layers, ER and notification flow in `architecture.md`; sign-in and merge sequences in `identity.md`; the two state machines and the checkout sequence in `orders.md`; the ledger flow in `escrow.md`; the concept map in `ontology.md` |

## Goal

| Requirement | Status | Evidence |
| --- | --- | --- |
| Back-office for artists to create an account, list art, manage sales | done | `/seller/**` |
| Customer site for browsing | done | `/` |
| Mocked cart and payment with a fake card, success and failure | done | `Domain\Payments\FakeCard` — 4242… approves, 4000…0002 and 4000…9995 decline, anything else is an invalid number |
| Magic links for both sides, printed to the screen in a debug alert | done | `Notifications\MagicLinkIssued` on `Notifications\Channels\SessionFlashChannel` → `<x-debug-alert>` |
| A hook where email goes later | done | `toMail()` on every notification; `config/magic_links.php` → `delivery=mail` and `config/notifications.php` → `channels` turn it on |
| Guest checkout requiring verification before the order finalizes | done | `Shop\CheckoutController::place` |
| Work queued and delivered by agents | done | `work/journal.md` — every ticket in `work/3-done/` |
| Delivered in `./prototype/php/` with a complete README and a docs folder | done | `README.md`, `docs/` |

## Known gaps

1. **Mail is written but unproven.** Every notification implements
   `toMail()` and `MAGIC_LINK_DELIVERY=mail` / `NOTIFICATION_CHANNELS` turn
   the channel on, but no mailer is configured beyond `MAIL_MAILER=log`, so
   nothing has been sent to a real inbox.
2. **The payout button pays every seller**, not the signed-in one. It is
   labelled a debug control on `seller.earnings` and the flash says so, and
   `Seller\PayoutControllerTest` pins the behavior ("pays out every seller with
   released escrow, not only the signed-in seller") so a change to it fails a
   test rather than passing unnoticed.
3. **Shipment tracking is a text field.** No carrier integration; the customer
   confirms delivery from the order page.
4. **No order cancellation route.** `OrderStatus::Cancelled` exists in the
   domain with no way to reach it over HTTP.
5. **The cart's Checkout button stays live on an unavailable line.** The
   shopper is refused at the write, with the item named, rather than at the
   button.
6. **Replacing a listing image that fails to store keeps the old one
   silently.** The create path flashes the failure; the update path does not.
7. **Seeded listings carry a generated placeholder SVG**, not artwork.

## Suggested next steps

1. Point `MAIL_MAILER` at a real transport and turn on the `mail` channel,
   which closes gap 1 and removes the debug alert from the demo path.
2. Scope the payout button to the signed-in seller, or move it behind an
   artisan-only path and keep `payouts:run` as the single entry point.
3. Add order cancellation for `pending_verification` orders, with the stock
   returned to the listing.
4. Replace the placeholder SVG images with real uploads in the seeder so the
   storefront demo shows artwork.
