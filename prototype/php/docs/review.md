# Review against the brief

Every requirement in `__local__/prompt.md`, its status, and the route and test
class that prove it.

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Engineering quality

| Measure | Where it is set | Result |
| --- | --- | --- |
| Formatting | `src/pint.json` — `laravel` preset plus `strict_comparison`, `strict_param`, `void_return` | `make lint` clean over 610 files |
| Static analysis | `src/phpstan.neon` — PHPStan/Larastan, `level: max` over `app`, `database`, `routes`, `tests` | 0 errors, no `excludePaths`, no `ignoreErrors`, no baseline |
| Strict types | Pint's `declare_strict_types`, re-asserted by `tests/Arch.php` | every PHP file |
| Tests | Pest, sidecars beside the file they cover | 1827 tests, 4934 assertions |
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
| Pay out at the end of every week | done | `payouts:run`, `admin.payouts.run` | `Console\Commands\RunWeeklyPayoutsTest`, `Domain\Escrow\PayoutPeriodTest`, `Admin\RunPayoutControllerTest` |
| Customer cancels an unpaid order | done | `shop.order.cancel` | `Actions\Orders\CancelOrderTest`, `Shop\OrderCancellationControllerTest` |
| Stale unverified orders are swept | done | `orders:sweep` (`make sweep`, hourly on the scheduler) | `Actions\Orders\SweepStaleOrdersTest` |
| Seller declines a parcel, stock returns | done | `seller.orders.decline` | `Actions\Fulfillment\DeclineFulfillmentTest` |
| Admin cancels an unpaid order, refunds a fulfillment | done | `admin.orders.cancel`, `admin.fulfillments.refund` | `Actions\Fulfillment\RefundFulfillmentTest`, `Actions\Escrow\IssueRefundTest` |
| Refund folds through the ledger in all three timings | done | — (domain) | `Domain\Escrow\LedgerBalanceTest`, `Actions\Escrow\RunWeeklyPayoutTest` |

## Admin site and messaging

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Admin actor, seeded not signed up, own guard | done | `auth.admin.login`, `auth.admin.send`, `auth.magic.verify` | `Auth\AdminLoginControllerTest`, `Requests\Auth\SendAdminMagicLinkRequestTest`, `Actions\Auth\SignInAdminTest` |
| Admin dashboard with a tally for every status, including empty ones | done | `admin.dashboard` | `Admin\DashboardControllerTest`, `Domain\Reports\*TallyTest` |
| Directory: sellers, customers, listings, orders, fulfillments | done | `admin.sellers.*`, `admin.customers.*`, `admin.listings.*`, `admin.orders.*`, `admin.fulfillments.*` | `Admin\SellerControllerTest`, `Admin\CustomerControllerTest`, `Admin\ListingControllerTest`, `Admin\OrderControllerTest`, `Admin\FulfillmentControllerTest` |
| Accounting, ledger browser, site stats | done | `admin.accounting`, `admin.ledger`, `admin.stats` | `Admin\AccountingControllerTest`, `Admin\LedgerControllerTest`, `Admin\StatsControllerTest` |
| Listing removals, temporary and permanent | done | `admin.listings.removals.store`/`.lift` | `Actions\Listings\RemoveListingTest`, `Actions\Listings\LiftListingRemovalTest` |
| Page views rolled up, listing views collapsed per hour | done | — (middleware) | `Http\Middleware\RollUpPageViewsTest`, `Actions\Analytics\RecordPageViewTest`, `Actions\Listings\RecordListingEventTest` |
| Block a customer from buying; browsing and messaging stay open except posting | done | `admin.customers.blocks.store`, `.blocks.lift` | `Admin\CustomerBlockControllerTest`, `Admin\LiftCustomerBlockControllerTest`, `Actions\Customers\BlockCustomerTest`, `Actions\Customers\LiftCustomerBlockTest`, `Domain\Customers\CustomerStandingTest` |
| One conversation model for four kinds, one thread per subject under contention | done | — (domain) | `Domain\Messaging\ConversationKindTest`, `Domain\Messaging\ConversationSubjectTest`, `Models\ConversationTest` |
| Read is ownership-only and denies as not found; post adds standing | done | — (policy) | `Policies\ConversationPolicyTest` |
| Anonymous shopper asks a seller a question on a listing | done | `shop.listing.questions` | `Shop\ListingQuestionControllerTest` |
| Seller replies, publishes the answer as a listing FAQ | done | `seller.messages.show`/`.store`, `seller.listings.faqs.store`/`.update`/`.destroy` | `Seller\MessageControllerTest`, `Seller\ListingFaqControllerTest`, `Actions\Messaging\PublishListingFaqTest` |
| Published FAQ shows on the listing for every visitor | done | `shop.listing` | `Shop\ListingControllerTest` |
| Fulfillment thread reachable from an order on both sides | done | `seller.orders.messages`, `shop.order.messages` | `Seller\OrderMessageControllerTest`, `Shop\OrderMessageControllerTest` |
| Support thread from the seller portal and the storefront, admin answers | done | `seller.support`, `shop.support`, `admin.sellers.messages`, `admin.customers.messages`, `admin.messages.show`/`.store` | `Seller\SupportControllerTest`, `Shop\SupportControllerTest`, `Admin\SellerMessageControllerTest`, `Admin\CustomerMessageControllerTest`, `Admin\MessageControllerTest` |
| Nav badge: per-thread and total unread, one rule | done | every page, all three sites | `Models\MessageTest` (`unreadBy`, `unreadInInboxOf`), `View\Composers\*LayoutComposerTest` |
| Badge updates live without a page load | done | `seller.events`, `shop.events`, `admin.events` | `Support\UnreadCountStreamTest`, `Seller\EventsControllerTest`, `Shop\EventsControllerTest`, `Admin\EventsControllerTest` |
| Anonymous customer's threads and sent messages follow them through a merge | done | `auth.magic.verify` | `Actions\Customers\MergeAnonymousCustomerTest`, `Models\ConversationTest` (`moveCustomer`) |
| Seed data: one thread per kind, one published FAQ, non-zero unread | done | `db:seed --class=MessagingSeeder` | `Database\seeders\MessagingSeederTest`, `Tests\SmokeTest` |

