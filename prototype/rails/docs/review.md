# Review against the brief

Every requirement in `__local__/prompts/initial-prompt.md`, its status, and the
route helper and test class that prove it. Verified on FEAT-008 against a clean
first run (527 tests, 100% line coverage) and again on FEAT-014 after the
messaging feature (737 tests, 100% line coverage).

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Seller portal

| Requirement                                 | Status | Route helper                                 | Test                                         |
| ------------------------------------------- | ------ | -------------------------------------------- | -------------------------------------------- |
| Create an account                           | done   | `seller_login`, `seller_send_magic_link`,    | `Auth::SellerSessionsControllerTest`,        |
|                                             |        | `verify_magic_link`                          | `Auth::MagicLinksControllerTest`             |
| Add items straight after sign-in            | done   | `new_seller_listing`, `seller_listings`      | `Seller::ListingsControllerTest`             |
|                                             |        | (POST)                                       |                                              |
| Manage listings                             | done   | `seller_listings`, `edit_seller_listing`,    | `Seller::ListingsControllerTest`,            |
|                                             |        | `seller_listing` (PATCH),                    | `Seller::ListingStatusesControllerTest`      |
|                                             |        | `seller_listing_status`                      |                                              |
| Activity per listing: views, favorites,     | done   | `seller_listing`                             | `Seller::ListingsControllerTest`,            |
| cart adds                                   |        |                                              | `ListingTest`, `ListingEventTest`            |
| Reports on sales                            | done   | `seller_earnings` (Sales table)              | `Seller::EarningsControllerTest`             |
| Tools for fulfillment                       | done   | `seller_orders`, `seller_order`,             | `Seller::OrdersControllerTest`,              |
|                                             |        | `seller_order_shipment`                      | `Seller::ShipmentsControllerTest`            |
| Accumulated earnings and payouts            | done   | `seller_earnings` (balances and history; the | `Seller::EarningsControllerTest`,            |
|                                             |        | payout run is a platform action,             | `Admin::PayoutsControllerTest`, `PayoutTest` |
|                                             |        | `admin_payouts`)                             |                                              |
| Flow: account → add items → `for_sale`      | done   | the chain above plus `root`                  | `SmokeTest`                                  |
| reaches the storefront                      |        |                                              |                                              |
| Magic links, no passwords                   | done   | `verify_magic_link`                          | `Auth::MagicLinksControllerTest`             |
| Theme: vanilla controls, system type,       | done   | `layouts/seller`                             | none (visual)                                |
| semantic HTML, stock Tailwind               |        |                                              |                                              |

The portal renders `table`, `dl`, `fieldset`/`legend`, `address` and `caption`
with no component library and no font download. `app/assets/tailwind/application.css`
is `@import "tailwindcss"` and nothing else.

## Customer site

