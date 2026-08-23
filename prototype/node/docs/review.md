# Review against the brief

Every requirement in `__local__/prompts/initial-prompt.md`, its status, and the
route and test that prove it. Verified on FEAT-010 against a clean first run:
1,161 tests green, 99.42% line coverage.

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
| Magic links printed to a debug alert; hook for real email | done | `app/delivery/flash-magic-link-delivery.ts`, `app/delivery/mail-magic-link-delivery.ts` — `app/delivery/flash-magic-link-delivery.test.ts`, `app/delivery/mail-magic-link-delivery.test.ts` |
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
| TypeScript + Node | done | `src/package.json`, `src/tsconfig.json`, `node:24-bookworm-slim` (`Dockerfile`) |
| Fastify 5 | done | `src/package.json` — `fastify ^5.12.1` |
| EJS via @fastify/view | done | `src/package.json` — `@fastify/view ^12.0.0`, `ejs ^6.0.1`; `app/sites/*/views/**` |
| better-sqlite3 | done | `src/package.json` — `better-sqlite3 ^13.0.3`; `app/db/database.ts` |
| Kysely + its migrator | done | `src/package.json` — `kysely ^0.29.5`; `app/db/migrator.ts`, `app/db/migrations/**` |
| zod | done | `src/package.json` — `zod ^4.4.3`; parsed at every route boundary (e.g. `app/sites/seller/listing-form.ts`) |
| Tailwind CLI | done | `src/package.json` — `@tailwindcss/cli ^4.3.3`; `app/assets/app.css` → `public/app.css` |
| `node:test` sidecars on Node 24 | done | `npm test` runs `node --test 'app/**/*.test.ts'` — 190 sidecar files |
| Not a React SPA; server-rendered, no client JS required | done | zero `<script>` tags across the 57 templates under `app/**/*.ejs`; every flow is a form POST |

## Development workflow

| Requirement | Status | Evidence |
| --- | --- | --- |
| Entire product dockerized, nothing installed on host | done | `docker-compose.yml`, `Dockerfile`, `Makefile` — every target wraps `docker compose` |
| All source lives in `src` | done | `prototype/node/src/` |
| Tests are sidecar files next to the code they test | done | 190 `*.test.ts` files beside their 248 source files |
| `/test*` and `/tdd*` skills used | partial | process, not visible in the artifacts; ticket `## Working` sections record a TDD flow (see FEAT-001 through FEAT-008) |
| `/work-*` skills for defining and managing work | done | `work/1-inbox`, `work/2-doing`, `work/3-done`, `work/journal.md` — ten feature tickets and one bug |
| `/write-*` skills for classes, functions, comments | partial | process; not independently verifiable from the tree |
| TDD flow | partial | process; each ticket's `## Working` notes assert it (e.g. FEAT-001 built `app/core/money.ts` test-first) |
| Measure and keep test coverage high | done | `npm run coverage` — `--test-coverage-lines=90 --test-coverage-branches=80`, enforced |
| Functional core / imperative shell | done | `app/core/**` is pure (no I/O, no clock, no random — `now: Date` and ids arrive as parameters); `app/actions/**` sequences core + adapters in one transaction; routes hold no domain `if` |
| `/diagramming` skill used to capture docs | done | `docs/README.md`, `docs/architecture.md`, `docs/identity.md`, `docs/orders.md`, `docs/escrow.md`, `docs/messaging.md`, `docs/admin.md`, `docs/data-model.md`, `docs/ontology.md` |

## Goal

| Requirement | Status | Evidence |
| --- | --- | --- |
| Back-office for artists: account, listings, sales | done | `/seller/**` — `app/sites/seller/routes/**` |
| Customer site for browsing | done | `/` — `app/sites/shop/routes/home.ts` |
| Mocked cart and payment, fake card, success and failure | done | `app/core/payments/fake-card.ts` — 4242… approves; 4000…0002 and 4000…9995 decline; anything else is `invalid_card_number` |
| Magic links for artists and customers, printed to a debug alert | done | `flashMagicLinkDelivery` → `app/views/partials/debug-alert.ejs` |
| A hook where email can be added later | done | Both ports have a working implementation over a transactional outbox — `outboxMagicLinkDelivery` and `outboxNotificationDelivery` (`app/delivery/`), draining to `.eml` files (`npm run outbox`, `POST /admin/outbox/drain`) and readable on `/admin/outbox`. A real transport is a third implementation and no call site changes |
| Guest checkout requiring verification before finalizing | done | `POST /checkout` → `GET/POST /orders/:id/pay` — `app/sites/shop/routes/checkout.test.ts` |
| Work orchestrated and delivered by Opus/Sonnet agents | done | `work/journal.md`, `work/3-done/FEAT-001` … `FEAT-010` plus `BUG-001` |
| Delivered in `./prototype/node/` with a README and a docs folder | done | `prototype/node/README.md`, `prototype/node/docs/` |

