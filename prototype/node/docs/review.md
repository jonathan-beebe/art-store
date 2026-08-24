# Review against the brief

Every requirement in `__local__/prompts/initial-prompt.md`, its status, and the
route and test that prove it. Verified on FEAT-017 against a clean first run:
1,536 tests green, 99.57% line coverage.

Status values: **done** — built and covered by a test; **partial** — built with
a stated gap; **missing** — not built.

## Seller's portal

| Requirement | Status | Evidence (route + test file) |
| --- | --- | --- |
| Create an account, magic link, no passwords | done | `GET/POST /seller/login` (`app/sites/auth/sign-in-routes.ts`), `GET /auth/magic/:token` (`app/sites/auth/index.ts`) — `app/sites/auth/sign-in-routes.test.ts`, `app/sites/auth/index.test.ts` |
| Immediately begin managing store by adding items | done | `POST /seller/listings` — `app/sites/seller/routes/listings.test.ts` |
| Item appears on storefront once marked "for sale" | done | `POST /seller/listings/:id/status`, `GET /` — `app/sites/seller/routes/listings.test.ts`, `app/sites/shop/routes/home.test.ts`, `app/test/smoke.test.ts` |
| Manage listings (index, new, edit, show) | done | `GET /seller/listings`, `GET /seller/listings/new`, `GET /seller/listings/:id/edit`, `POST /seller/listings/:id` — `app/sites/seller/routes/listings.test.ts` |
| Activity per listing: views, favorites, added to cart | done | `GET /seller/listings/:id` — `app/sites/seller/routes/listings.test.ts`, `app/core/reports/activity-totals.test.ts`, `app/core/reports/activity-timeline.test.ts` |
| Reports on sales | done | `GET /seller/earnings` (sold-goods table) — `app/sites/seller/routes/earnings.test.ts` |
| Tools for fulfillment | done | `GET /seller/orders`, `GET /seller/orders/:id`, `POST /seller/orders/:id/ship` — `app/sites/seller/routes/orders.test.ts`, `app/actions/fulfillments/mark-shipped.test.ts` |
| Reports for accumulated earnings and payouts | done | `GET /seller/earnings` — `app/sites/seller/routes/earnings.test.ts`, `app/actions/escrow/seller-balance.test.ts` |
| Magic links printed to a debug alert; hook for real email | done | `app/delivery/flash-magic-link-delivery.ts` prints; `app/delivery/outbox-magic-link-delivery.ts` queues (`MAGIC_LINK_DELIVERY`) — `app/delivery/flash-magic-link-delivery.test.ts`, `app/delivery/outbox-magic-link-delivery.test.ts`, `app/actions/outbox/drain-outbox.test.ts` |
| Theme: simple, vanilla controls, system typography, semantic HTML, stock Tailwind | done | `app/sites/seller/views/layout.ejs`, `app/assets/app.css` — no dedicated test (visual); pages render through every route test above |

## Customer site