| Requirement                                 | Status | Route helper                                 | Test                                         |
| ------------------------------------------- | ------ | -------------------------------------------- | -------------------------------------------- |
| Browse                                      | done   | `root` (search, medium filter, pagination),  | `Shop::StorefrontControllerTest`,            |
|                                             |        | `shop_listing`                               | `Shop::ListingsControllerTest`,              |
|                                             |        |                                              | `ListingTest`, `PageTest`                    |
| Favorite                                    | done   | `shop_favorites`, `shop_toggle_favorite`     | `Shop::FavoritesControllerTest`,             |
|                                             |        |                                              | `CustomerTest`                               |
| Cart                                        | done   | `shop_cart`, `shop_add_to_cart`,             | `Shop::CartsControllerTest`,                 |
|                                             |        | `shop_remove_from_cart`                      | `Shop::CartItemsControllerTest`, `CartTest`, |
|                                             |        |                                              | `CartItemTest`                               |
| Purchase                                    | done   | `shop_checkout`, `shop_place_order`,         | `Shop::CheckoutsControllerTest`,             |
|                                             |        | `shop_pay_order`                             | `Shop::OrderPaymentsControllerTest`          |
| Anonymous customer id per visitor           | done   | every storefront route, via                  | `CustomerIdentityConcernTest`                |
|                                             |        | `CustomerIdentity`                           |                                              |
| Anonymous ids merge into the account on     | done   | `verify_magic_link`                          | `CustomerTest`,                              |
| sign-in                                     |        |                                              | `Auth::MagicLinksControllerTest`             |
| Magic links, no passwords                   | done   | `customer_login`, `customer_send_magic_link` | `Auth::CustomerSessionsControllerTest`       |
| Fake card 4242 4242 4242 4242               | done   | `shop_pay_order`                             | `FakeCardTest`                               |
| Failed payments                             | done   | `shop_pay_order`, retry form on `shop_order` | `Shop::OrderPaymentsControllerTest`,         |
|                                             |        |                                              | `OrderTest`, `OrderLifecycleTest`            |
| Guest checkout, verification before         | done   | `shop_place_order` → `verify_magic_link` →   | `Shop::CheckoutsControllerTest`, `SmokeTest` |
| finalizing                                  |        | `shop_order_payment`                         |                                              |
| Whole purchase and fulfillment flow mocked  | done   | the chain above plus                         | `SmokeTest`, `OrderLifecycleTest`            |
|                                             |        | `seller_order_shipment`,                     |                                              |
|                                             |        | `shop_confirm_delivery`                      |                                              |
| Theme: bright, open, wares over brand       | done   | `layouts/shop`                               | none (visual)                                |

## Fulfillment, escrow, payout

| Requirement                                 | Status | Route helper                                 | Test                                         |
| ------------------------------------------- | ------ | -------------------------------------------- | -------------------------------------------- |
| Tell sellers an item sold                   | done   | `seller_notifications`                       | `Seller::NotificationsControllerTest`,       |
|                                             |        |                                              | `NotificationTest`                           |
| Walk sellers through fulfillment            | done   | `seller_order`, `seller_order_shipment`      | `Seller::ShipmentsControllerTest`,           |
|                                             |        |                                              | `FulfillmentTest`                            |
| Notify customers of shipment                | done   | `shop_account` inbox,                        | `Shop::AccountControllerTest`,               |
|                                             |        | `shop_read_notification`                     | `Shop::NotificationReadsControllerTest`      |
| Escrow held on payment, released on         | done   | `shop_confirm_delivery`                      | `FulfillmentTest`, `LedgerEntryTest`         |
| delivery                                    |        |                                              |                                              |
| Report of sold goods and funds due          | done   | `seller_earnings`                            | `Seller::EarningsControllerTest`             |
| Pay out at the end of every week            | done   | `payouts:run`, `admin_payouts` (POST)        | `PayoutsTaskTest`,                           |
|                                             |        |                                              | `Admin::PayoutsControllerTest`,              |
|                                             |        |                                              | `PayoutTest`, `PayoutPeriodTest`             |

## Messaging and the admin site

Added after the brief (FEAT-009 … FEAT-014), so these are the feature's own
claims rather than requirements from `initial-prompt.md`.

