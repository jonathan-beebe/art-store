# Art Store prototype (Node) — system architecture

Prototype of a three-sided art marketplace: a **seller portal** (back office), a
**customer storefront**, and an **admin site** for the platform operators, plus a
**messaging center** that spans all three. One Node deployable, one SQLite
file, server-rendered HTML, no client-side JavaScript required. Every agent
working in `prototype/node/` reads this doc first and follows the conventions
in it. The Rails spike in `prototype/rails/` (and the PHP spike in
`prototype/php/`) solved the seller/customer half of the same product; its
domain decisions carry over unless stated otherwise here.

This doc is the system-altitude map. The feature docs beside it carry the
sequences and state machines: [`identity.md`](identity.md),
[`orders.md`](orders.md), [`escrow.md`](escrow.md),
[`messaging.md`](messaging.md), [`admin.md`](admin.md),
[`data-model.md`](data-model.md), [`ontology.md`](ontology.md).

## Stack (installed versions, 2026-08-23)

| Concern | Choice | Version |
| --- | --- | --- |
| Runtime | Node, native type stripping (`node app/server.ts`, no build step, no `tsx`) | `node:24.19.0-bookworm-slim` |
| Language | TypeScript, `erasableSyntaxOnly` (no `enum`, no parameter properties, no namespaces), `verbatimModuleSyntax`, `tsc --noEmit` for type checking only | 5.9.3 |
| HTTP | Fastify | 5.12.1 |
| Views | EJS via `@fastify/view`; raw `<%- %>` output is reserved for `include(...)` and a layout's `<%- body %>` — every other value renders through the escaping `<%= %>` | ejs 6.0.1, @fastify/view 12.0.0 |
| Forms, cookies, static, uploads | `@fastify/formbody`, `@fastify/cookie` (signed cookies), `@fastify/static`, `@fastify/multipart` | 9.0.0, 11.1.2, 10.1.3, 10.1.1 |
| Database | `node:sqlite` behind `app/db/node-sqlite-dialect.ts`, an owned Kysely dialect (`CamelCasePlugin`) + Kysely `Migrator` with `FileMigrationProvider`, both imported from `kysely/migration` | built in, kysely 0.29.5 |
| Validation at the edge | zod schemas declared on the route, run by one validator compiler (`app/http/zod-type-provider.ts`) | 4.4.3 |
| Logging | Fastify's own logger, configured in `app/logging.ts`; the CLIs build one with `createCliLogger` | pino 10.3.1 |
| CSS | Tailwind CLI, stock theme | @tailwindcss/cli 4.3.3 |
| Tests | `node:test` + `node:assert/strict`, sidecar files, `--experimental-test-coverage` with line/branch thresholds | built in |
| Complexity | eslint + typescript-eslint on `recommendedTypeChecked`, with `complexity` and `max-depth` rules as a gate | eslint 9.39.5, typescript-eslint 8.67.0 |

Ten runtime dependencies and seven dev dependencies resolve 260 packages.
There is no compiled dependency: SQLite comes from the Node runtime, so the
image carries no compiler toolchain.

Kysely 0.29 moved `Migrator` and `FileMigrationProvider` out of its root
export; importing them from `kysely` fails at runtime.

Everything runs in the `app` container (`docker compose`). Nothing is installed
on the host; `node_modules/` lives inside the bind mount so it survives
restarts. The container serves port 4000. `Dockerfile` carries three targets:
`dev` (the bind-mount workflow `make up` builds), `build` (installs everything
and compiles the stylesheet once), and `runtime` (production dependencies only,
`USER node`, no bind mount) — `make image` and `make run-image`.

Every Make target wraps `docker compose`: `up`, `down`, `build`, `assets`,
`shell`, `test` (`npm run coverage`), `lint`, `lint-fix`, `check`, `smoke`,
`coverage`, `routes`, `migrate`, `fresh`, `seed`, `payouts`, `outbox`,
`sweep`, `logs`, plus `docs-check` (Mermaid through `minlag/mermaid-cli`),
`image`, and `run-image`. The README's Commands table gives the compose
command each one stands for.

## Deployables

Question: what runs, and what talks to what?

```mermaid
flowchart LR
    subgraph docker["docker compose: app container"]
        node["Node 24 + Fastify 5<br/>/ storefront<br/>/seller portal<br/>/admin site<br/>/auth/magic/:token<br/>/health, &lt;prefix&gt;/events"]
        sqlite[("SQLite (WAL)<br/>src/storage/development.sqlite3<br/>incl. outbox_messages")]
        node -- "Kysely" --> sqlite
    end
    seller["Seller (browser)"] -- "HTML forms, SSE" --> node
    customer["Customer (browser)"] -- "HTML forms, SSE" --> node
    admin["Admin (browser)"] -- "HTML forms, SSE" --> node
    payouts["npm run payouts<br/>(app/cli/run-payouts.ts)"] -- "Kysely" --> sqlite
    outbox["npm run outbox<br/>(app/cli/drain-outbox.ts)"] -- "Kysely" --> sqlite
    outbox -- "renderMailMessage" --> eml["&lt;OUTBOX_DIR&gt;/&lt;id&gt;.eml"]
```

Each CLI is a second entry point onto the same database, not a second service:
it opens its own connection, runs one action, and exits. Both `MagicLinkDelivery`
and `NotificationDelivery` are ports with a live implementation over the outbox
(`app/delivery/outbox-*.ts`); a real SMTP transport would be a third
implementation of each, and no call site changes.

## Layers inside the deployable

Functional core / imperative shell. Dependencies point inward only.