| Requirement | Status | Evidence (route + test file) |
| --- | --- | --- |
| Browse hand-made and custom art | done | `GET /`, `GET /art/:slug` — `app/sites/shop/routes/home.test.ts`, `app/sites/shop/routes/listings.test.ts`, `app/core/shop/listing-search.test.ts` |
| Favorite | done | `POST /art/:slug/favorite`, `GET /favorites` — `app/sites/shop/routes/favorites.test.ts`, `app/actions/favorites/toggle-favorite.test.ts` |
| Purchase | done | `POST /cart/:slug`, `POST /checkout`, `POST /orders/:id/pay` — `app/sites/shop/routes/carts.test.ts`, `app/sites/shop/routes/checkout.test.ts`, `app/sites/shop/routes/order-payments.test.ts` |
| Anonymous customer id assigned to every visitor | done | `resolveCustomerIdentity` (`app/plugins/identity.ts`) — `app/plugins/identity.test.ts` |
| Anonymous ids merged into the account on sign-in | done | `GET /auth/magic/:token` → `mergeAnonymousCustomer` — `app/actions/customers/merge-anonymous-customer.test.ts`, `app/core/customers/customer-merge-plan.test.ts` |
| Magic links, no passwords | done | `GET/POST /login` — `app/sites/auth/sign-in-routes.test.ts` |
| Fake card 4242 4242 4242 4242 mocks success | done | `POST /orders/:id/pay` — `app/sites/shop/routes/order-payments.test.ts`, `app/core/payments/fake-card.test.ts` |
| Fake card mocks a failed payment | done | `POST /orders/:id/pay` (`4000000000000002`, `4000000000009995`) — `app/sites/shop/routes/order-payments.test.ts`, `app/core/payments/fake-card.test.ts` |
| Guest checkout, verification required before finalizing | done | `POST /checkout` → `GET/POST /orders/:id/pay` behind `requireVerifiedCustomer` — `app/sites/shop/routes/checkout.test.ts`, `app/sites/shop/routes/order-payments.test.ts` |
| Mocked cart and fulfillment flow, end to end | done | the chain above plus `POST /seller/orders/:id/ship`, `POST /orders/:id/fulfillments/:fulfillmentId/delivered` — `app/test/smoke.test.ts`, `app/actions/orders/order-lifecycle.test.ts` |
| Theme: bright, open, easy to read, wares over brand | done | `app/sites/shop/views/layout.ejs` — no dedicated test (visual); pages render through every route test above |

## Admin site

| Requirement | Status | Evidence (route + test file) |
| --- | --- | --- |
| Admin sign-in | done | `GET/POST /admin/login` (`admits` restricts to seeded rows) — `app/sites/auth/sign-in-routes.test.ts`, `app/db/seed-admins.test.ts` |
| View all users, their listings, orders, fulfillments, earnings | done | `GET /admin/sellers`, `GET /admin/sellers/:id` — `app/sites/admin/routes/sellers.test.ts`, `app/sites/admin/queries/seller-detail.test.ts` |
| App-wide stats: listings, orders, fulfillments, full accounting | done | `GET /admin`, `GET /admin/accounting` — `app/sites/admin/routes/home.test.ts`, `app/sites/admin/routes/accounting.test.ts` |
| View of all customers, anonymous or known | done | `GET /admin/customers?standing=` — `app/sites/admin/routes/customers.test.ts`, `app/sites/admin/queries/customer-rows.test.ts` |
| Site-wide stats such as page views | done | `GET /admin/stats` — `app/sites/admin/routes/stats.test.ts`, `app/plugins/page-views.test.ts` |
| Review a seller's listings, remove from sale temporarily or permanently | done | `POST /admin/listings/:id/removals`, `POST /admin/listings/:id/removals/lift` — `app/sites/admin/routes/moderation.test.ts`, `app/core/moderation/listing-removal.test.ts` |
| Review a customer, optionally block | done | `POST /admin/customers/:id/blocks`, `POST /admin/customers/:id/blocks/lift` — `app/sites/admin/routes/moderation.test.ts`, `app/core/moderation/customer-standing.test.ts` |
| Pre-seeded admins: Jonathan Beebe, Anna Schmunk | done | `app/db/seed-admins.ts` — `app/db/seed-admins.test.ts` |

## Message feature

| Requirement | Status | Evidence (route + test file) |
| --- | --- | --- |
| Admins message sellers | done | `GET /seller/support`, `POST /admin/sellers/:id/messages` — `app/sites/seller/routes/messages.test.ts`, `app/sites/admin/routes/messages.test.ts` |
| Admins message customers | done | `GET /support`, `POST /admin/customers/:id/messages` — `app/sites/shop/routes/messages.test.ts`, `app/sites/admin/routes/messages.test.ts` |
| Sellers and customers message about orders | done | `POST /seller/orders/:id/messages`, `POST /orders/:id/fulfillments/:fulfillmentId/messages` — `app/sites/seller/routes/orders.test.ts`, `app/sites/shop/routes/fulfillments.test.ts` |
| Customer asks a question about an item | done | `POST /art/:slug/questions` — `app/sites/shop/routes/listings.test.ts`, `app/core/messaging/conversation-plan.test.ts` |
| Seller reviews, answers, and can publish as FAQ | done | `GET /seller/messages/:id`, `POST /seller/listings/:id/faqs` — `app/sites/seller/routes/messages.test.ts`, `app/sites/seller/routes/faqs.test.ts`, `app/actions/messaging/publish-listing-faq.test.ts` |
| Published FAQ shows on the item for future customers | done | `GET /art/:slug` — `app/sites/shop/routes/listings.test.ts`, `app/test/smoke.test.ts` |

