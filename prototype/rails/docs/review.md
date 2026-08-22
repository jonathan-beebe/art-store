# Review against the brief

Every requirement in `__local__/prompts/initial-prompt.md`, its status, and the
route helper and test class that prove it. Verified on FEAT-008 against a clean
first run: 645 tests green, 100% line coverage.

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Seller portal

| Requirement | Status | Route helper | Test |
| --- | --- | --- | --- |
| Create an account | done | `seller_login`, `seller_send_magic_link`, `verify_magic_link` | `Auth::SellerSessionsControllerTest`, `Auth::MagicLinksControllerTest` |
| Add items straight after sign-in | done | `new_seller_listing`, `seller_listings` (POST) | `Seller::ListingsControllerTest` |
| Manage listings | done | `seller_listings`, `edit_seller_listing`, `seller_listing` (PATCH), `seller_listing_status` | `Seller::ListingsControllerTest`, `Seller::ListingStatusesControllerTest` |
| Activity per listing: views, favorites, cart adds | done | `seller_listing` | `Seller::ListingsControllerTest`, `Domain::Reports::ActivityTimelineTest` |
| Reports on sales | done | `seller_earnings` (Sales table) | `Seller::EarningsControllerTest` |
| Tools for fulfillment | done | `seller_orders`, `seller_order`, `seller_order_shipment` | `Seller::OrdersControllerTest`, `Seller::ShipmentsControllerTest` |
| Accumulated earnings and payouts | done | `seller_earnings`, `seller_earnings_payout` | `Seller::EarningsControllerTest`, `Seller::PayoutsControllerTest`, `Escrow::RunWeeklyPayoutTest` |
| Flow: account → add items → `for_sale` reaches the storefront | done | the chain above plus `root` | `SmokeTest` |
| Magic links, no passwords | done | `verify_magic_link` | `Auth::MagicLinksControllerTest` |
| Theme: vanilla controls, system type, semantic HTML, stock Tailwind | done | `layouts/seller` | none (visual) |

The portal renders `table`, `dl`, `fieldset`/`legend`, `address` and `caption`
with no component library and no font download. `app/assets/tailwind/application.css`
is `@import "tailwindcss"` and nothing else.

## Customer site

| Requirement | Status | Route helper | Test |
| --- | --- | --- | --- |
| Browse | done | `root` (search, medium filter, pagination), `shop_listing` | `Shop::StorefrontControllerTest`, `Shop::ListingsControllerTest`, `Domain::Shop::ListingSearchTest`, `Domain::Shop::PageTest` |
| Favorite | done | `shop_favorites`, `shop_toggle_favorite` | `Shop::FavoritesControllerTest`, `Favorites::ToggleFavoriteTest` |
| Cart | done | `shop_cart`, `shop_add_to_cart`, `shop_remove_from_cart` | `Shop::CartsControllerTest`, `Carts::AddToCartTest`, `Carts::RemoveFromCartTest` |
| Purchase | done | `shop_checkout`, `shop_place_order`, `shop_pay_order` | `Shop::CheckoutsControllerTest`, `Shop::OrderPaymentsControllerTest` |
| Anonymous customer id per visitor | done | every storefront route, via `CustomerIdentity` | `CustomerIdentityConcernTest` |
| Anonymous ids merge into the account on sign-in | done | `verify_magic_link` | `Customers::MergeAnonymousCustomerTest`, `Domain::Customers::IdentityPlanTest` |
| Magic links, no passwords | done | `customer_login`, `customer_send_magic_link` | `Auth::CustomerSessionsControllerTest` |
| Fake card 4242 4242 4242 4242 | done | `shop_pay_order` | `Domain::Payments::FakeCardTest` |
| Failed payments | done | `shop_pay_order`, retry form on `shop_order` | `Shop::OrderPaymentsControllerTest`, `Orders::FinalizeOrderTest`, `Orders::OrderLifecycleTest` |
| Guest checkout, verification before finalizing | done | `shop_place_order` → `verify_magic_link` → `shop_order_payment` | `Shop::CheckoutsControllerTest`, `SmokeTest` |
| Whole purchase and fulfillment flow mocked | done | the chain above plus `seller_order_shipment`, `shop_confirm_delivery` | `SmokeTest`, `Orders::OrderLifecycleTest` |
| Theme: bright, open, wares over brand | done | `layouts/shop` | none (visual) |

