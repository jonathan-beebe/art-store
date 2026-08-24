# Review against the brief

Every requirement in `__local__/prompts/initial-prompt.md`, its status, and the
route helper and test class that prove it. Verified on FEAT-008 against a clean
first run (527 tests, 100% line coverage) and again on FEAT-014 after the
messaging feature (737 tests, 100% line coverage).

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Seller portal

| Requirement | Status | Route helper | Test |
| --- | --- | --- | --- |
| Create an account | done | `seller_login`, `seller_send_magic_link`, `verify_magic_link` | `Auth::SellerSessionsControllerTest`, `Auth::MagicLinksControllerTest` |
| Add items straight after sign-in | done | `new_seller_listing`, `seller_listings` (POST) | `Seller::ListingsControllerTest` |
| Manage listings | done | `seller_listings`, `edit_seller_listing`, `seller_listing` (PATCH), `seller_listing_status` | `Seller::ListingsControllerTest`, `Seller::ListingStatusesControllerTest` |
| Activity per listing: views, favorites, cart adds | done | `seller_listing` | `Seller::ListingsControllerTest`, `ListingTest`, `ListingEventTest` |
| Reports on sales | done | `seller_earnings` (Sales table) | `Seller::EarningsControllerTest` |
| Tools for fulfillment | done | `seller_orders`, `seller_order`, `seller_order_shipment` | `Seller::OrdersControllerTest`, `Seller::ShipmentsControllerTest` |
| Accumulated earnings and payouts | done | `seller_earnings`, `seller_earnings_payout` | `Seller::EarningsControllerTest`, `Seller::PayoutsControllerTest`, `PayoutTest` |
| Flow: account → add items → `for_sale` reaches the storefront | done | the chain above plus `root` | `SmokeTest` |
| Magic links, no passwords | done | `verify_magic_link` | `Auth::MagicLinksControllerTest` |
| Theme: vanilla controls, system type, semantic HTML, stock Tailwind | done | `layouts/seller` | none (visual) |

The portal renders `table`, `dl`, `fieldset`/`legend`, `address` and `caption`
with no component library and no font download. `app/assets/tailwind/application.css`
is `@import "tailwindcss"` and nothing else.

## Customer site

| Requirement | Status | Route helper | Test |
| --- | --- | --- | --- |
| Browse | done | `root` (search, medium filter, pagination), `shop_listing` | `Shop::StorefrontControllerTest`, `Shop::ListingsControllerTest`, `ListingTest`, `PageTest` |
| Favorite | done | `shop_favorites`, `shop_toggle_favorite` | `Shop::FavoritesControllerTest`, `CustomerTest` |
| Cart | done | `shop_cart`, `shop_add_to_cart`, `shop_remove_from_cart` | `Shop::CartsControllerTest`, `Shop::CartItemsControllerTest`, `CartTest`, `CartItemTest` |
| Purchase | done | `shop_checkout`, `shop_place_order`, `shop_pay_order` | `Shop::CheckoutsControllerTest`, `Shop::OrderPaymentsControllerTest` |
| Anonymous customer id per visitor | done | every storefront route, via `CustomerIdentity` | `CustomerIdentityConcernTest` |
| Anonymous ids merge into the account on sign-in | done | `verify_magic_link` | `CustomerTest`, `Auth::MagicLinksControllerTest` |
| Magic links, no passwords | done | `customer_login`, `customer_send_magic_link` | `Auth::CustomerSessionsControllerTest` |
| Fake card 4242 4242 4242 4242 | done | `shop_pay_order` | `FakeCardTest` |
| Failed payments | done | `shop_pay_order`, retry form on `shop_order` | `Shop::OrderPaymentsControllerTest`, `OrderTest`, `OrderLifecycleTest` |
| Guest checkout, verification before finalizing | done | `shop_place_order` → `verify_magic_link` → `shop_order_payment` | `Shop::CheckoutsControllerTest`, `SmokeTest` |
| Whole purchase and fulfillment flow mocked | done | the chain above plus `seller_order_shipment`, `shop_confirm_delivery` | `SmokeTest`, `OrderLifecycleTest` |
| Theme: bright, open, wares over brand | done | `layouts/shop` | none (visual) |