## Fulfillment, escrow, payout

| Requirement | Status | Evidence (route + test file) |
| --- | --- | --- |
| Tell sellers an item has sold | done | `notify` on `finalizeOrder` approval, `GET /seller/notifications` — `app/actions/notifications/notify.test.ts`, `app/sites/seller/routes/notifications.test.ts` |
| Walk sellers through fulfillment | done | `GET /seller/orders/:id`, `POST /seller/orders/:id/ship` — `app/sites/seller/routes/orders.test.ts`, `app/actions/fulfillments/mark-shipped.test.ts` |
| Notify customers of shipment | done | `notify` on `markShipped`, `GET /account` — `app/actions/fulfillments/mark-shipped.test.ts`, `app/sites/shop/routes/notifications.test.ts` |
| Confirm shipment and delivery, escrow held then released | done | `POST /orders/:id/fulfillments/:fulfillmentId/delivered` → `confirmDelivered` — `app/actions/fulfillments/confirm-delivered.test.ts`, `app/core/escrow/ledger-balance.test.ts` |
| Sellers get a report of sold goods and funds due | done | `GET /seller/earnings` — `app/sites/seller/routes/earnings.test.ts` |
| Pay out at the end of every week | done | `npm run payouts` (`app/cli/run-payouts.ts`) → `runWeeklyPayout` — `app/actions/escrow/run-weekly-payout.test.ts`, `app/core/escrow/payout-period.test.ts`, `app/cli/parse-as-of.test.ts` |

## Tech stack