| Capability                                  | Status | Route helper                                 | Test                                         |
| ------------------------------------------- | ------ | -------------------------------------------- | -------------------------------------------- |
| An admin site with a seeded-only account    | done   | `admin_login`, `admin_root`, `admin_seller`, | `Auth::AdminSessionsControllerTest`,         |
|                                             |        | `admin_customer`                             | `Admin::DashboardControllerTest`,            |
|                                             |        |                                              | `Admin::SellersControllerTest`,              |
|                                             |        |                                              | `Admin::CustomersControllerTest`,            |
|                                             |        |                                              | `AdminTest`                                  |
| A directory over every seller, customer,    | done   | `admin_sellers`, `admin_customers`,          | `Admin::SellersControllerTest`,              |
| listing, order and fulfillment              |        | `admin_listings`, `admin_orders`,            | `Admin::CustomersControllerTest`,            |
|                                             |        | `admin_fulfillments` and each `…/:id` — see  | `Admin::ListingsControllerTest`,             |
|                                             |        | [`admin.md`](admin.md)                       | `Admin::OrdersControllerTest`,               |
|                                             |        |                                              | `Admin::FulfillmentsControllerTest`          |
| One conversation record for four kinds      | done   | —                                            | `ConversationTest`, `MessageTest`            |
| Inbox and thread on all three sites,        | done   | `shop_conversations`,                        | `Shop::ConversationsControllerTest`,         |
| non-participant answers 404                 |        | `seller_conversations`,                      | `Seller::ConversationsControllerTest`,       |
|                                             |        | `admin_conversations` and each               | `Admin::ConversationsControllerTest`         |
|                                             |        | `…_conversation`                             |                                              |
| Reply, with the counterpart notified at     | done   | each site's `…_conversation_messages`        | `Shop::MessagesControllerTest`,              |
| their own path                              |        |                                              | `Seller::MessagesControllerTest`,            |
|                                             |        |                                              | `Admin::MessagesControllerTest`,             |
|                                             |        |                                              | `NotificationTest`                           |
| Support threads against `Admin.on_duty`     | done   | `shop_support`, `seller_support`             | `Shop::SupportsControllerTest`,              |
|                                             |        |                                              | `Seller::SupportsControllerTest`             |
| The desk opens a thread from either account | done   | `admin_seller_conversation`,                 | `Admin::SellerConversationsControllerTest`,  |
| page                                        |        | `admin_customer_conversation`                | `Admin::CustomerConversationsControllerTest` |
| Admin removes a listing (temporary or       | done   | `admin_listing_removals` (POST),             | `Admin::ListingRemovalsControllerTest`,      |
| permanent) and lifts a removal; a removal   |        | `lift_admin_listing_removals` (POST)         | `ListingTest`,                               |
| takes the listing off browse, search and    |        |                                              | `Shop::ListingsControllerTest`,              |
| its own page whatever its status, and       |        |                                              | `Shop::StorefrontControllerTest`,            |
| blocks the seller from putting it back on   |        |                                              | `Seller::ListingStatusesControllerTest`      |
| sale                                        |        |                                              |                                              |
| Admin blocks a customer and lifts a block;  | done   | `admin_customer_blocks` (POST),              | `Admin::CustomerBlocksControllerTest`,       |
| a block stops cart add, checkout, pay and   |        | `lift_admin_customer_blocks` (POST)          | `CustomerTest`, `ConversationTest`,          |
| message post, leaving browsing, favorites   |        |                                              | `Shop::CartItemsControllerTest`,             |
| and reading threads open                    |        |                                              | `Shop::CheckoutsControllerTest`,             |
|                                             |        |                                              | `Shop::OrderPaymentsControllerTest`,         |
|                                             |        |                                              | `Shop::MessagesControllerTest`,              |
|                                             |        |                                              | `Shop::ListingQuestionsControllerTest`       |
| A thread per fulfillment from either order  | done   | `shop_fulfillment_conversation`,             | `Shop::FulfillmentConversationsControllerTest`, |
| page                                        |        | `seller_order_conversation`                  | `Seller::OrderConversationsControllerTest`   |
| Anonymous shopper asks a question on a      | done   | `shop_listing_questions`                     | `Shop::ListingQuestionsControllerTest`,      |
| listing                                     |        |                                              | `CustomerTest` (merge carries the thread)    |
| Seller publishes, edits, unpublishes an     | done   | `seller_listing_faqs`                        | `Seller::FaqsControllerTest`,                |
| FAQ; the storefront shows it                |        |                                              | `Shop::ListingsControllerTest`,              |
|                                             |        |                                              | `ListingFaqTest`                             |
| Unread count in every nav, read on opening  | done   | every layout                                 | `ConversationTest`, the three inbox tests    |
| a thread                                    |        |                                              |                                              |
| Live message and badge over Turbo streams   | done   | `turbo_stream_from` on each thread page      | `MessageTest`, `ConversationTest`, the three |
|                                             |        |                                              | thread-page tests                            |
| A shopper's question through to a published | done   | the chain above                              | `SmokeTest`                                  |
| FAQ on the listing page                     |        |                                              |                                              |