```mermaid
flowchart TD
    entry["Entry: app/server.ts, app/app.ts, app/config.ts, app/logging.ts, app/cli/"] --> coord
    coord["Coordination: app/sites/**/routes, app/actions/**, app/plugins/, app/http/"] --> core
    coord --> adapters
    adapters["Adapters: app/db (Kysely), app/delivery, EJS views, app/sites/*/queries"] --> core
    core["Core: app/core/** — pure TypeScript, no I/O, no clock, no random"]
```

| Layer | Lives in | Rules |
| --- | --- | --- |
| Core | `app/core/<concept>/` | Pure functions and types. Receives `now: Date` and ids as parameters — never a `Clock`. Enumerations are `as const` string unions; state machines are a `TRANSITIONS` table plus `canTransition<Thing>(from, to)` and a throwing `transition<Thing>`. Concepts: `analytics`, `auth`, `cart`, `customers`, `escrow`, `health`, `listings`, `messaging`, `moderation`, `notifications`, `orders`, `payments`, `reports`, `shop`, plus `money.ts`, `status-label.ts`, `transition-error.ts`. Unit tested with `node:test` and no database. |
| Adapters | `app/db/`, `app/delivery/`, `app/sites/*/views/`, `app/views/`, `app/sites/*/queries/` | `app/db/`: the Kysely factory (`openDatabase`), `node-sqlite-dialect.ts` (the dialect over `node:sqlite`), `migrations/`, `migrator.ts`, `schema.ts` + `commerce-schema.ts` (row types), `count.ts`, `timestamp.ts`, the `seed-*.ts` modules. `app/delivery/`: the `MagicLinkDelivery` and `NotificationDelivery` ports, the `DeliveryContext` both `deliver` calls take, and their implementations — `flash-magic-link-delivery.ts`, `outbox-magic-link-delivery.ts`, `outbox-notification-delivery.ts`, plus `outbox-message.ts`, which enqueues and renders a row. `queries/`: read-only Kysely per site, one module per table a page shows, no domain logic. Views are EJS. |
| Coordination | `app/actions/<concept>/`, `app/sites/<site>/`, `app/plugins/`, `app/http/` | Actions are verbs (`placeOrder`, `runWeeklyPayout`, `drainOutbox`) that take an `ActionContext` (`{ db, clock, notificationDelivery? }`) and sequence core + adapters inside one transaction. Every route declares its `params`/`querystring`/`body` as zod schemas, which one validator compiler set in `buildApp` runs, so a handler reads already-typed `request.params`/`query`/`body`, calls actions, and renders views. `app/http/` holds that compiler (`zod-type-provider.ts`) and the schema pieces routes are built from (`request-schema.ts`: `idParams`, `slugParams`, `optionalFilter`, `submittedForm`). `app/plugins/` holds the cross-cutting Fastify wiring — see the table below. None of them owns a domain `if`; if one appears, it moves to `app/core`. Covered by integration tests (`app.inject`). |
| Entry | `app/app.ts` (`buildApp(deps)`), `app/server.ts` (listen, signal handling), `app/config.ts` (env → typed config), `app/logging.ts` (logger options and serializers), `app/cli/` | Wiring only. `buildApp` is the composition root and the thing tests construct. |

`app/clock.ts` sits at the root of `app/` rather than in a layer: the `Clock`
type, `systemClock`, and `fixedClock`. Core takes a `Date`, so `Clock` has no
reason to live in `app/core/`.

### The path a request takes

Every cross-cutting feature is a Fastify plugin, and `buildApp` registers all
of them before the first site, because a site inherits the root's hooks as they
stand when its own context is built. `npm run routes` prints this tree from
Fastify's own introspection.

The order below is Fastify's lifecycle. Validation runs **before**
`preHandler`, which is why a signed-out visitor asking for a guarded URL with
an unparseable id gets 404 rather than a redirect to sign in.

```mermaid
flowchart TD
    request(["Request"]) --> route{"a route matches?"}
    route -- no --> notfound["setNotFoundHandler, one per site<br/>404 in that site's layout"]
    route -- yes --> parse["Body parsing<br/>@fastify/formbody, or @fastify/multipart in the portal"]
    parse --> schema{"Route schema<br/>zod params / querystring / body"}
    schema -- "refused params" --> notfound
    schema -- "refused body or query" --> errors["errorPages setErrorHandler<br/>400 in that site's layout"]
    schema -- ok --> pre["preHandler, in registration order"]

    subgraph preHandlers["preHandler"]
        direction TB
        identity["identityCookies: currentSeller, currentAdmin"] --> unread["countUnreadMessages(actorType)"]
        unread --> guard["the site's guard<br/>requireSeller | requireAdmin | resolveCustomerIdentity"]
        guard --> routeGuard["route-level guards, e.g. refuseBlockedCustomer"]
    end

    pre --> preHandlers
    preHandlers --> handler["Handler: load → call core → apply → respond"]
    handler --> core["app/core — the decision, pure"]
    core --> handler
    handler --> action["app/actions — one transaction:<br/>read, decide, write"]
    action --> db[("SQLite")]
    action --> handler
    handler --> render["reply.render(page) from addSiteRender<br/>this site's layout, flash, identity, unread count"]
    render --> send["onSend: securityHeaders"]
    handler -- "redirect" --> send
    errors --> send
    notfound --> send
    send --> response(["Response"])
    response --> after["onResponse: pageViewRollup upserts<br/>the count; eventBus fires 'changed'<br/>after any request that wrote"]
```