## Fulfillment, escrow, payout

| Requirement | Status | Route helper | Test |
| --- | --- | --- | --- |
| Tell sellers an item sold | done | `seller_notifications` | `Seller::NotificationsControllerTest`, `NotificationTest` |
| Walk sellers through fulfillment | done | `seller_order`, `seller_order_shipment` | `Seller::ShipmentsControllerTest`, `FulfillmentTest` |
| Notify customers of shipment | done | `shop_account` inbox, `shop_read_notification` | `Shop::AccountControllerTest`, `Shop::NotificationReadsControllerTest` |
| Escrow held on payment, released on delivery | done | `shop_confirm_delivery` | `FulfillmentTest`, `LedgerEntryTest` |
| Report of sold goods and funds due | done | `seller_earnings` | `Seller::EarningsControllerTest` |
| Pay out at the end of every week | done | `payouts:run`, `seller_earnings_payout` | `PayoutsTaskTest`, `PayoutTest`, `PayoutPeriodTest` |

## Messaging and the admin site

Added after the brief (FEAT-009 … FEAT-014), so these are the feature's own
claims rather than requirements from `initial-prompt.md`.

| Capability | Status | Route helper | Test |
| --- | --- | --- | --- |
| An admin site with a seeded-only account | done | `admin_login`, `admin_root`, `admin_seller`, `admin_customer` | `Auth::AdminSessionsControllerTest`, `Admin::DashboardControllerTest`, `Admin::SellersControllerTest`, `Admin::CustomersControllerTest`, `AdminTest` |
| A directory over every seller, customer, listing, order and fulfillment | done | `admin_sellers`, `admin_customers`, `admin_listings`, `admin_orders`, `admin_fulfillments` and each `…/:id` — see [`admin.md`](admin.md) | `Admin::SellersControllerTest`, `Admin::CustomersControllerTest`, `Admin::ListingsControllerTest`, `Admin::OrdersControllerTest`, `Admin::FulfillmentsControllerTest` |
| One conversation record for four kinds | done | — | `ConversationTest`, `MessageTest` |
| Inbox and thread on all three sites, non-participant answers 404 | done | `shop_conversations`, `seller_conversations`, `admin_conversations` and each `…_conversation` | `Shop::ConversationsControllerTest`, `Seller::ConversationsControllerTest`, `Admin::ConversationsControllerTest` |
| Reply, with the counterpart notified at their own path | done | each site's `…_conversation_messages` | `Shop::MessagesControllerTest`, `Seller::MessagesControllerTest`, `Admin::MessagesControllerTest`, `NotificationTest` |
| Support threads against `Admin.on_duty` | done | `shop_support`, `seller_support` | `Shop::SupportsControllerTest`, `Seller::SupportsControllerTest` |
| The desk opens a thread from either account page | done | `admin_seller_conversation`, `admin_customer_conversation` | `Admin::SellerConversationsControllerTest`, `Admin::CustomerConversationsControllerTest` |
| A thread per fulfillment from either order page | done | `shop_fulfillment_conversation`, `seller_order_conversation` | `Shop::FulfillmentConversationsControllerTest`, `Seller::OrderConversationsControllerTest` |
| Anonymous shopper asks a question on a listing | done | `shop_listing_questions` | `Shop::ListingQuestionsControllerTest`, `CustomerTest` (merge carries the thread) |
| Seller publishes, edits, unpublishes an FAQ; the storefront shows it | done | `seller_listing_faqs` | `Seller::FaqsControllerTest`, `Shop::ListingsControllerTest`, `ListingFaqTest` |
| Unread count in every nav, read on opening a thread | done | every layout | `ConversationTest`, the three inbox tests |
| Live message and badge over Turbo streams | done | `turbo_stream_from` on each thread page | `MessageTest`, `ConversationTest`, the three thread-page tests |
| A shopper's question through to a published FAQ on the listing page | done | the chain above | `SmokeTest` |

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