## Fulfillment, escrow, payout

| Requirement | Status | Route helper | Test |
| --- | --- | --- | --- |
| Tell sellers an item sold | done | `seller_notifications` | `Seller::NotificationsControllerTest`, `Domain::Notifications::NotificationMessageTest` |
| Walk sellers through fulfillment | done | `seller_order`, `seller_order_shipment` | `Seller::ShipmentsControllerTest`, `Fulfillments::MarkShippedTest` |
| Notify customers of shipment | done | `shop_account` inbox | `Shop::AccountControllerTest` |
| Escrow held on payment, released on delivery | done | `shop_confirm_delivery` | `Fulfillments::ConfirmDeliveredTest`, `Domain::Escrow::LedgerBalanceTest` |
| Report of sold goods and funds due | done | `seller_earnings` | `Seller::EarningsControllerTest` |
| Pay out at the end of every week | done | `payouts:run`, `seller_earnings_payout` | `PayoutsTaskTest`, `Domain::Escrow::PayoutPeriodTest` |

## Tech stack

| Requirement | Status | Evidence |
| --- | --- | --- |
| Modern Rails | done | `Gemfile.lock` — Rails 8.1.3.1 on Ruby 3.3.12 |
| SQLite | done | `config/database.yml`, `src/storage/development.sqlite3` |
| Tailwind | done | `tailwindcss-rails` 4.6.0 / tailwindcss 4.3.3, standalone binary, no Node |
| Semantic HTML + CSS | done | `app/views/**` |
| No JavaScript required | done | no `<script>` in any view, no `app/javascript`, no importmap; every flow is a form POST |

## Development workflow

| Requirement | Status | Evidence |
| --- | --- | --- |
| Everything dockerized, nothing on the host | done | `Dockerfile`, `docker-compose.yml`, `Makefile` — every target is a `docker compose` wrapper |
| All source in `src` | done | `prototype/rails/src/` |
| Tests mirror the code under `test/` | partial | 20 files under `app/` have no test of their own (see Known gaps); all are at 100% line coverage through the tests of their callers |
| `/test*` and `/tdd*` skills | partial | process, not visible in the artifacts; the shape they call for holds — pure core tests, HTTP tests for the shell |
| `/work-*` skills for work items | done | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — 8 tickets |
| `/write-*` skills | partial | process; the comments in the tree carry reasons, not restatements |
| TDD flow | partial | process; each ticket's `## Working` notes record it |
| Measure coverage, keep it high | done | `make coverage` — 100% line coverage, `COVERAGE_MIN=80` enforced |
| Functional core / imperative shell | done | `app/domain/**` is pure — no I/O, no clock, no random; time and ids arrive as arguments. No controller holds a domain `if`: every branch reads a domain predicate (`OrderPayment.payable?`, `ListingAvailability.purchasable?`, `FulfillmentStatus.can_transition?`, `EmailAddress.valid?`, `CheckoutForm#complete?`, `ShipmentDetails#complete?`) or a shell fact (signed in, empty cart, missing row) |
| `/diagramming` for docs | done | `docs/architecture.md`, `identity.md`, `orders.md`, `escrow.md`, `data-model.md`, `ontology.md` |

## Goal

| Requirement | Status | Evidence |
| --- | --- | --- |
| Back-office for artists to create an account, list art, manage sales | done | `/seller/**` |
| Customer site for browsing | done | `/` |
| Mocked cart and payment with a fake card, success and failure | done | `Domain::Payments::FakeCard` — 4242… approves; 4000…0002 and 4000…9995 decline; anything else is an invalid number |
| Magic links for both sides, printed to the screen in a debug alert | done | `FlashMagicLinkDelivery` → `layouts/_debug_alert` |
| A hook where email goes later | done | `MailMagicLinkDelivery` (selected by `MAGIC_LINK_DELIVERY=mail`), `Notifications::Notify#deliver_by_email` |
| Guest checkout requiring verification before the order finalizes | done | `Shop::CheckoutsController#create` → `Shop::OrderPaymentsController` |
| Work queued and delivered by agents | done | `work/journal.md` — FEAT-001 … FEAT-008 |
| Delivered in `./prototype/rails/` with a complete README and a docs folder | done | `README.md`, `docs/` |