`healthCheck` sits outside all of it: `GET /health` is registered at the root,
so no site guard and no site layout reaches it, and it answers JSON.

| Plugin | File | What it adds |
| --- | --- | --- |
| `errorPages` | `plugins/error-pages.ts` | One root `setErrorHandler`: a thrown `ZodError` is 400, an error carrying a 4xx `statusCode` is that status, anything else is logged and rendered as a generic 500 — each in the layout of the site the request landed on. |
| `securityHeaders` | `plugins/security-headers.ts` | One `onSend` hook, so a page, the JSON health check, an uploaded file, and a 404 all carry the same headers. |
| `flashCookie` | `plugins/flash.ts` | `reply.setFlash` / `reply.takeFlash` over a signed one-request cookie. |
| `identityCookies` | `plugins/identity.ts` | The three signed actor cookies, `signedInActorId`, `resolveCustomerIdentity`, and the `requireSeller` / `requireAdmin` guards. |
| `pageViewRollup` | `plugins/page-views.ts` | One root `onResponse` hook that upserts `page_view_counts`. |
| `unreadMessages` | `plugins/unread-messages.ts` | `countUnreadMessages(actorType)` as a `preHandler`, decorating `request.unreadMessageCount`. |
| `eventBus` | `plugins/events.ts` | `app.events` (a typed `node:events` emitter), the `onResponse` hook that fires `changed` after any request that wrote, the `preClose` hook that fires `closing`, and `unreadEventsRoute(actorType)` serving `<prefix>/events` as `text/event-stream`. |
| `healthCheck` | `plugins/health.ts` | `GET /health`. |
| `addSiteRender` | `plugins/site-render.ts` | Called inside a site rather than at the root: gives that site a `reply.render(page)` carrying its layout, the flash, the identity, and the unread count, and returns the `SitePageRenderer` a 404 handed over by `callNotFound` renders through. |
| `rootPlugin` | `plugins/root-plugin.ts` | Marks a plugin as belonging to the root context (`Symbol.for('skip-override')` and `plugin-meta` set by hand — no `fastify-plugin` dependency). |

Naming follows the `naming` skill: actions are verb phrases, core enums name
states (`OrderStatus`), events are past tense. Files are kebab-case and named
for their primary export (`order-status.ts` exports `OrderStatus` and
`canTransitionOrder`). Database tables are snake_case plural; `CamelCasePlugin`
exposes them to TypeScript as camelCase (`price_cents` → `priceCents`).

## Sites

| Site | URL prefix | Plugin | Identity cookie | Theme |
| --- | --- | --- | --- | --- |
| Storefront | `/` | `shopSite` (`app/sites/shop/`) | signed `customer_id` (anonymous or verified) | Bright, open, white space, large imagery, one amber accent, brand recedes. |
| Seller portal | `/seller` | `sellerSite` (`app/sites/seller/`) | signed `seller_id` | Stock Tailwind, system font, vanilla controls, dense and tool-focused. |
| Admin site | `/admin` | `adminSite` (`app/sites/admin/`) | signed `admin_id` | Same as the seller portal: tools, tables, filters. |
| Magic links | `/auth/magic/:token` | `authSite` (`app/sites/auth/`) | writes whichever cookie the link names | No pages of its own; every answer is a redirect. |

Each site is one Fastify plugin registered in `buildApp`. It calls
`addSiteRender(site, { pages, layout })` for its own
`app/sites/<site>/views/layout.ejs`, adds `countUnreadMessages(<actorType>)`,
registers `signInRoutes({ actorType, ... })` and `unreadEventsRoute(<actorType>)`
(its `<prefix>/events` stream), adds its own `setNotFoundHandler`, and registers
its own routes behind whatever guard it needs:

- `sellerSite` registers `@fastify/multipart` (`attachFieldsToBody: true`) and
  puts every page except sign-in inside a child plugin carrying `requireSeller`.
- `adminSite` puts its pages inside `adminConsoleRoutes`, which carries
  `requireAdmin`. The sign-in pages stay outside it, or a signed-out admin
  would be redirected in a circle.
- `shopSite` puts its browsing pages inside `storefrontRoutes`, which carries
  `resolveCustomerIdentity` — the hook that mints an anonymous customer row.
  The sign-in pages stay outside it, so asking for a link leaves no row behind.

The three identity cookies are independent, so one browser can be a seller, a
customer, and an admin at the same time — the demo needs that. All three are
signed, `httpOnly`, `sameSite=lax`, and last a year. Every layout renders the
shared `app/views/partials/debug-alert.ejs`, which prints a flashed magic link
when `MAGIC_LINK_DELIVERY=flash` outside production. Flash is a signed,
one-request cookie set on the reply (`reply.setFlash`, `reply.takeFlash`).

Every unmatched URL renders the layout of the site it landed on rather than
Fastify's JSON: each site adds a `setNotFoundHandler`, and the prefixed portals
also register `site.all('/*')`, because `@fastify/static`'s root `/*` otherwise
claims every unmatched GET. `/nope` is a storefront 404 page; `/seller/nope` and
`/admin/nope` are their sites' own. A storefront miss reaches that page through
`@fastify/static`'s hand-off, whose 404 context snapshotted the root's hooks
before the site attached its own, so the header on that one page reads
signed-out whoever is asking. Reading the cookie there would mean an identity
hook at the root, and a query on every asset request.

`/auth/magic/:token` is shared by all three sites: it consumes the link, claims
the identity for the link's `actorType`, sets that site's cookie, and redirects
to the link's `redirectTo` or that actor's `ACTOR_SITES[...].homePath`.

## Identity