| Requirement | Status | Evidence |
| --- | --- | --- |
| Modern Rails | done | `Gemfile.lock` — Rails 8.1.3.1 on Ruby 3.3.12 |
| SQLite | done | `config/database.yml`, `src/storage/development.sqlite3` |
| Tailwind | done | `tailwindcss-rails` 4.6.0 / tailwindcss 4.3.3, standalone binary, no Node |
| Semantic HTML + CSS | done | `app/views/**` |
| No JavaScript required | done | every flow is a form POST that redirects; `app/javascript/application.js` is one `import "@hotwired/turbo-rails"`, and with it blocked or absent every page, form, link and redirect behaves as it did before Turbo |
| Hotwire | done | `turbo-rails` 2.0.23, `importmap-rails` 2.2.3, `solid_cable` 4.0.2 — thread pages and the nav badge update over Action Cable with the broadcast queue in the app's own SQLite file |

## Development workflow

| Requirement | Status | Evidence |
| --- | --- | --- |
| Everything dockerized, nothing on the host | done | `Dockerfile`, `docker-compose.yml`, `Makefile` — every target is a `docker compose` wrapper |
| All source in `src` | done | `prototype/rails/src/` |
| Tests mirror the code under `test/` | partial | 20 of the 85 files under `app/` have no test of their own (see Known gaps); all are at 100% line coverage through the tests of their callers |
| `/test*` and `/tdd*` skills | partial | process, not visible in the artifacts; the shape they call for holds — pure core tests, HTTP tests for the shell |
| `/work-*` skills for work items | done | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — 30 tickets |
| `/write-*` skills | partial | process; the comments in the tree carry reasons, not restatements |
| TDD flow | partial | process; each ticket's `## Working` notes record it |
| Measure coverage, keep it high | done | `make coverage` — 100% line coverage, `COVERAGE_MIN=80` enforced |
| Functional core / imperative shell | done | The value objects in `app/models` (`Money`, `Page`, `PayoutPeriod`, `FakeCard`, `PlaceholderImage`, `LedgerEntry::Balance`, `ListingEvent::Totals`, `ListingEvent::Day`) are pure — no I/O, no clock, no random; time and ids arrive as arguments. No controller holds a domain `if`: every branch reads a record predicate (`Fulfillment#can_transition_to?`, `Order#unpaid?`, `Order#payable_by?`, `Listing#purchasable?`, `MagicLink#usable?`, `order.persisted?`), or a shell fact (signed in, empty cart, missing row) |
| `/diagramming` for docs | done | `docs/architecture.md`, `identity.md`, `orders.md`, `escrow.md`, `messaging.md`, `data-model.md`, `ontology.md` |

## Goal

| Requirement | Status | Evidence |
| --- | --- | --- |
| Back-office for artists to create an account, list art, manage sales | done | `/seller/**` |
| Customer site for browsing | done | `/` |
| Mocked cart and payment with a fake card, success and failure | done | `FakeCard` — 4242… approves; 4000…0002 and 4000…9995 decline; anything else is an invalid number |
| Magic links for both sides, printed to the screen in a debug alert | done | `MagicLinkSender#send_magic_link` → `layouts/_debug_alert` (`MAGIC_LINK_DEBUG_ALERT`) |
| A hook where email goes later | done | `MagicLinkMailer#sign_in` sends the link; `Notification#deliver_by_email` is still empty |
| Guest checkout requiring verification before the order finalizes | done | `Shop::CheckoutsController#create` → `Shop::OrderPaymentsController` |
| Work queued and delivered by agents | done | `work/journal.md` — FEAT-001 … FEAT-014 and RFCTR-001 … RFCTR-013 |
| Delivered in `./prototype/rails/` with a complete README and a docs folder | done | `README.md`, `docs/` |

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
2. **The payout button pays every seller**, not the signed-in one. It is
   labelled a debug control on `seller_earnings` and the controller says so.
   `payouts:run` is the real entry point.