## Verified on FEAT-010

- **Clean first run.** `make down`, then `src/node_modules`, both SQLite files
  and their `-wal`/`-shm` siblings, `src/public/app.css`, and everything under
  `src/public/uploads/` removed, then `make build` and `make up` alone.
  `make build` took **14s** on a cold image cache; `make up` took **13s** from
  returning to `/` answering 200. Inside that: `npm ci` installed 230 packages
  in 9s, ten migrations applied from nothing, `seed.ts` wrote 2 admins then
  4 sellers, 29 listings, 5 customers, 3 orders, 98 page-view rows,
  4 conversations, 11 messages and 1 listing FAQ, and Tailwind built a
  25,574-byte `public/app.css`. Nothing in the README or the entrypoint needed
  correcting.
- **`make test`**: 1,161 tests, 1,161 pass, 0 fail — `tsc --noEmit`, then
  eslint (`complexity` 8, `max-depth` 3), then `node --test`.
- **`make coverage`**: **99.42% lines, 95.23% branches, 98.85% functions**,
  exit 0 against the 90 / 80 gate.
- **`make smoke`**: 6 tests, 0 fail, 0.78s. It walks everything
  `docs/architecture.md` → Testing lists.
- **Curl walk** over the running server on <http://localhost:4000> against the
  freshly seeded database: **116 checks, 0 failures, no 500 anywhere.**
  - Sign-in through the debug alert's magic link as a seller
    (`maya@example.com`), an admin (`jonathan-beebe@outlook.com`), and a
    customer (`casey@example.com`); each site's `/account` names the address.
  - Every GET page on all three sites answers 200 with `/app.css` linked: 9
    storefront pages including search, the medium filter and page 2; 12 seller
    portal pages; 23 admin pages including every filtered table. `/app.css`
    serves 25,574 bytes of `text/css`.
  - Cross-actor ids answer 404 on reads and writes: another seller's listing
    and its status POST, a non-numeric id, another customer's order, a thread
    the actor is not in, and ids naming nothing.
  - A listing created through the portal with a real multipart PNG stored the
    file and served it back from `/uploads/<uuid>.png` as `image/png`.
  - A live guest checkout on that piece: view → favorite → cart ($480.00) →
    a checkout page with no card field → order placed → the emailed link
    verified the address and landed on the pay page → `4000 0000 0000 0002`
    left the order `Payment failed` with the decline notice → `4242 4242 4242
    4242` left it `Paid`.
  - The seller was told the item sold, shipped it with a carrier and tracking
    number, the customer confirmed delivery, and the admin's payout run moved
    the seller's `$432.00` from available to paid out.
  - An ask on the listing became a thread, the seller replied, published the
    answer, and it renders on `/art/:slug` for the next visitor.
  - An admin removal took the piece to 404 and off the grid; the lift brought
    it back to 200.
  - The seeded blocked customer is refused at add-to-cart and at `/checkout`,
    and the listing page says why.

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

2. **Seeded listings carry generated SVG placeholders, not photographs.**
   `listingImageSource` (`app/core/listings/placeholder-image.ts`) renders a
   procedural SVG from the title's hash when `listings.image_path` is null,
   which is true of every seeded listing (`app/db/seed-catalog.ts` calls
   `createListing` with no `imagePath`). An image uploaded through the seller
   portal (`app/sites/seller/listing-image-upload.ts`) is served for real from
   `public/uploads/`.

3. **Shipment tracking is two text fields with no carrier integration.**
   `ShipmentDetails` (`app/core/orders/shipment-details.ts`) is `{ carrier,
   trackingNumber }`, free text entered on `POST /seller/orders/:id/ship`; the
   customer confirms delivery themselves from the order page
   (`POST /orders/:id/fulfillments/:fulfillmentId/delivered`) rather than a
   carrier webhook driving it.