## Tech stack

| Requirement | Status | Evidence |
| --- | --- | --- |
| PHP | done | `Dockerfile` — `php:8.3-cli`, PHP 8.3.33 |
| Laravel | done | `composer.json` — `laravel/framework ^13.17` |
| SQLite | done | `config/database.php`, `database/database.sqlite` |
| Tailwind | done | `package.json` — `tailwindcss` v4 through Vite |
| Semantic HTML + CSS | done | `resources/views/**` |
| No JavaScript required | done | every page works and every action completes with JavaScript off; the one `<script defer>` in each layout (`src/public/live-badge.js`, ~20 dependency-free lines) is a progressive enhancement — it keeps the unread-message badge current over the `/events` SSE stream while a page sits open, and returns immediately when `EventSource` is absent |

## Development workflow

| Requirement | Status | Evidence |
| --- | --- | --- |
| Everything dockerized, nothing on the host | done | `Dockerfile`, `docker-compose.yml`, `Makefile` |
| All source in `src` | done | `prototype/php/src/` |
| Tests are sidecars next to the code | done | every non-abstract, non-interface, non-enum, non-trait file under `app/` has a sidecar; `tests/SidecarsTest.php`'s exception list is empty |
| `/test*` and `/tdd*` skills | partial | process, not visible in the artifacts; the shape they call for (sidecars, pure core tests, HTTP tests for the shell) holds |
| `/work-*` skills for work items | done | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — 30 tickets |
| `/write-*` skills | partial | process; comments in the tree are decision records, no restatements, no adverbs |
| TDD flow | partial | process; each ticket's `## Working` notes record the flow |
| Measure coverage, keep it high | done | `make coverage` — 100.0% of lines |
| Functional core / imperative shell | done | `app/Domain/**` is pure (no I/O, no clock, no random); actions and controllers sequence it. No controller holds a domain `if` — every branch reads a domain predicate (`OrderPayment::isPayableBy`, `ListingAvailability::isPurchasable`, `MagicLinkStatus`) or a shell fact (auth, empty cart, missing row) |
| `/diagramming` for docs | done | Mermaid throughout `docs/`: deployables, layers, ER and notification flow in `architecture.md`; sign-in, admin sign-in, and merge sequences in `identity.md`; the two state machines and the checkout sequence in `orders.md`; the ledger flow in `escrow.md`; the subject-key, FAQ, authorization, block, unread-count, live-badge, and notification flows in `messaging.md`; the concept map in `ontology.md` |

## Goal

| Requirement | Status | Evidence |
| --- | --- | --- |
| Back-office for artists to create an account, list art, manage sales | done | `/seller/**` |
| Customer site for browsing | done | `/` |
| Mocked cart and payment with a fake card, success and failure | done | `Domain\Payments\FakeCard` — 4242… approves, 4000…0002 and 4000…9995 decline, anything else is an invalid number |
| Magic links for every actor, printed to the screen in a debug alert | done | `Notifications\MagicLinkIssued` on `Notifications\Channels\SessionFlashChannel` → `<x-debug-alert>` on all three layouts |
| A hook where email goes later | done | `toMail()` on every notification; `config/magic_links.php` → `delivery=mail` and `config/notifications.php` → `channels` turn it on |
| Guest checkout requiring verification before the order finalizes | done | `Shop\CheckoutController::place` |
| Work queued and delivered by agents | done | `work/journal.md` — every ticket in `work/3-done/` |
| Delivered in `./prototype/php/` with a complete README and a docs folder | done | `README.md`, `docs/` |