See [`identity.md`](identity.md) for the sequences.

- Passwordless for sellers, customers, and admins. `magic_links` holds a hashed
  token (`digestMagicLinkToken`, sha256 hex), `email`, `actor_type`
  (`seller` | `customer` | `admin`), `expires_at`, `consumed_at`, optional
  `redirect_to`. A link lasts `MAGIC_LINK_LIFETIME_MINUTES` (15) and works
  once — enforced by the UPDATE (`set consumed_at = ? where id = ? and
  consumed_at is null`), not by the read, so two requests arriving together
  cannot both spend it.
- `signInRoutes({ actorType, admits?, refusal?, accountView? })` is a plugin
  factory: `GET/POST /login`, `POST /logout`, `GET /account`, registered inside
  whichever site wants them, so all three share one implementation and keep
  their own layout and templates.
- Delivery is a port: `MagicLinkDelivery` (`app/delivery/`) with
  `flashMagicLinkDelivery` (flash the URL so the layout prints it in the debug
  alert) and `outboxMagicLinkDelivery` (queue a row in `outbox_messages` inside
  the caller's transaction and flash nothing). `selectMagicLinkDelivery` reads
  `MAGIC_LINK_DELIVERY` (`flash` | `outbox`); production refuses `flash`.
- Sellers: the first link for an address creates the seller row
  (`claimSellerIdentity`). No separate sign-up.
- Admins: `admins` rows are **seeded only** (Jonathan Beebe
  `jonathan-beebe@outlook.com`, Anna Schmunk `annaschmunk@pm.me`). `adminSite`
  passes `admits`, so an address with no `admins` row is never sent a link at
  all.
- Customers: every storefront visitor gets a `customers` row with `email = null`
  on first request; its id is in the signed `customer_id` cookie. A cookie alone
  is a browsing history — `signedInActorId(request, 'customer')` counts a
  customer as signed in only once `email` is set.
- Verifying an address runs a **discriminated-union plan**,
  `planCustomerIdentity` (`app/core/customers/identity-plan.ts`), over the
  anonymous row the cookie names and the row that already owns the address:
  `createVerified`, `claimAnonymous`, `signInExisting`, or `mergeAnonymousInto`.
  Each variant carries exactly the ids that case needs, so the action reads them
  with no null assertions.
- The merge is a **fold**, planned by `planCustomerMerge`
  (`app/core/customers/customer-merge-plan.ts`): cart quantities sum (clamped to
  stock), favorites de-duplicate. `REPOINTED_CUSTOMER_TABLES` — `orders`,
  `listing_events`, `notifications`, `conversations` — move wholesale. Carts and
  favorites are deliberately not in that list; re-pointing a cart would leave a
  customer with two. The anonymous row survives, and a `customer_merges` row
  records `anonymous_customer_id -> customer_id` so a stale cookie on another
  device resolves forward.
- Guest checkout = place the order as the anonymous customer, verify by link,
  then pay on `/orders/:id/pay`. The card is entered after verification and
  never stored. Verifying moves the order `pending_verification ->
  awaiting_payment` (`markAwaitingPayment`); it never pays.

## Commerce domain

Money is integer cents (`app/core/money.ts`, type `Cents`). Orders may span
sellers; fulfillment and escrow are tracked **per (order, seller)** in
`fulfillments`.

Question: which tables carry the marketplace, and how do they connect?

```mermaid
erDiagram
    sellers ||--o{ listings : owns
    customers ||--o{ favorites : has
    customers ||--o{ carts : has
    customers ||--o{ orders : places
    carts ||--o{ cart_items : holds
    listings ||--o{ listing_events : records
    listings ||--o{ listing_removals : moderated_by
    listings ||--o{ listing_faqs : answers
    orders ||--o{ order_items : contains
    orders ||--o{ payments : attempts
    orders ||--o{ fulfillments : split_by_seller
    sellers ||--o{ fulfillments : ships
    fulfillments ||--o{ ledger_entries : produces
    sellers ||--o{ payouts : receives
    payouts ||--o{ ledger_entries : settles
    customers ||--o{ customer_blocks : blocked_by
    admins ||--o{ listing_removals : issues
    admins ||--o{ customer_blocks : issues
    conversations ||--o{ messages : holds
    messages ||--o{ listing_faqs : published_from
```

Twenty-four tables, created across ten of the eleven migrations (the first
turns on write-ahead logging and creates nothing). `notifications`,
`magic_links`, `customer_merges`, `page_view_counts`, and `outbox_messages` are
left off this overview: the first three hang off the identity tables and the
last two stand alone. Columns, the identity half, and the caveats are in
[`data-model.md`](data-model.md).

Every column holding a string union carries a `CHECK` constraint built from
that union's `as const` array (`status in ('draft', 'for_sale', …)`), so a value
TypeScript would refuse cannot reach the file either; money and quantity columns
carry range checks, and `notifications` carries one asserting exactly one of
its three recipient columns is set. `page_view_counts.site` is the one union
column without a constraint — nothing but `pageViewSite(pathPattern)` writes
it. `app/db/schema-fidelity.test.ts` reads the
applied schema back and asserts the row types match it.

Those constraints were added to the original `create` migrations rather than
appended as new ones, so a database created before them is not upgraded by
`make migrate` — **an existing development database needs `make fresh`**, which
deletes the file, re-applies every migration, and re-seeds.

### Listing status

`draft → for_sale → sold`, `sold → for_sale` (stock restored after a declined
card), `archived` from `draft`/`for_sale`. Browse and search show only
`for_sale` listings with **no active removal**; a listing's own page
(`/art/:slug`) stays reachable through `sold` so a followed link keeps working
(`isOnStorefront`). `draft`, `archived`, and removed listings answer 404.
Quantity defaults to 1; a purchase decrements at placement and `sold` is
reached at 0 (`stockAfterSale`).

Seller input (title, description, medium, dimensions, price, quantity, image)
arrives through the route's zod schema and is turned into a value or a set of
errors by the pure `parseListingDraft` (`app/core/listings/listing-draft.ts`),
which returns `{ ok: true; value } | { ok: false; errors }` — so a route holds
one `if` and the rule stays out of the database layer. The image is a path
column (`listings.image_path`) written by an upload to
`public/uploads/<uuid>.<ext>`, with the extension taken from the file's own
magic bytes rather than the browser's filename (`app/core/listings/image-format.ts`);
`listingImageSource` falls back to an SVG generated from the title.

### Moderation (admin)

See [`admin.md`](admin.md).

- `listing_removals`: one row per admin action on a listing — `kind`
  (`temporary` | `permanent`), `reason`, `admin_id`, `created_at`, `lifted_at`
  (null while active). A listing with an active removal is off the storefront
  whatever its status; the seller sees the removal and reason in the portal.
  `temporary` can be lifted by an admin; `permanent` cannot (`canLiftRemoval`).
- `customer_blocks`: same shape for customers (`reason`, `admin_id`,
  `created_at`, `lifted_at`). A blocked customer can browse but cannot add to
  cart, check out, pay, or send messages; the storefront says so.
- At most one active removal per listing and one active block per customer.
  Raising a temporary removal to a permanent one is lift then remove.
- Visibility and standing are pure core predicates —
  `app/core/listings/listing-availability.ts` (`isOnStorefront`,
  `isPurchasable`) and `app/core/moderation/` (`activeRemoval`,
  `canLiftRemoval`, `activeBlock`, `customerStanding`, `canShop`). The admin
  site writes the rows; the storefront and portal read the predicates through
  `activeListingRemoval` / `currentCustomerStanding`. **`customerStanding` lives
  in `app/core/moderation/`, beside the removal predicate — not in
  `app/core/customers/`, which belongs to identity.**

### Order status

Question: what are the legal `OrderStatus` transitions?

```mermaid
stateDiagram-v2
    [*] --> pending_verification : guest places order
    [*] --> awaiting_payment : verified customer places order
    pending_verification --> awaiting_payment : email verified
    pending_verification --> cancelled : customer cancels
    awaiting_payment --> paid : card approved
    awaiting_payment --> payment_failed : card declined
    awaiting_payment --> cancelled : customer cancels
    payment_failed --> paid : retry approved
    payment_failed --> payment_failed : retry declined
    payment_failed --> cancelled : customer cancels
    paid --> partially_shipped : one fulfillment shipped
    paid --> shipped : all fulfillments shipped
    partially_shipped --> shipped
    shipped --> delivered : all fulfillments delivered
    delivered --> [*]
    cancelled --> [*]
```

Source of truth: `ORDER_STATUS_TRANSITIONS` in
`app/core/orders/order-status.ts`. `cancelled` is reachable: `cancelOrder`
(action + `POST /orders/:id/cancel`) covers the three `isCancellable` statuses
and restores the stock `placeOrder` took. A multi-seller order's status rolls up
from its fulfillments (`orderStatusFromFulfillments`).

Whether a cart may become an order at all is a core decision, not a route `if`:
`planOrderPlacement` (`app/core/orders/order-placement.ts`) reads each line
against the listing as it is now and answers
`{ ok: true; lines } | { ok: false; unavailable }`, naming every line that
stands in the way with an `UnavailableReason` (`removed`, `off_sale`,
`sold_out`, `short_stock`). `placeOrder` runs it inside its own transaction and
returns the refusal rather than throwing, so the checkout page re-renders with
every blocked line named at once, and a listing sold between the page and the
submit cannot be sold twice.

### Fulfillment status (per order × seller)

`awaiting_shipment → shipped → delivered`
(`FULFILLMENT_STATUS_TRANSITIONS`). Seller marks shipped (carrier + tracking);
customer confirms delivery from the order page. A seller's "order" **is a
fulfillment** — the portal's orders pages take a `fulfillments.id`, and UI copy
says "fulfillment" where the row is one.

### Escrow and payouts

See [`escrow.md`](escrow.md).

- Platform fee: `PLATFORM_FEE_PERCENT` is 10% of the fulfillment subtotal,
  computed once at order placement (`placeOrder`) and stored on `fulfillments`
  (`fee_cents`, `net_cents`). Later steps move the stored `net_cents`; they
  never re-price.
- `ledger_entries.entry_type`: `held` (+net, when the order pays), `released`
  (+net, when the fulfillment is delivered), `paid_out` (−amount, when included
  in a payout). `ledgerBalance` folds a seller's entries:
  `held = heldTotal − releasedTotal`, `available = releasedTotal + paidOutTotal`
  (adding a negative nets it down), `paidOut = −paidOutTotal`.
- Payout period = Monday–Sunday, the most recently completed one
  (`payoutPeriodEndingBefore`). `npm run payouts -- --as-of=DATE`
  (`app/cli/run-payouts.ts`, wrapped as `make payouts AS_OF=…`) calls
  `runWeeklyPayout` and writes one `payouts` row per seller with released and
  unpaid money. The `paid_out` entry is dated at `payoutPeriodEndsAt`
  (`T23:59:59.999Z` of the last day), so a re-run of the same period pays
  nothing. Period math is pure. The admin site exposes the run
  (`POST /admin/payouts`); the seller portal does not.
- Who gets paid is decided in core, not in the action: `planWeeklyPayout`
  (`app/core/escrow/payout-plan.ts`) takes the balances, the sellers already
  settled for this period, and the period, and returns a `PayoutIntent[]`. The
  action reads the rows, calls it, and writes what it returns; a seller with a
  payable balance who already has a payout for this period is skipped there
  rather than by a `where` clause.

### Fake payment

`decideCard(number)` in `app/core/payments/fake-card.ts`:

| Number | Decision |
| --- | --- |
| `4242 4242 4242 4242` | approved |
| `4000 0000 0000 0002` | declined: `generic_decline` |
| `4000 0000 0000 9995` | declined: `insufficient_funds` |
| anything else | declined: `invalid_card_number` |

Every non-digit is stripped, so spaces and dashes are ignored. Only the last
four digits are stored, one `payments` row per attempt.

### Notifications

`notifications` rows with exactly one recipient FK set (`seller_id`,
`customer_id`, or `admin_id`, held by a check constraint): `subject`, `body`,
`url`, `read_at`. `notify` is the one write point —
`itemSoldMessage` to the seller when the order pays, `orderShippedMessage` to
the customer when a fulfillment ships, `newMessageMessage` to the other side of
a conversation. `NotificationDelivery` (`app/delivery/notification-delivery.ts`)
is the port for carrying one out of the application; it takes a
`DeliveryContext` of `{ db, clock }` rather than nothing, which is why it lives
in `app/delivery/` and not in `app/core/`.

`notify` runs its inbox row and its delivery inside one `runInTransaction`, and
defaults to `outboxNotificationDelivery`, which queues a row in
`outbox_messages`. A business change that rolls back therefore sends nothing.
`ActionContext.notificationDelivery` stays the seam a test or another
deployment substitutes through; there is no environment variable for it.

## Outbox

Two things leave the application: sign-in links and notifications. Both take
the same path.

```mermaid
sequenceDiagram
    participant Route
    participant Action as Action (one transaction)
    participant DB as SQLite
    participant Drain as npm run outbox
    participant Disk as OUTBOX_DIR

    Route->>Action: notify / sendMagicLink
    activate Action
    Action->>DB: the business rows
    Action->>DB: insert outbox_messages (same transaction)
    deactivate Action
    Note over Action,DB: a rollback leaves neither

    Drain->>DB: select where delivered_at is null
    Drain->>Disk: renderMailMessage → <id>.eml
    Drain->>DB: set delivered_at
    Note over Drain,DB: outside any transaction — a<br/>synchronous connection must not<br/>be held across a file write
```

`renderMailMessage` (`app/core/notifications/mail-message.ts`) is pure: it takes
the `Message-ID` and the date as inputs, writes RFC-5322 headers with CRLF line
endings, and folds a CR or LF inside a header value to a space so nothing can
open a header of its own. Draining is `drainOutbox`
(`app/actions/outbox/drain-outbox.ts`), reachable as `npm run outbox`,
`make outbox`, and `POST /admin/outbox/drain`. `/admin/outbox` lists the queue
and `/admin/outbox/:id` shows one message with its link clickable — the demo's
mailbox once `MAGIC_LINK_DELIVERY=outbox` turns the debug alert off. There is no
SMTP.

## Messaging

See [`messaging.md`](messaging.md). One model serves every pairing: a
`conversations` row names its `kind` and its participants; `messages` rows hang
off it.

| `kind` | Participants | Opened from | Subject column |
| --- | --- | --- | --- |
| `admin_seller` | admin ↔ seller | `/seller/support`, or `POST /admin/sellers/:id/messages` | — |
| `admin_customer` | admin ↔ customer | `/support`, or `POST /admin/customers/:id/messages` | — |
| `fulfillment` | seller ↔ customer | `POST /seller/orders/:id/messages`, or `POST /orders/:id/fulfillments/:fulfillmentId/messages` | `fulfillment_id` |
| `listing_question` | customer ↔ seller | `POST /art/:slug/questions` | `listing_id` |

- `conversations`: `id`, `kind`, `seller_id?`, `customer_id?`, `admin_id?`,
  `listing_id?`, `fulfillment_id?`, `created_at`, `last_message_at`.
- `messages`: `id`, `conversation_id`, `sender_type` (`seller` | `customer` |
  `admin`), `sender_id`, `body`, `sent_at`, `read_at?`. A conversation has
  exactly two participants, so **one `read_at` per message** is unambiguous: the
  reader is always the participant who did not send it (`isUnreadBy`).
- `listing_faqs`: `id`, `listing_id`, `question`, `answer`,
  `source_message_id?`, `published_at`. **A row exists only while the entry is
  published** — unpublishing deletes it, `published_at` is `not null`, and the
  storefront reads the table with no predicate. Re-publishing is one click from
  the thread the answer came from, so there is no draft state to model. A seller
  answering a `listing_question` publishes the question + answer as an FAQ
  entry; the storefront listing page lists them. The seller can also edit or
  unpublish one.
- Find-or-open is a pure plan, `planConversation`
  (`app/core/messaging/conversation-plan.ts`), over the rows that already match
  the kind and the five id columns — the same shape as the identity plan. One
  thread per subject is what makes "message this seller" reach the same place
  every time.
- Who may read or post is a pure predicate, `conversationAccess`
  (`app/core/messaging/conversation-access.ts`), answering `mayRead` and
  `mayPost` separately: reading is being named in the participant column,
  posting is that plus standing. `postMessage` enforces it, so all three sites
  refuse a blocked sender with the same words.
- The support counterpart on both `/support` routes is the first admin by id.
  Admin rows are seeded and this prototype has no assignment model; with no
  admin row at all the route flashes and goes back.
- Each site has an inbox (`/messages`, `/seller/messages`, `/admin/messages`)
  listing its conversations by `last_message_at`, and a thread page that posts a
  reply. `conversationPath(actorType, id)` is core, because the same thread has
  three URLs. Anonymous customers can ask a listing question; the conversation
  re-points on merge.
- The `unreadMessages` plugin decorates `request.unreadMessageCount` and
  `countUnreadMessages(actorType)` is one `preHandler` per site, so
  `addSiteRender` hands every layout its badge beside the flash and the
  identity.
- The badge then moves without a reload. `unreadEventsRoute(actorType)` serves
  `GET <prefix>/events` as `text/event-stream` inside each site's own identity
  guard; the stream sends `retry: 3000`, the count now, and the count again each
  time it moves. What makes it move is a payload-free `changed` on `app.events`,
  fired by a root `onResponse` hook after any request that wrote (anything but
  `GET`, `HEAD`, or `OPTIONS`, answering under 400) — settled after commit, so no stream can read a count that a
  rollback is about to undo. 21 dependency-free lines of `public/app.js`, loaded
  by one `<script defer>` per layout, write the number into the badge the layout
  already rendered. With JavaScript off the badge is simply the number the page
  was rendered with.

## Site analytics

- `listing_events` (per listing: `view` | `favorite` | `unfavorite` |
  `cart_add`, optional `customer_id`, `occurred_at`) feed the seller's activity
  numbers. A `view` is recorded at most once per (listing, customer, hour) —
  `isRecordedOncePerHour` and `viewWindowStart` decide that, and
  `recordListingEvent` returns `null` when it collapses a repeat.
- Page views are rolled up, not logged per hit. The `pageViewRollup` plugin
  (`app/plugins/page-views.ts`) adds one root `onResponse` hook, so it reaches
  every site; `isCountablePageView` keeps successful HTML GETs;
  `pageViewSite(pathPattern)` derives the site from the URL prefix (`/seller`
  and `/admin` claim theirs, everything else is `shop`); and `recordPageView`
  upserts `page_view_counts(site, path_pattern, day, count)` against the unique
  index on those three columns, so the first hit of a day inserts and every
  later one increments in one statement. The pattern is the route's
  (`/art/:slug`), not the concrete URL, so a thousand listing pages share one
  row. A request that matched no route has no pattern and is counted against
  nothing. The admin site reads this table on `/admin/stats`.

## Readiness, shutdown, and logs

- `GET /health` is registered at the root, outside every site's guard, and
  answers JSON — which keeps it out of the page-view rollup for free, since that
  hook counts HTML only. It runs two checks, `select 1` and
  `pendingMigrations(db)`, and the pure `evaluateHealth` /`healthStatusCode`
  (`app/core/health/health-status.ts`) turn those plus `app.draining` into
  `ok` (200), `unavailable` (503), or `draining` (503). `docker-compose.yml`
  polls it with `node -e` and `fetch`, because the image has no `curl`.
- SIGINT or SIGTERM flips `app.draining` **before** closing, so the endpoint
  reports `draining` while in-flight requests finish; a force-exit timer fires
  after 10 seconds if `close()` hangs, `unref()`'d so it never keeps the process
  alive itself (`armGracefulShutdown`, `app/server.ts`).
- Logging is Fastify's own logger, configured in `app/logging.ts`: `genReqId`
  takes an inbound `x-request-id` or generates one, and a `req` serializer
  redacts the `seller_id` / `customer_id` / `admin_id` / `flash` cookie values so
  a signed cookie never lands in a log line. Business events carry an `event`
  field and are logged from the route shell after the action result is applied,
  never the magic-link token or URL: `order.placed`, `order.paid`,
  `order.declined`, `fulfillment.shipped`, `payout.run`,
  `magic_link.requested` / `consumed` / `refused`, and the four `moderation.*`.
  The four CLIs build their own logger with `createCliLogger` and log the same
  way (`payout.run`, `payout.paid`, `outbox.drained`, `outbox.drain_run`,
  `migrate.*`, `seed.*`); `no-console` is an eslint error with no override.

## Testing

- `node:test` with `node:assert/strict`. Tests are **sidecars**: `foo.ts` →
  `foo.test.ts` in the same directory.

| Command | What it runs |
| --- | --- |
| `npm test` | `node --test 'app/**/*.test.ts'` — the fast loop, no coverage gate |
| `npm run coverage` | adds `--experimental-test-coverage --test-coverage-include='app/**' --test-coverage-exclude='app/**/*.test.ts' --test-coverage-lines=95 --test-coverage-branches=90`, and writes `coverage/lcov.info` alongside the table it prints. `make test` and `make coverage` both run this. |
| `npm run typecheck` | `tsc --noEmit` |
| `npm run lint` | `tsc --noEmit`, then `eslint app` — `recommendedTypeChecked`, `complexity` ≤ 8, `max-depth` ≤ 3, `no-console`. Read-only. `make lint` runs this. |
| `npm run lint:fix` | `eslint app --fix` — the auto-fixable subset. `make lint-fix` runs this. |
| `npm run check` | lint, then `npm run assets`, then `npm run coverage`. `make check` runs this — the commit gate `.githooks/pre-commit` and CI both call. |
| `npm run routes` | boots the real app over `:memory:` and prints `printRoutes()` then `printPlugins()` (`app/cli/print-routes.ts`) |

CI has no compose stack, so it cannot run `make check` through Docker the way
the pre-commit hook does. It runs `npm run check` directly instead — the same
script `make check`'s recipe delegates to
([`.github/workflows/node.yml`](../../../.github/workflows/node.yml)) — then
uploads the `coverage/lcov.info` that run wrote. The hook and CI run one
identical command, so they cannot disagree.

- Core tests (`app/core/**`) import only the file under test. No database, no
  doubles.
- Coordination tests (actions, routes) build the whole app through
  `buildTestApp()` (`app/test/build-test-app.ts`) over
  `openDatabase(':memory:')` with migrations run and `fixedClock(TEST_INSTANT)`,
  drive it with `app.inject(...)`, and assert on rendered HTML and rows. The
  same file signs in each actor type without walking the link flow —
  `signInAsSeller`, `signInAsCustomer`, `signInAsAdmin`,
  `browseAsAnonymousCustomer`, each returning `{ id, cookies }` — and
  `takeDebugMagicLink` reads back the URL a response flashed for a test that
  does want to walk it.
- `app/test/commerce-world.ts` builds an action-level world (no HTTP) with a
  `TravellingClock`, so `app/actions/orders/order-lifecycle.test.ts` can walk
  place → pay → ship → deliver → payout across simulated days.
- Each site has its own fixtures beside its routes:
  `app/sites/seller/test-fixtures.ts` and
  `app/sites/shop/storefront-fixtures.ts`. Both build through the real actions
  rather than inserting rows, so a fixture cannot drift from what the
  application writes.
- `app/test/smoke.test.ts` holds the cross-site walks over HTTP: all three
  sites serving their own layout off one stylesheet and `/health` answering its
  JSON; a listing travelling from the seller signing in to their weekly payout;
  a listing question answered and published as an FAQ that then shows on the
  storefront; an admin removing a listing and it leaving the storefront; an
  admin blocking a customer and checkout refusing; an admin messaging a seller
  who reads it; a sign-in link asked for under outbox delivery, listed on
  `/admin/outbox`, drained to an `.eml` file; and one frame off a live
  `/seller/events` stream, which needs a real socket because `app.inject`
  buffers and a stream never ends.
- TDD: failing sidecar test, make it pass, refactor. A feature ticket is done
  when its flow has an integration test that walks it end to end.
- `make docs-check` renders every Mermaid block under `docs/` through
  `minlag/mermaid-cli` in Docker (`docker/docs-check.sh`) and fails on any
  diagram that does not parse.

## Repository layout

```
prototype/node/
  README.md            how to run, serve, test
  docker-compose.yml   one service: app, port 4000
  Dockerfile           node:24.19.0-bookworm-slim; dev, build, runtime targets
  docker/              entrypoint.sh, docs-check.sh
  Makefile             host-side wrappers over docker compose, plus image/run-image
  docs/                architecture + feature docs (this folder)
  work/                tickets and journal (orchestration only)
  src/                 the Node project: package.json, tsconfig.json, eslint.config.js, node_modules/
    app/               all TypeScript source
      server.ts        entry: builds the app from config, listens, drains on a signal
      app.ts           buildApp(deps): composition root
      config.ts        env → typed config, parsed by zod
      logging.ts       logger options, request id, redacting serializers
      clock.ts         Clock, systemClock, fixedClock
      core/            functional core: analytics, auth, cart, customers, escrow,
                       health, listings, messaging, moderation, notifications,
                       orders, payments, reports, shop, plus money.ts,
                       status-label.ts and transition-error.ts
      actions/         verbs over ActionContext: analytics, auth, carts, customers,
                       escrow, favorites, fulfillments, listings, messaging,
                       moderation, notifications, orders, outbox, plus
                       action-context.ts and transaction.ts
      db/              database.ts, node-sqlite-dialect.ts, schema.ts +
                       commerce-schema.ts (row types), count.ts,
                       migrations/, migrator.ts, migrate.ts, timestamp.ts, seed*.ts
      delivery/        MagicLinkDelivery + NotificationDelivery ports,
                       delivery-context.ts, the flash and outbox implementations,
                       outbox-message.ts
      http/            zod-type-provider.ts (validator compiler), request-schema.ts
      plugins/         error-pages, events, flash, health, identity, page-views,
                       root-plugin, security-headers, site-render, unread-messages
      sites/           shop/, seller/, admin/ — each a plugin with routes/,
                       views/, queries/ and its own helpers; auth/ is three
                       flat files (index.ts, sign-in-routes.ts,
                       request-origin.ts) and has no pages of its own
      views/partials/  shared partials: debug-alert.ejs, flash.ejs, head.ejs,
                       unread-badge.ejs
      cli/             run-payouts.ts, drain-outbox.ts, print-routes.ts,
                       parse-as-of.ts
      test/            build-test-app.ts, commerce-world.ts, log-lines.ts,
                       smoke.test.ts
      assets/app.css   Tailwind source
    public/            app.css (built, not committed), app.js, uploads/
    storage/           development.sqlite3 and outbox/ (neither committed)
```

## Mapping the project skills onto this stack

| Skill says | Here it means |
| --- | --- |
| `npm run test:run -- <pattern>` | `docker compose run --rm app node --test app/path/to/file.test.ts` |
| Vitest unit test | `node:test` sidecar importing only the file under test |
| React Testing Library integration test | `node:test` sidecar that builds the app with `buildTestApp` and uses `app.inject` |
| `src/` | `prototype/node/src/app/` |