### The same feature in the Node prototype

Both prototypes carry the four conversation kinds, the FAQ publish, and the
nav badge, and both keep every page working with JavaScript off. The live
half differs:

- **Node** hand-rolls Server-Sent Events. `app/plugins/events.ts` (174 lines)
  holds an in-process `EventEmitter`, an `onResponse` hook that emits after
  any write, and the SSE route each site serves at `/events`,
  `/seller/events`, `/admin/events`. `src/public/app.js` (21 lines) opens an
  `EventSource` and rewrites the nav badge. The frame carries one number: the
  actor's unread total. An open thread page gains nothing until the next load.
  The bus is in-process, so a second app instance needs a shared broker.
- **Rails** uses Turbo streams. The client is
  `app/javascript/application.js` (4 lines, one `import`) plus the
  `turbo.min.js` the `turbo-rails` gem serves through the import map.
  `Message#broadcast_arrival` (`after_create_commit`) appends the rendered
  message row to each participant's stream and replaces the counterpart's
  badge partial; `Conversation#read_by!` replaces the reader's. So an open
  thread gains the other side's message, and the badge moves in both
  directions. Transport is Action Cable on Solid Cable, whose queue is the
  `solid_cable_messages` table in the same SQLite file. Broadcasts run after
  commit, so a rolled-back post sends nothing, and stream names are signed.

## Tech stack

| Requirement            | Status | Evidence                                                                                                         |
| ---------------------- | ------ | ---------------------------------------------------------------------------------------------------------------- |
| Modern Rails           | done   | `Gemfile.lock` — Rails 8.1.3.1 on Ruby 3.3.12                                                                    |
| SQLite                 | done   | `config/database.yml`, `src/storage/development.sqlite3`                                                         |
| Tailwind               | done   | `tailwindcss-rails` 4.6.0 / tailwindcss 4.3.3, standalone binary, no Node                                        |
| Semantic HTML + CSS    | done   | `app/views/**`                                                                                                   |
| No JavaScript required | done   | every flow is a form POST that redirects; `app/javascript/application.js` is one                                 |
|                        |        | `import "@hotwired/turbo-rails"`, and with it blocked or absent every page, form, link and redirect behaves as   |
|                        |        | it did before Turbo                                                                                              |
| Hotwire                | done   | `turbo-rails` 2.0.23, `importmap-rails` 2.2.3, `solid_cable` 4.0.2 — thread pages and the nav badge update over  |
|                        |        | Action Cable with the broadcast queue in the app's own SQLite file                                               |

## Cross-prototype comparison