4. **No admin assignment model for support threads.** The `admin_seller` and
   `admin_customer` counterpart on `/seller/support` and `/support` is
   hardcoded to the first admin by id (`app/actions/messaging/open-conversation.ts`
   via `planConversation`); with no admin row at all the route flashes and goes
   back rather than opening a thread. Both seeded admins land in the same
   inbox.

5. **No attachments and no per-conversation archive in messaging.** `messages`
   rows (`app/db/migrations/20260823000008-create-messaging.ts`) carry only
   `body` text; there is no file column and no route that uploads one. A
   conversation has no closed/archived state — every thread an actor is a
   participant in stays in their inbox indefinitely.

6. **Migration `down()` bodies are uncovered.** `FileMigrationProvider`
   supplies both `up` and `down` per file, but nothing in the suite runs a
   `down` — `app/db/migrator.test.ts` and `app/db/database.test.ts` only apply
   forward. Coverage on the nine migration files under
   `app/db/migrations/` reflects `up` only.

7. **The seller portal has no payout control by design, and the admin payout
   run pays every seller with a released balance in the period, not one at a
   time.** `POST /admin/payouts` calls `runWeeklyPayout` for the whole
   platform (`app/actions/escrow/run-weekly-payout.ts`); there is no
   per-seller payout route. This matches the brief ("paying out at the end of
   every week" is a platform action) but means there is no way to demo a
   single seller's payout in isolation from `/admin`.

## Suggested next steps

1. Add an SMTP implementation of `MagicLinkDelivery` and `NotificationDelivery`
   beside the outbox ones, selected the same way `MAGIC_LINK_DELIVERY` already
   is, and drain to it instead of to files. Closes gap 1.
2. Attach real images in `app/db/seed-catalog.ts` so the demo storefront shows
   artwork instead of generated shapes. Closes gap 2.
3. If a real carrier integration is in scope for a later prototype, replace
   the two free-text shipment fields with a tracking lookup; for this
   prototype's purposes the customer-confirms-delivery model is an accepted
   simplification. Closes gap 3.
4. Add an assignment column to `conversations` (or a round-robin over
   `admins`) so support threads are not all routed to the same admin. Closes
   gap 4.
5. Add a `message_attachments` table and an `archived_at` column on
   `conversations` if the product review calls for either. Closes gap 5.
6. Add a migration-cycle test that runs every `down()` after its `up()` on a
   scratch database. Closes gap 6.
7. Document the platform-wide payout run as intentional in the seller-facing
   copy, or add a per-seller filter to `/admin/payouts` if reviewers want to
   demo one seller's payout in isolation. Closes gap 7.

## Stack notes

The Node stack resolves 263 packages in `package-lock.json` behind 10 direct
runtime dependencies and 8 dev dependencies. The Rails spike's `Gemfile.lock`
resolves 113 gems. There is no build step: Node 24 strips TypeScript types
natively, so `node app/server.ts` runs the source directly — no bundler, no
`tsx`, no `dist/`. `tsc --noEmit` exists only to type-check.
`erasableSyntaxOnly` is the cost of that native stripping: no `enum`, no
parameter properties, no namespaces, so the codebase uses `as const` string
unions and lookup tables instead, and every import specifier carries the
`.ts` extension it has on disk. The test runner is `node:test` +
`node:assert/strict`, built into the Node binary, with
`--experimental-test-coverage` covering both line and branch thresholds — no
Jest, no Vitest, no coverage tool as a dependency. The codebase is 248 source
`.ts` files (12,710 lines) against 190 sidecar test files (18,866 lines) —
more test code than production code — plus 57 EJS templates (2,821 lines).

Set against that low dependency count, everything a framework like Rails
gives for free is explicit wiring here: sessions, flash, per-site view
layouts, identity resolution, and route guards all live in `app/plugins/` and
each `app/sites/*/index.ts`. Fastify's plugin encapsulation is what makes
three sites with three independent identity cookies (seller, customer, admin)
share one deployable without their guards leaking into each other. The trade
is fewer runtime dependencies and no code generation, paid for in more
hand-written infrastructure code than a batteries-included framework needs —
visible directly in the sidecar-to-source ratio above.