3. **20 files under `app/` have no test of their own**: `ApplicationController`,
   the four base controllers, the five controller concerns
   (`AdminAuthentication`, `CustomerAuthentication`, `SellerAuthentication`,
   `MagicLinkSender`, `MessagingSite`), the two helpers, `ApplicationMailer`,
   `ApplicationRecord`, the `EmailAddress` and `Messaging` model concerns,
   `TransitionError`, and `CustomerMerge`, `Favorite` and `OrderItem`. Every
   one is at 100% line coverage through the tests of its callers.
4. **A merge can leave a customer holding two carts.**
   `Customer#absorb` re-points the anonymous cart rather than
   folding it into the account's cart, and `Customer#current_cart` then shops with
   whichever holds more items. Items in the other cart are still in the
   database and no page shows them.
5. **Refunds are whole-fulfillment only.** A `refunds` row is always the
   fulfillment's entire `subtotal_cents`; there is no partial line refund and
   no way to refund shipping separately. `docs/alignment.md` §4.1 fixes that
   for this cut.
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
10. **An intermittent `RecordNotFound` was seen once in
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
11. **No site renders its own 400 or 404.** `Admin::BaseController#filter_from`
    / `#id_filter` raise `ActionController::BadRequest` for a filter value a
    page does not offer, and `ActiveRecord::RecordNotFound` answers an unknown
    id everywhere. Neither is rescued anywhere in the controller tree, and
    there is no `config.exceptions_app`, so both fall through to Rails'
    static, un-themed `public/400.html` and `public/404.html` — shared by the
    storefront, seller portal, and admin site, with no site's own layout or
    nav. Node's `plugins/error-pages.ts` renders both statuses inside the
    site's own layout. Building that for all three sites is out of scope for
    any one ticket that touches a single site.
12. **The admin directory lists are unpaginated.** `/admin/sellers`,
    `/admin/customers`, `/admin/listings`, `/admin/orders` and
    `/admin/fulfillments` render every matching row, and the seller and
    customer selects in their filter forms hold every row of those tables.
    The page cost does not grow with the row count in statements — every list
    carries a `count_queries` assertion that pins that — but it does in rows
    rendered. The storefront's `Page` value object is the shape to reuse.
13. **The refund figures have no accounting page yet.**
    `Fulfillment.fees_earned_cents` and `Fulfillment.fees_refunded_cents` fold
    the forgone platform fee, and `LedgerEntry` carries the `refunded` entry
    type, but `/admin/accounting` and `/admin/ledger` are FEAT-020's. The
    ledger browser's `refunded` filter value works from the enum the moment
    that page exists.
14. **Nothing seeded shows a decline or a refund.** `db/seeds/order_history.rb`
    walks placement through delivery and payout; the reversal surfaces are
    reachable in the app and covered by tests, but the demo data does not
    exercise them.

## Suggested next steps

1. Configure SMTP for production and give `Notification#deliver_by_email` its
   own mailer. Closes gap 1 and lets `MAGIC_LINK_DEBUG_ALERT=false` take the
   debug alert off the demo path.
2. Fold the anonymous cart into the account's cart during the merge, so one
   customer has one cart. Closes gap 4.
3. Scope the payout button to the signed-in seller, or drop it and keep
   `payouts:run` as the single entry point. Closes gap 2.
4. Seed a declined fulfillment and an admin refund in `db/seeds/order_history.rb`
   so the demo shows the reversal surfaces. Closes gap 14.
5. Delete or use the model relations no caller reads. Shrinks gap 3.
6. Attach real images in `db/seeds.rb` and add libvips to the image so the
   storefront can ask for a thumbnail variant. Closes gaps 6 and 8.