## Verified on FEAT-008

- Clean first run: `src/vendor`, `src/.bundle`, both SQLite files, `app/assets/builds`,
  `tmp`, `log` and `coverage` removed, then `make up` alone. The bundle installs,
  `db:prepare` creates and seeds the database, Tailwind builds, and `/`,
  `/seller`, `/seller/login`, `/login`, `/cart`, `/favorites` and `/orders`
  answer 200 in 40 seconds. The stylesheet the HTML references serves 200.
- `make fresh` against the running server re-seeds and the storefront still
  answers 200 with the seeded listings.
- `make test`: 645 runs, 1604 assertions, 0 failures. `make coverage`: 100%
  line coverage, every group at 100%.
- `make smoke`: the whole product in one test, 105 assertions, 0.7s.
- `bin/rails zeitwerk:check`: all is good.
- A curl walk over the running server: the storefront, all 12 listing pages on
  page 1, search, the medium filter, page 2, cart, favorites, orders, login,
  and the nine seller pages all answer 200 with the stylesheet linked; another
  seller's listing, shipment and order ids answer 404 on both reads and writes;
  a live guest checkout runs from cart to a declined card to `Paid`; an
  uploaded image is stored and served from `/rails/active_storage/blobs/...`.

## Known gaps

1. **Mail delivery raises.** `MailMagicLinkDelivery#deliver` raises
   `NotImplementedError` and `Notify#deliver_by_email` does nothing. Setting
   `MAGIC_LINK_DELIVERY=mail` breaks sign-in. This is the hook, not an
   implementation.
2. **The payout button pays every seller**, not the signed-in one. It is
   labelled a debug control on `seller_earnings` and the controller says so.
   `payouts:run` is the real entry point.
3. **20 files under `app/` have no test of their own**: `ApplicationController`,
   the three base controllers, `Shop::NotificationsController`, the two
   authentication concerns, `Domain::TransitionError`, the two helpers,
   `ApplicationRecord`, and nine thin models. Every one is at 100% line
   coverage through the tests of its callers; `Shop::NotificationsController`
   is the only one with behavior of its own, covered by
   `Shop::AccountControllerTest`.
4. **A merge can leave a customer holding two carts.**
   `Customers::MergeAnonymousCustomer` re-points the anonymous cart rather than
   folding it into the account's cart, and `Carts::CurrentCart` then shops with
   whichever holds more items. Items in the other cart are still in the
   database and no page shows them.
5. **No order cancellation.** `OrderStatus::CANCELLED` and the stock it would
   release exist in the domain with no route that reaches them.
6. **No image variants.** There is no libvips in the image, so
   `Listing#image_url` serves the original blob. Asking for a variant would
   raise.
7. **Shipment tracking is two text fields.** No carrier integration; the
   customer confirms delivery from the order page.
8. **Seeded listings carry no image files.** `PlaceholderImage` renders a
   generated SVG from the title, so the storefront demo shows shapes rather
   than artwork. An uploaded image is served for listings created through the
   portal.
9. **`allow_browser versions: :modern`** answers 406 to old browsers, which is
   stock Rails and the one place the prototype needs a modern browser it does
   not otherwise need.

## Suggested next steps

1. Implement `MailMagicLinkDelivery` against Action Mailer and give `Notify`
   the same port shape. Closes gap 1 and removes the debug alert from the demo
   path.
2. Fold the anonymous cart into the account's cart during the merge, so one
   customer has one cart. Closes gap 4.
3. Scope the payout button to the signed-in seller, or drop it and keep
   `payouts:run` as the single entry point. Closes gap 2.
4. Add order cancellation for `pending_verification` and `awaiting_payment`
   orders, returning the stock to the listing. Closes gap 5.
5. Give `Shop::NotificationsController` its own test, and delete or use the
   model relations no caller reads. Closes gap 3.
6. Attach real images in `db/seeds.rb` and add libvips to the image so the
   storefront can ask for a thumbnail variant. Closes gaps 6 and 8.