## Against the alignment contract

`docs/alignment.md` fixes the shapes the three prototypes share. What this one
implements, section by section.

| Contract | Status | Where |
| --- | --- | --- |
| §1 Prefixed ULID identifiers | done | `App\Domain\Identifiers\PrefixedId`, `App\Models\Concerns\HasPrefixedUlid`; every domain table plus `notifications`. Two recorded deviations — the minting clock and the ordering tiebreak (gaps 9 and 13) |
| §2 Structured JSON logs | done | `App\Logging\StoryFormatter`, `App\Support\Story`, `App\Http\Middleware\LogRequestStory`; one `stdout` channel in every environment. 32 of the §2.3 events; one recorded deviation (gap 10) |
| §3 Rate limits and security headers | done | `App\Domain\RateLimiting\*`, `App\Support\RateLimiting\RateLimitGate`, `App\Http\Middleware\SecurityHeaders`; all seven limits, 429 with `Retry-After`, CSP and nosniff and Referrer-Policy on every response, HSTS in production |
| §4 Transaction lifecycle | done | `App\Actions\Orders\CancelOrder`, `SweepStaleOrders`, `App\Actions\Fulfillment\DeclineFulfillment`, `RefundFulfillment`, `App\Actions\Escrow\IssueRefund`; the `refunds` table and the `refunded` ledger entry across all three timings. One amendment proposed — the fold groups by fulfillment |
| §5 Admin feature set | done | `App\Http\Controllers\Admin\*` — directory, dashboard, accounting, ledger, stats, moderation, payouts. See `docs/admin.md` |
| §6 Workflows | done | the `Makefile`'s target vocabulary, `make check` as the commit gate, `.github/workflows/php.yml` running the same command |

The full amendment list this prototype proposes against the contract is in the
alignment tickets' `## Working` sections under `work/3-done/`.

## Compared to the Node prototype

Both prototypes build the same messaging feature — one conversation table for
four kinds, read-is-participant/post-adds-standing authorization, a live
unread badge — on the four points where the two designs actually differ.

- **One thread per subject.** This prototype adds a `conversations_subject_key_unique`
  index and reaches it through `Conversation::firstOrCreate(['subject_key' =>
  ...], ...)`; a concurrent duplicate insert fails the constraint and Laravel's
  `createOrFirst` catches it behind a savepoint and re-reads. The invariant
  lives in the schema, so it holds even against a write path this design did
  not anticipate. Node's `planConversation` is a pure function that matches a
  candidate subject against the rows already read for it, with no unique
  constraint backing the match — correctness there rests on the transaction
  the read and the insert both run inside, not on the database refusing a
  duplicate. Node's version is the one a unit test can exercise with no
  database at all; this one cannot, because the invariant it enforces is not
  expressible without one.
- **Authorization.** `ConversationPolicy` is a class Laravel's own machinery
  resolves — `Gate::inspect()` from a form request, `@can`/`@visitorCan` from
  a view, `denyAsNotFound()` on route-model binding — so the same rule reaches
  three call sites with one registration. Node's `conversationAccess` is a
  pure function returning `{ mayRead, mayPost }`; nothing registers it, and
  each route decides what a `false` means (a `null` from the action, rendered
  as 404). Node's function costs nothing to unit test and carries no framework
  dependency; this policy costs a `Policies` registration and a Larastan stub
  for the abstraction, and buys the three call sites agreeing without each one
  restating the rule.
- **The live badge.** This design polls: `UnreadCountStream` re-reads the
  actor's count on a fixed tick for a fixed lifetime (`TICK_SECONDS = 2`,
  `LIFETIME_SECONDS = 25`), bounded because `artisan serve`'s one-worker-per-request
  model means an open stream holds a worker for its whole life regardless of
  whether anything changed — the reason `PHP_CLI_SERVER_WORKERS` had to be
  raised at all, and still the reason a closed tab holds its worker for a few
  seconds after disconnecting (see `docs/messaging.md` § "The live badge").
  Node's stream is push-driven: an in-process `EventEmitter` fires `changed`
  once after any request that wrote something, every open stream re-reads its
  own count on that event, and the stream ends only on client disconnect or
  app shutdown, not a fixed deadline — no tick loop, no idle polling, and an
  explicit `retry: 3000` hint this design's response does not send. The cost
  Node's own comment names: the bus is in-process, so a second app instance
  would carry its own and never hear a write that landed on the other. This
  design pays a worker for the length of every stream instead; Node pays
  nothing between changes but could not span two instances without a shared
  broker.