- **CSRF failure status**: Rails answers **422** — a raised
  `ActionController::InvalidAuthenticityToken` is unrescued anywhere in the
  tree, so it falls through to Rails' own default mapping
  (`ActionDispatch::ExceptionWrapper`'s `unprocessable_content`). Node
  answers 403, Laravel answers 419. Each stack keeps its own idiom; the
  divergence is recorded here rather than resolved.

## Development workflow

| Requirement                                | Status  | Evidence                                                                                    |
| ------------------------------------------ | ------- | ------------------------------------------------------------------------------------------- |
| Everything dockerized, nothing on the host | done    | `Dockerfile`, `docker-compose.yml`, `Makefile` — every target is a `docker compose` wrapper |
| All source in `src`                        | done    | `prototype/rails/src/`                                                                      |
| Tests mirror the code under `test/`        | partial | 20 of the 85 files under `app/` have no test of their own (see Known gaps); all are at 100% |
|                                            |         | line coverage through the tests of their callers                                            |
| `/test*` and `/tdd*` skills                | partial | process, not visible in the artifacts; the shape they call for holds — pure core tests,     |
|                                            |         | HTTP tests for the shell                                                                    |
| `/work-*` skills for work items            | done    | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — 30 tickets               |
| `/write-*` skills                          | partial | process; the comments in the tree carry reasons, not restatements                           |
| TDD flow                                   | partial | process; each ticket's `## Working` notes record it                                         |
| Measure coverage, keep it high             | done    | `make test`/`make check` — 100% line coverage, `COVERAGE_MIN=100` enforced; `make coverage` |
|                                            |         | runs the same suite for the HTML report with no minimum of its own                          |
| Functional core / imperative shell         | done    | The value objects in `app/models` (`Money`, `Page`, `PayoutPeriod`, `FakeCard`,             |
|                                            |         | `PlaceholderImage`, `LedgerEntry::Balance`, `ListingEvent::Totals`, `ListingEvent::Day`)    |
|                                            |         | are pure — no I/O, no clock, no random; time and ids arrive as arguments. No controller     |
|                                            |         | holds a domain `if`: every branch reads a record predicate                                  |
|                                            |         | (`Fulfillment#can_transition_to?`, `Order#unpaid?`, `Order#payable_by?`,                    |
|                                            |         | `Listing#purchasable?`, `MagicLink#usable?`, `order.persisted?`), or a shell fact (signed   |
|                                            |         | in, empty cart, missing row)                                                                |
| `/diagramming` for docs                    | done    | `docs/architecture.md`, `identity.md`, `orders.md`, `escrow.md`, `messaging.md`,            |
|                                            |         | `data-model.md`, `ontology.md`                                                              |

## Goal

| Requirement                                                         | Status | Evidence                                                            |
| ------------------------------------------------------------------- | ------ | ------------------------------------------------------------------- |
| Back-office for artists to create an account, list art, manage      | done   | `/seller/**`                                                        |
| sales                                                               |        |                                                                     |
| Customer site for browsing                                          | done   | `/`                                                                 |
| Mocked cart and payment with a fake card, success and failure       | done   | `FakeCard` — 4242… approves; 4000…0002 and 4000…9995 decline;       |
|                                                                     |        | anything else is an invalid number                                  |
| Magic links for both sides, printed to the screen in a debug alert  | done   | `MagicLinkSender#send_magic_link` → `layouts/_debug_alert`          |
|                                                                     |        | (`MAGIC_LINK_DEBUG_ALERT`)                                          |
| A hook where email goes later                                       | done   | `MagicLinkMailer#sign_in` sends the link;                           |
|                                                                     |        | `Notification#deliver_by_email` is still empty                      |
| Guest checkout requiring verification before the order finalizes    | done   | `Shop::CheckoutsController#create` →                                |
|                                                                     |        | `Shop::OrderPaymentsController`                                     |
| Work queued and delivered by agents                                 | done   | `work/journal.md` — FEAT-001 … FEAT-014 and RFCTR-001 … RFCTR-013   |
| Delivered in `./prototype/rails/` with a complete README and a docs | done   | `README.md`, `docs/`                                                |
| folder                                                              |        |                                                                     |

## Verified on FEAT-008

- Clean first run: `src/vendor`, `src/.bundle`, both SQLite files, `app/assets/builds`,
  `tmp`, `log` and `coverage` removed, then `make up` alone. The bundle installs,
  `db:prepare` creates and seeds the database, Tailwind builds, and `/`,
  `/seller`, `/seller/login`, `/login`, `/cart`, `/favorites` and `/orders`
  answer 200 in 40 seconds. The stylesheet the HTML references serves 200.
- `make fresh` against the running server re-seeds and the storefront still
  answers 200 with the seeded listings.
- `make test`: 527 runs, 1604 assertions, 0 failures. `make coverage`: 100%
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

1. **No mail leaves the container.** `MagicLinkMailer` renders and sends the
   sign-in link, and `delivery_method :test` outside production holds it in
   `ActionMailer::Base.deliveries`. Production needs the SMTP settings that are
   commented out in `config/environments/production.rb`, and
   `Notification#deliver_by_email` does nothing.
2. **20 files under `app/` have no test of their own**: `ApplicationController`,
   the four base controllers, the five controller concerns
   (`AdminAuthentication`, `CustomerAuthentication`, `SellerAuthentication`,
   `MagicLinkSender`, `MessagingSite`), the two helpers, `ApplicationMailer`,
   `ApplicationRecord`, the `EmailAddress` and `Messaging` model concerns,
   `TransitionError`, and `CustomerMerge`, `Favorite` and `OrderItem`. Every
   one is at 100% line coverage through the tests of its callers.
3. **Refunds are whole-fulfillment only.** A `refunds` row is always the
   fulfillment's entire `subtotal_cents`; there is no partial line refund and
   no way to refund shipping separately. `docs/alignment.md` §4.1 fixes that
   for this cut.
4. **No image variants.** There is no libvips in the image, so
   `Listing#image_url` serves the original blob. Asking for a variant would
   raise.
5. **Shipment tracking is two text fields.** No carrier integration; the
   customer confirms delivery from the order page.
6. **Seeded listings carry no image files.** `PlaceholderImage` renders a
   generated SVG from the title, so the storefront demo shows shapes rather
   than artwork. An uploaded image is served for listings created through the
   portal.
7. **`allow_browser versions: :modern`** answers 406 to old browsers, which is
   stock Rails and the one place the prototype needs a modern browser it does
   not otherwise need.
8. **An intermittent `RecordNotFound` was seen once in
   `Shop::ConversationsControllerTest`** on a single full-suite run; it passed
   on reruns and in isolation, and 24 further full-suite runs (5 with fixed
   seeds, 19 with randomized ones) reproduced nothing. Two candidate causes
   were investigated: `Message#broadcast_arrival` and `Conversation#read_by!`
   writing Turbo Stream broadcasts to `solid_cable_messages` outside a test's
   rollback is ruled out — `config/cable.yml` sets `adapter: test` for the
   test environment, and `solid_cable_messages` holds zero rows both before
   and after a full suite run. `PrefixedUlid`'s module-level `@clock`/`@value`
   persisting across tests, combined with a clock that jumps backward (a
   `travel_to`/`freeze_time` block, or `unused_id`'s un-frozen `Time.current`
   landing behind an earlier frozen mint), does not look like a real
   duplicate-id vector on inspection: every change in the millisecond
   `next_value` sees — earlier or later than the last one — draws a fresh
   80-bit random value, so only ids minted back-to-back on one unchanged
   millisecond share a lineage, and those are unique by construction (a
   monotonic counter that never resets). A "clock never goes backward" clamp
   was tried and reverted: it broke two tests that deliberately rely on an
   explicit past `at:` being embedded in the id exactly as given
   (`PrefixedUlidTest#test_the_leading_digits_are_the_millisecond_the_caller's_clock_reads`,
   `PrefixedIdTest#test_a_row_built_under_a_frozen_clock_mints_an_id_stamped_with_that_instant`),
   which is deliberate, tested behaviour, not a drift bug. The flake stands
   unreproduced.
9. **No site renders its own 400 or 404.** `Admin::BaseController#filter_from`
   / `#id_filter` raise `ActionController::BadRequest` for a filter value a
   page does not offer, and `ActiveRecord::RecordNotFound` answers an unknown
   id everywhere. Neither is rescued anywhere in the controller tree, and
   there is no `config.exceptions_app`, so both fall through to Rails'
   static, un-themed `public/400.html` and `public/404.html` — shared by the
   storefront, seller portal, and admin site, with no site's own layout or
   nav. Node's `plugins/error-pages.ts` renders both statuses inside the
   site's own layout. Building that for all three sites is out of scope for
   any one ticket that touches a single site.
10. **The admin directory lists are unpaginated.** `/admin/sellers`,
    `/admin/customers`, `/admin/listings`, `/admin/orders`,
    `/admin/fulfillments`, `/admin/ledger` and `/admin/payouts` render every
    matching row, and the seller selects in their filter forms hold every row
    of that table. The page cost does not grow with the row count in
    statements — every list carries a `count_queries` assertion that pins
    that — but it does in rows rendered. The storefront's `Page` value object
    is the shape to reuse.
11. **Nothing seeded shows a decline or a refund.** `db/seeds/order_history.rb`
    walks placement through delivery and payout; the reversal surfaces are
    reachable in the app and covered by tests, but the demo data does not
    exercise them. `/admin/accounting` and `/admin/ledger` render correctly
    for it either way — a page with nothing declined or refunded is the zero
    case their tests already cover — but a reviewer clicking through the
    seeded demo never sees `fees_refunded_cents` or a `refunded` ledger row
    without triggering one by hand.
12. **Nothing seeded is removed or blocked.** `db/seeds.rb` writes no
    `listing_removals` or `customer_blocks` row, so a reviewer clicking
    through the seeded demo never sees a removed listing's storefront 404 or
    a blocked customer's notice without triggering one by hand.
13. **A blocked customer can evade the block by merging into a different,
    unblocked account.** `customer_blocks` is deliberately left behind by a
    customer merge (`IMPRV-003`, matching `FEAT-021`'s decision and the Node
    and PHP prototypes) — see `docs/identity.md`'s "The merge is a fold, not
    a re-point" section. Consequence, confirmed reproducible: a blocked
    anonymous customer who verifies with an email address that belongs to an
    existing, unblocked verified account resolves forward to that account —
    the block stays on the abandoned anonymous row, and
    `Customer.from_cookie` follows `customer_merges` to the unblocked
    survivor, whose own `blocked?` reads false. This is shared behaviour
    across all three prototypes, not a Rails-specific bug; it is a product
    decision (which block should win when a merge's two sides disagree, or
    whether re-pointing should happen after all) that needs an owner.
14. **The session-fixation code comments were imprecise until this ticket.**
    `CustomerIdentity#sign_in_customer`, `SellerAuthentication#sign_in_seller`,
    and `AdminAuthentication#sign_in_admin` described
    `request.session_options[:renew] = true` as "Rack's session-fixation
    defense." With `ActionDispatch::Session::CookieStore` the session travels
    as a whole inside a signed-and-encrypted, content-bound cookie an
    attacker never receives — that is what actually defeats fixation here.
    `renew` rotates the session id on top of that as defence in depth; it is
    not the primary defense the old comments claimed. The three comments and
    `docs/identity.md`'s "Three actors, one browser" section now say this.

## Suggested next steps

1. Configure SMTP for production and give `Notification#deliver_by_email` its
   own mailer. Closes gap 1 and lets `MAGIC_LINK_DEBUG_ALERT=false` take the
   debug alert off the demo path.
2. Seed a declined fulfillment and an admin refund in `db/seeds/order_history.rb`
   so the demo shows the reversal surfaces. Closes gap 11.
3. Delete or use the model relations no caller reads. Shrinks gap 2.
4. Attach real images in `db/seeds.rb` and add libvips to the image so the
   storefront can ask for a thumbnail variant. Closes gaps 4 and 6.
5. Seed a removed listing and a blocked customer in `db/seeds.rb` so the demo
   shows both without triggering one by hand. Closes gap 12.
6. Decide, as a product call, whether a merge should re-point an active
   `customer_blocks` row and how to resolve the case where both sides of a
   merge are blocked. Closes gap 13.