| Requirement | Status | Evidence |
| --- | --- | --- |
| TypeScript + Node | done | `src/package.json`, `src/tsconfig.json`, `node:24.19.0-bookworm-slim` (`Dockerfile`) |
| Fastify 5 | done | `src/package.json` — `fastify ^5.12.1` |
| EJS via @fastify/view | done | `src/package.json` — `@fastify/view ^12.0.0`, `ejs ^6.0.1`; `app/sites/*/views/**` |
| A SQLite driver behind Kysely | done, by a different driver | The brief named `better-sqlite3`; the project uses `node:sqlite`, the SQLite built into the Node 24 runtime, behind an owned Kysely dialect — `app/db/node-sqlite-dialect.ts`, `app/db/database.ts`, `app/db/node-sqlite-dialect.test.ts`. No compiled dependency, so the image carries no compiler toolchain. Backing out is one file (see the README's Database section) |
| Kysely + its migrator | done | `src/package.json` — `kysely ^0.29.5`; `app/db/migrator.ts`, `app/db/migrations/**` |
| zod | done | `src/package.json` — `zod ^4.4.3`; parsed at every route boundary (e.g. `app/sites/seller/listing-form.ts`) |
| Tailwind CLI | done | `src/package.json` — `@tailwindcss/cli ^4.3.3`; `app/assets/app.css` → `public/app.css` |
| `node:test` sidecars on Node 24 | done | `npm test` runs `node --test 'app/**/*.test.ts'` — 226 sidecar files |
| Not a React SPA; server-rendered, no client JS required | done | every flow is a form POST, and every page renders and works with JavaScript off. One `<script defer src="/app.js">` sits in the three site layouts (`app/sites/*/views/layout.ejs`); the other 63 of the 66 templates carry no tag. Those 21 dependency-free lines (`public/app.js`) subscribe to `GET <prefix>/events` and refresh the unread-message badge the layout already rendered — `app/plugins/events.ts`, `app/plugins/events.test.ts` |

## Development workflow

| Requirement | Status | Evidence |
| --- | --- | --- |
| Entire product dockerized, nothing installed on host | done | `docker-compose.yml`, `Dockerfile`, `Makefile` — every target wraps `docker compose` |
| All source lives in `src` | done | `prototype/node/src/` |
| Tests are sidecar files next to the code they test | done | 226 `*.test.ts` files beside their 275 source files |
| `/test*` and `/tdd*` skills used | partial | process, not visible in the artifacts; ticket `## Working` sections record a TDD flow (see FEAT-001 through FEAT-008, and the refinement batch) |
| `/work-*` skills for defining and managing work | done | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — 35 tickets: the ten build tickets and BUG-001, then 24 refinement tickets (BUG-002…006, FEAT-011…017, IMPRV-001…008, RFCTR-001…004) |
| `/write-*` skills for classes, functions, comments | partial | process; not independently verifiable from the tree |
| TDD flow | partial | process; each ticket's `## Working` notes assert it (e.g. FEAT-001 built `app/core/money.ts` test-first) |
| Measure and keep test coverage high | done | `npm run coverage` — `--test-coverage-lines=95 --test-coverage-branches=90`, enforced, and writes `coverage/lcov.info`; `npm run check` ends with it, and CI runs that same `npm run check` |
| Functional core / imperative shell | done | `app/core/**` is pure (no I/O, no clock, no random — `now: Date` and ids arrive as parameters); `app/actions/**` sequences core + adapters in one transaction; routes hold no domain `if` |
| `/diagramming` skill used to capture docs | done | `docs/README.md`, `docs/architecture.md`, `docs/identity.md`, `docs/orders.md`, `docs/escrow.md`, `docs/messaging.md`, `docs/admin.md`, `docs/data-model.md`, `docs/ontology.md`; `make docs-check` renders every Mermaid block through `minlag/mermaid-cli` and fails on one that does not parse |

## Goal

| Requirement | Status | Evidence |
| --- | --- | --- |
| Back-office for artists: account, listings, sales | done | `/seller/**` — `app/sites/seller/routes/**` |
| Customer site for browsing | done | `/` — `app/sites/shop/routes/home.ts` |
| Mocked cart and payment, fake card, success and failure | done | `app/core/payments/fake-card.ts` — 4242… approves; 4000…0002 and 4000…9995 decline; anything else is `invalid_card_number` |
| Magic links for artists and customers, printed to a debug alert | done | `flashMagicLinkDelivery` → `app/views/partials/debug-alert.ejs`, the default `MAGIC_LINK_DELIVERY=flash` outside production |
| A hook where email can be added later | done | Both ports have a working implementation over a transactional outbox — `outboxMagicLinkDelivery` and `outboxNotificationDelivery` (`app/delivery/`), draining to `.eml` files (`npm run outbox`, `POST /admin/outbox/drain`) and readable on `/admin/outbox`. A real transport is a third implementation and no call site changes |
| Guest checkout requiring verification before finalizing | done | `POST /checkout` → `GET/POST /orders/:id/pay` — `app/sites/shop/routes/checkout.test.ts` |
| Work orchestrated and delivered by Opus/Sonnet agents | done | `work/journal.md`, `work/3-done/` — 35 tickets, each with a `## Working` section recording what was verified |
| Delivered in `./prototype/node/` with a README and a docs folder | done | `prototype/node/README.md`, `prototype/node/docs/` |

## Verified on FEAT-017

Run against a worktree of `chore/refine-node` with every other ticket in the
refinement batch landed.

- **Clean first run.** `src/node_modules`, `src/public/app.css`, the SQLite
  files with their `-wal`/`-shm` siblings, and `src/storage/outbox/` removed,
  then one `docker compose up -d --build`. **29 seconds** from an empty tree to
  the compose healthcheck reporting `healthy`. Inside that: `npm ci` installed
  230 of the lockfile's 260 packages — the rest are platform-specific optional
  binaries npm skips — eleven migrations applied from nothing, `seed.ts` wrote 2 admins
  then 4 sellers, 29 listings, 5 customers, 3 orders, 98 page-view rows,
  4 conversations, 11 messages and 1 listing FAQ, and Tailwind built
  `public/app.css`. Nothing in the README or the entrypoint needed correcting.
- **`make test`** (`npm run check` in the container): `tsc --noEmit`, then
  eslint, then the coverage-gated suite — **1,536 tests, 1,536 pass, 0 fail**,
  exit 0. Coverage **99.57% lines, 97.22% branches, 99.47% functions** against
  the 95 / 90 gate. Green again from the host outside the container.
- **`make smoke`**: 8 tests, 0 fail, 1.6s. It walks everything
  `docs/architecture.md` → Testing lists, including `/health`, the outbox, and
  one frame off a live SSE stream.
- **`make docs-check`**: every Mermaid block under `docs/` rendered through
  `minlag/mermaid-cli` — 21 diagrams, 0 failed.
- **Curl walk** over the running server against the freshly seeded database,
  covering every GET route `npm run routes` prints: **75 checks, 0 failures, and
  zero 5xx responses in the server log.**
  - `/health` answers 200 `application/json` with
    `{"status":"ok","checks":{"database":"ok","migrations":"current"}}`.
  - Sign-in by magic link as a seller (`maya@example.com`), an admin
    (`jonathan-beebe@outlook.com`), and a customer who verified from checkout.
  - Every GET page on all three sites: 16 storefront URLs including search,
    the medium filter and page 2; 15 seller portal pages; 18 admin pages plus
    six filtered tables submitted with their empty-string "all" value
    (`?seller=`, `?status=`, `?standing=`, `?type=`, `?customer=`, `?removed=`),
    which used to 500. `/app.css` and `/app.js` both serve.
  - A live guest checkout: cart → checkout → order placed
    `pending_verification` → the emailed link verified the address and landed on
    the pay page.
  - `/nope`, `/seller/nope`, and `/admin/nope` each answer **404 with that
    site's own HTML page**.
  - `POST /login` with the `email` field submitted twice answers **400
    text/html**, the site's own error page.
  - `/events`, `/seller/events`, and `/admin/events` answer **200
    `text/event-stream`** and push `retry: 3000` then `event: unread`.
- **Outbox delivery.** With `MAGIC_LINK_DELIVERY=outbox`, an admin sign-in
  request printed nothing into the page, left a pending `outbox_messages` row,
  and appeared on `/admin/outbox`; `/admin/outbox/:id` showed the message with
  its link clickable, and following it signed the admin in. `npm run outbox`
  then wrote 17 pending messages to `storage/outbox/*.eml` and stamped each
  `delivered_at` — the sign-in one a well-formed RFC-5322 message with `From`,
  `To`, `Subject`, `Date`, `Message-ID`, and the URL in the body.
- **Production image.** `docker build --target runtime` produced a **289MB**
  image with 87 production packages. Run on port 4002 with `COOKIE_SECRET` and
  `MAGIC_LINK_DELIVERY=outbox` over a named volume, it migrated, answered
  `/health` 200 JSON and `/` 200 HTML, and drained cleanly on `docker stop`
  (`shutdown: draining` then `shutdown: complete`). It refuses to boot without
  `COOKIE_SECRET`:

  ```
  Error: COOKIE_SECRET is required when NODE_ENV=production: the identity
  cookies are signed with it, and a shared default makes an admin cookie
  forgeable.
  ```

  and refuses `MAGIC_LINK_DELIVERY=flash` under `NODE_ENV=production` for the
  same reason — it prints sign-in links into the page that asked.

## Known gaps

1. **Delivery stops at a file on disk: there is no SMTP.** Sign-in links and
   notifications are queued in `outbox_messages` inside the transaction that
   caused them (`outboxMagicLinkDelivery`, `outboxNotificationDelivery` in
   `app/delivery/`), and draining writes each one as an RFC-5322 `.eml` under
   `OUTBOX_DIR` (`npm run outbox`, `make outbox`, `POST /admin/outbox/drain`)
   rather than handing it to a mail server. `/admin/outbox` is the mailbox a
   reviewer reads. A real transport is a third implementation of the same two
   ports; no call site changes. A notification to a customer who has given no
   address is queued nowhere — that recipient has only the in-app inbox.

2. **The unread-badge event bus is in-process, so the app is one instance.**
   `app.events` (`app/plugins/events.ts`) is a `node:events` `EventEmitter`, and
   `<prefix>/events` streams are held open by the process that answered the
   subscription. A write handled by instance A therefore never wakes a stream
   held by instance B. Nothing else in the app has that constraint — the
   database is the only shared state and SQLite already serializes writers — so
   running two instances would leave the badges stale rather than break a flow.
   Fixing it means a bus outside the process (a table the streams poll, or a
   broker) behind the same `AppEvents` type.

3. **The storefront's 404 page renders signed-out for everyone.** A miss on the
   storefront reaches its page through `@fastify/static`'s hand-off, and that
   404 context snapshotted the root's hooks before `shopSite` attached its own,
   so neither the customer cookie nor the unread count is read there and the
   header says "Sign in" whoever is asking. The seller and admin 404 pages, which
   go through their sites' own `setNotFoundHandler`, are correct. Resolving it
   means an identity hook at the root, which costs a query on every asset
   request.

4. ~~**`listing.published` is the one business event with no log line.**~~
   Closed by IMPRV-009: `changeListingStatus` logs `listing.publish` when the
   target is `for_sale` and `listing.transition` otherwise, and every other
   write in the app now tells a `will` → `did` / `refused` / `failed` story from
   the action rather than the route shell. See
   [`architecture.md`](architecture.md#the-log).

5. **Seeded listings carry generated SVG placeholders, not photographs.**
   `listingImageSource` (`app/core/listings/placeholder-image.ts`) renders a
   procedural SVG from the title's hash when `listings.image_path` is null,
   which is true of every seeded listing (`app/db/seed-catalog.ts` calls
   `createListing` with no `imagePath`). An image uploaded through the seller
   portal (`app/sites/seller/listing-image-upload.ts`) is served for real from
   `public/uploads/`, with its extension taken from the file's own magic bytes.

6. **Shipment tracking is two text fields with no carrier integration.**
   `ShipmentDetails` (`app/core/orders/shipment-details.ts`) is `{ carrier,
   trackingNumber }`, free text entered on `POST /seller/orders/:id/ship`; the
   customer confirms delivery themselves from the order page
   (`POST /orders/:id/fulfillments/:fulfillmentId/delivered`) rather than a
   carrier webhook driving it.

7. **No admin assignment model for support threads.** The `admin_seller` and
   `admin_customer` counterpart on `/seller/support` and `/support` is the first
   admin by id (`app/actions/messaging/open-support-conversation.ts`); with no
   admin row at all the route flashes and goes back rather than opening a
   thread. Both seeded admins land in the same inbox.

8. **No attachments and no per-conversation archive in messaging.** `messages`
   rows (`app/db/migrations/20260823000008-create-messaging.ts`) carry only
   `body` text; there is no file column and no route that uploads one. A
   conversation has no closed/archived state — every thread an actor is a
   participant in stays in their inbox indefinitely.

9. **Migration `down()` bodies are uncovered.** `FileMigrationProvider`
   supplies both `up` and `down` per file, but nothing in the suite runs a
   `down` — `app/db/migrator.test.ts` and `app/db/database.test.ts` only apply
   forward. Coverage on the eleven migration files under `app/db/migrations/`
   reflects `up` only.

10. **The seller portal has no payout control by design, and the admin payout
    run pays every seller with a released balance in the period, not one at a
    time.** `POST /admin/payouts` calls `runWeeklyPayout` for the whole
    platform (`app/actions/escrow/run-weekly-payout.ts`); there is no
    per-seller payout route. This matches the brief ("paying out at the end of
    every week" is a platform action) but means there is no way to demo a
    single seller's payout in isolation from `/admin`.

11. ~~**A `conversation_open` trip on the listing-question box answers the
    generic 429 page, not a re-rendered listing page.**~~ Closed by the
    IMPRV-012 fix-up: `guardConversationOpen` (`app/sites/shop/routes/
    messages.ts`) split into three. The original, still carrying no `onTrip`,
    stays on `GET /support`'s two uses — a link click has nowhere of its own
    to re-render, so a trip there keeps the shared `error` page by design.
    `guardConversationOpenForQuestion` reuses the same listing-page re-render
    `guardQuestionMessagePost` already gave `POST /art/:slug/questions`, so
    either of that route's two guards tripping lands the visitor back on the
    listing page with the question kept. `guardConversationOpenForFulfillmentMessage`
    re-renders the order page for `POST /orders/:id/fulfillments/:fulfillmentId/messages`
    — a second route with the same bug the original gap write-up did not
    name — through `renderOrderPage` (`app/sites/shop/order-page.ts`),
    extracted from `GET /orders/:id` so both share it; the order page gained
    a `form-error.ejs` slot scoped to the fulfillment section that tripped,
    since one order can hold a "Message the seller" form per seller.

12. **The admin's `POST /sellers/:id/messages` and `POST /customers/:id/messages`
    carry no rate limit.** Both open a conversation with no message body, the
    same shape as the fulfillment-thread open `conversation_open` guards
    elsewhere, but `docs/alignment.md` §3's guard list names only "listing
    question, support, fulfillment thread opens" — these two admin routes are
    not among them, and an admin is already authenticated. Left unguarded
    rather than stretching the contract's list past what it says (FEAT-020).

13. **The admin site's five write forms still flash a single sentence instead
    of a field-level error.** IMPRV-012 moved every seller and storefront form
    onto the shared `form-field.ejs`/`form-error.ejs` partials — the listing
    form, the FAQ forms, the ship and decline forms, checkout, the cart and
    ask-a-question forms, and all three sites' message replies — but cut its
    own explicit fallback line at the admin site: `views/customer.ejs` (block
    reason), `views/listing.ejs` (removal kind and reason), `views/order.ejs`
    (cancel reason, inline refund amount), `views/fulfillment.ejs` (refund
    reason), and `views/payouts.ejs` (threshold) still call `reply.setFlash({
    alert })` and redirect. `parseRefundReason` was still brought onto the
    shared `Partial<Record<Field, string>>` shape its seller-side call site
    needed, so `admin/routes/orders.ts` and `admin/routes/fulfillments.ts`
    read `Object.values(reason.errors)[0]` for that flash rather than
    `reason.error` — the two admin call sites were left otherwise unconverted.

14. **Checkout's implicit magic-link guard answers the generic 429 page even
    though the order it is protecting is already placed.** `verifyGuestAddress`
    (`app/sites/shop/routes/checkout.ts`) calls `answerIfRateLimited` for
    `magic_link_request` after `checkOutCart` has already written the order —
    and, for an already-verified customer, charged it — inside the same
    request; a trip there only blocks the verification link the guest still
    needs, never the order. `docs/alignment.md` §3 fixes the trip response as
    HTTP 429 with no side effect performed for every guard alike, so
    redirecting to the order page instead (the way `input.charged !== null`
    already does above it) would drop that status for this one guard —
    a change to what §3 promises across all three prototypes, not a
    same-shape `onTrip` re-render like IMPRV-012's other callbacks. Left
    answering the generic page rather than special-cased against the
    contract.

Closed by the refinement batch, and listed here so a reader of an older copy of
this file is not misled: email had no implementation at all (FEAT-015 added the
outbox), an unmatched route answered Fastify's JSON (IMPRV-001), the admin
filter pages 500'd on their own "all" value (IMPRV-002), there was no health
endpoint or draining shutdown (FEAT-011), no production image (FEAT-013), and
no CI (FEAT-014).

## Suggested next steps

Numbered against the open gaps above; gaps 4 and 11 are closed and need no
step.

1. Add an SMTP implementation of `MagicLinkDelivery` and `NotificationDelivery`
   beside the outbox ones, selected the same way `MAGIC_LINK_DELIVERY` already
   is, and drain to it instead of to files. Closes gap 1.
2. Put the event bus behind the database — a table the open streams poll, or a
   broker — so a second instance's writes reach a subscriber on the first.
   Closes gap 2.
3. Resolve the customer cookie in a root hook that skips static paths, so the
   storefront's 404 page renders in the visitor's own session. Closes gap 3.
4. Attach real images in `app/db/seed-catalog.ts` so the demo storefront shows
   artwork instead of generated shapes. Closes gap 5.
5. If a real carrier integration is in scope for a later prototype, replace
   the two free-text shipment fields with a tracking lookup; for this
   prototype's purposes the customer-confirms-delivery model is an accepted
   simplification. Closes gap 6.
6. Add an assignment column to `conversations` (or a round-robin over
   `admins`) so support threads are not all routed to the same admin. Closes
   gap 7.
7. Add a `message_attachments` table and an `archived_at` column on
   `conversations` if the product review calls for either. Closes gap 8.
8. Add a migration-cycle test that runs every `down()` after its `up()` on a
   scratch database. Closes gap 9.
9. Document the platform-wide payout run as intentional in the seller-facing
   copy, or add a per-seller filter to `/admin/payouts` if reviewers want to
   demo one seller's payout in isolation. Closes gap 10.
10. Decide at the contract level whether `docs/alignment.md` §3's guard list
    should name the admin's two message-open routes; add a `conversation_open`
    guard to `POST /admin/sellers/:id/messages` and
    `POST /admin/customers/:id/messages` if so. Closes gap 12.
11. Move the admin site's five write forms onto `form-field.ejs`/
    `form-error.ejs`, re-rendering in place instead of flashing and
    redirecting, the same conversion IMPRV-012 gave every seller and
    storefront form. Closes gap 13.
12. Decide at the contract level whether a trip on checkout's implicit guest
    magic-link guard should redirect to the order page instead of answering
    the generic 429 — a change to `docs/alignment.md` §3's every-trip-is-429
    promise, not a same-shape `onTrip` re-render — and implement to match.
    Closes gap 14.

## Stack notes

The Node stack resolves 260 packages in `package-lock.json` behind 10 direct
runtime dependencies and 7 dev dependencies; 87 of them reach the production
image, which `npm ci --omit=dev` installs. The Rails spike's `Gemfile.lock`
resolves 113 gems. There is no build step: Node 24 strips TypeScript types
natively, so `node app/server.ts` runs the source directly — no bundler, no
`tsx`, no `dist/`. `tsc --noEmit` exists only to type-check.
`erasableSyntaxOnly` is the cost of that native stripping: no `enum`, no
parameter properties, no namespaces, so the codebase uses `as const` string
unions and lookup tables instead, and every import specifier carries the
`.ts` extension it has on disk. The test runner is `node:test` +
`node:assert/strict`, built into the Node binary, with
`--experimental-test-coverage` covering both line and branch thresholds — no
Jest, no Vitest, no coverage tool as a dependency. The codebase is 275 source
`.ts` files (15,362 lines) against 226 sidecar test files (24,590 lines) —
more test code than production code — plus 66 EJS templates (2,942 lines).

Set against that low dependency count, everything a framework like Rails
gives for free is explicit wiring here: sessions, flash, per-site view
layouts, identity resolution, route guards, error pages, and the validator
compiler all live in `app/plugins/`, `app/http/`, and each
`app/sites/*/index.ts`. Fastify's plugin encapsulation is what makes
three sites with three independent identity cookies (seller, customer, admin)
share one deployable without their guards leaking into each other. The trade
is fewer runtime dependencies and no code generation, paid for in more
hand-written infrastructure code than a batteries-included framework needs —
visible directly in the sidecar-to-source ratio above.