- **Model binding and form requests, against hand-rolled route schemas.**
  `PostMessageRequest` bundles `authorize()`, `rules()`, and a typed
  `body(): MessageBody` getter behind one class Laravel resolves before the
  controller runs; `Conversation $conversation` arrives already loaded through
  route-model binding. Node validates request shape with a Zod schema
  (`submittedForm`, `idParams`) registered on the route, and calls the access
  function and the action directly in the handler body. Neither is less code
  for the same guarantee — the Laravel side spends a class per route on
  authorize-then-validate-then-typed-getter; the Node side spends the same
  three steps as three lines in the handler. The leverage Laravel's shape
  buys is the same one `ConversationPolicy` buys: three sites' worth of routes
  calling the same class rather than restating the sequence, not fewer lines
  on any one route.

## Known gaps

1. **Mail is written but unproven.** Every notification implements
   `toMail()` and `MAGIC_LINK_DELIVERY=mail` / `NOTIFICATION_CHANNELS` turn
   the channel on, but no mailer is configured beyond `MAIL_MAILER=log`, so
   nothing has been sent to a real inbox.
2. **Shipment tracking is a text field.** No carrier integration; the customer
   confirms delivery from the order page.
3. **Replacing a listing image that fails to store keeps the old one
   silently.** The create path flashes the failure; the update path does not.
4. **Seeded listings carry a generated placeholder SVG**, not artwork, and the
   seeder writes no `refunds` row — seed data shows the happy path only.
5. **A closed messaging tab does not free its SSE worker at once.** The
   generator yields only on a change, so `connection_aborted()` is checked
   less often than a keepalive would allow; measured, an abandoned stream
   holds its worker for about five seconds. See `docs/messaging.md` § "The
   live badge".
6. **A cookieless client of `/events` mints a `customers` row per request**,
   the same as any other storefront route with no `customer_id` cookie — a
   crawler that ignores cookies holds one worker per reconnect. Bounded, not a
   new hole.
7. **The reply forms' `maxlength` attributes are literals**, not reads of
   `MessageBody::MAX_LENGTH` / `FaqDraft::*_MAX_LENGTH`. The form request
   still enforces the real limit either way.
8. **The seller's listing index carries an N+1 on the active removal.**
   `Listing::currentRemoval()` always issues a fresh query, and the index
   view asks `availableTransitions()` per row. Bounded to one seller's own
   catalogue. The admin listings list does not have it — it eager-loads
   `activeRemoval`.
9. **Ids are minted from the application clock, not the action's moment.**
   `docs/alignment.md` §1 says the id is minted from the clock the action
   already receives; `HasPrefixedUlid` mints from Laravel's freezable
   `Date::now()`. Relative creation order holds; the ULID's time bits do not
   track a seeded row's domain date.
10. **The opening `http.request` line carries `request_id` alone.**
    `session_id` and the actor marks join inside the `web` group, so they
    appear on the `did` line and on every domain line between, but not on the
    `will` line. §2.1 marks `session_id` as present on requests.
11. **An unrecognised admin filter value reads as absent.** `?status=nonsense`
    lists everything rather than answering 400; Laravel's `$request->enum()`
    treats absent, empty, and unrecognised alike. The Node prototype answers
    400 for the same input.
12. **A merged cart keeps a line whose listing carries an active removal.**
    The fold clamps to stock rather than dropping the line, on the grounds
    that a removal may be lifted; checkout refuses the line with the
    `removed` reason.
13. **Ordering falls back to the id.** Every list orders by a timestamp with
    the id as tiebreak, because Laravel stores timestamps at second
    resolution and rows written inside one second would otherwise tie.
    `docs/alignment.md` §1 says ordering uses `created_at` and never the id.
14. **Concurrency is judged, not exercised.** Row locks (`for update`) are
    asserted by compiling the query against a grammar that supports them.
    SQLite serialises writers, so no test demonstrates two transactions
    interleaving.

## Suggested next steps

1. Point `MAIL_MAILER` at a real transport and turn on the `mail` channel,
   which closes gap 1 and removes the debug alert from the demo path.
2. Replace the placeholder SVG images with real uploads in the seeder so the
   storefront demo shows artwork, and seed one declined fulfillment so the
   refund path shows in the demo data.
3. Eager-load the active removal on the seller's listing index (gap 8).
