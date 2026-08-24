# Review against the brief

Every requirement in `__local__/prompt.md`, its status, and the route and test
class that prove it.

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Engineering quality

| Measure | Where it is set | Result |
| --- | --- | --- |
| Formatting | `src/pint.json` — `laravel` preset plus `strict_comparison`, `strict_param`, `void_return` | `make lint` clean over 448 files |
| Static analysis | `src/phpstan.neon` — PHPStan/Larastan, `level: max` over `app`, `database`, `routes`, `tests` | 0 errors, no `excludePaths`, no `ignoreErrors`, no baseline |
| Strict types | Pint's `declare_strict_types`, re-asserted by `tests/Arch.php` | every PHP file |
| Tests | Pest, sidecars beside the file they cover | 1107 tests, 2491 assertions |
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

## Admin site and messaging

| Requirement | Status | Route | Test |
| --- | --- | --- | --- |
| Admin actor, seeded not signed up, own guard | done | `auth.admin.login`, `auth.admin.send`, `auth.magic.verify` | `Auth\AdminLoginControllerTest`, `Requests\Auth\SendAdminMagicLinkRequestTest`, `Actions\Auth\SignInAdminTest` |
| Admin dashboard, sellers and customers lists/detail | done | `admin.dashboard`, `admin.sellers.index`/`.show`, `admin.customers.index`/`.show` | `Admin\DashboardControllerTest`, `Admin\SellerControllerTest`, `Admin\CustomerControllerTest` |
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
2. **The payout button pays every seller**, not the signed-in one. It is
   labelled a debug control on `seller.earnings` and the flash says so, and
   `Seller\PayoutControllerTest` pins the behavior ("pays out every seller with
   released escrow, not only the signed-in seller") so a change to it fails a
   test rather than passing unnoticed.
3. **Shipment tracking is a text field.** No carrier integration; the customer
   confirms delivery from the order page.
4. **The cart's Checkout button stays live on an unavailable line.** The
   shopper is refused at the write, with the item named, rather than at the
   button.
5. **Replacing a listing image that fails to store keeps the old one
   silently.** The create path flashes the failure; the update path does not.
6. **Seeded listings carry a generated placeholder SVG**, not artwork.
7. **A closed messaging tab does not free its SSE worker at once.** The
   generator yields only on a change, so `connection_aborted()` is checked
   less often than a keepalive would allow; measured, an abandoned stream
   holds its worker for about five seconds. See `docs/messaging.md` § "The
   live badge".
8. **A cookieless client of `/events` mints a `customers` row per request**,
   the same as any other storefront route with no `customer_id` cookie — a
   crawler that ignores cookies holds one worker per reconnect. Bounded, not a
   new hole. See `docs/messaging.md` § "The live badge".
9. **A blocked customer's ask leaves an empty thread.** `OpenConversation`
   runs before the `post` policy check on `shop.listing.questions`, so a
   blocked visitor's submission opens (or finds) the thread and only the
   message is refused. See `docs/messaging.md` § "What a block does".
10. **The reply forms' `maxlength` attributes are literals**, not reads of
    `MessageBody::MAX_LENGTH` / `FaqDraft::*_MAX_LENGTH`. The form request
    still enforces the real limit either way. See `docs/messaging.md` §
    "A question becomes a published FAQ".

## Suggested next steps

1. Point `MAIL_MAILER` at a real transport and turn on the `mail` channel,
   which closes gap 1 and removes the debug alert from the demo path.
2. Scope the payout button to the signed-in seller, or move it behind an
   artisan-only path and keep `payouts:run` as the single entry point.
3. Replace the placeholder SVG images with real uploads in the seeder so the
   storefront demo shows artwork.
