# Alignment contract

Written 2026-08-23 against `main` (`605a056`). The three prototypes
(`prototype/node`, `prototype/php`, `prototype/rails`) implement the same
product; this document fixes the shapes they must share so a reader can put
two side by side and compare like with like. Idiom stays per stack. Names,
formats, state machines, env variables, make targets, and log payloads are
identical.

Research behind it: `__local__/prototype-alignment.md` (untracked).

## 1. Identifiers

Every table's primary key is a prefixed ULID stored as text. The public id is
the primary key; there is no second column.

Format: `<prefix>_<ulid>` — a 3-letter lowercase prefix, one underscore, then
the 26-character Crockford base32 ULID (uppercase, as the ULID spec renders
it). Example: `ord_01J5X3M9A2K8YB7Q4R6T1V0WZE`. Total length 30.

Rules:

- Foreign keys hold the same string as the referenced primary key.
- The id is minted in the shell (action / model layer) when the row is
  created, from the application's freezable clock, so seeds on a fixed clock
  stay reproducible in time order. Random bits stay random; within one
  millisecond the generator is monotonic.
- Ordering by creation uses the creation timestamp with the id as tiebreak
  (second-resolution timestamps tie), never the id alone.
- URLs carry the full prefixed id (`/orders/ord_…`, `/admin/customers/cus_…`).
  Storefront listing pages keep `/art/:slug`.
- An id whose prefix does not match the route's table answers 404, the same
  page as an unknown id. No prototype accepts an unprefixed ULID.
- The order number shown to customers and sellers is the order id, rendered
  bare — no `#` sigil before a prefixed id anywhere in copy.
- Fixtures and seeds may use hand-written ids of the right shape
  (`ord_00000000000000000000000001` is a valid ULID).
- Framework-owned tables (Rails `active_storage_*`, `solid_cable_messages`;
  Laravel `sessions`, `cache`, `jobs`, `failed_jobs`, `job_batches`;
  migration bookkeeping) keep the framework's keys.

Prefix table (one prefix per domain table; a table absent in a prototype is
simply unused there):

| Table | Prefix |
| --- | --- |
| admins | `adm` |
| sellers | `sel` |
| customers | `cus` |
| customer_merges | `cmg` |
| customer_blocks | `blk` |
| magic_links | `mlk` |
| listings | `lst` |
| listing_events | `lev` |
| listing_faqs | `faq` |
| listing_removals | `rmv` |
| carts | `crt` |
| cart_items | `cti` |
| favorites | `fav` |
| orders | `ord` |
| order_items | `oit` |
| payments | `pay` |
| refunds | `rfd` |
| fulfillments | `ful` |
| ledger_entries | `led` |
| payouts | `pyt` |
| conversations | `cnv` |
| messages | `msg` |
| notifications | `ntf` |
| outbox_messages | `obx` |
| page_view_counts | `pvc` |
| rate_limit_windows | `rlw` |
| sessions (the `sid` cookie value, §2) | `ses` |
| transactions (the `txn_id` log field, §2) | `txn` |

Generation per stack (the maker decides; these are the platform-first
options): Node — an owned ~30-line generator over `node:crypto`
`randomBytes`; PHP — `Str::ulid()` (Symfony Uid ships with Laravel) with a
prefix, via a `HasPrefixedUlid` trait on the base model; Rails — an owned
generator in `lib/` or the `ulid` gem, set through `attribute :id, default:`.
Each stack keeps one function that parses `"<prefix>_<ulid>"` and refuses the
wrong prefix; routes use it at the boundary.

No data migration: prototypes rebuild with `make fresh`. Existing migrations
may be rewritten in place.

## 2. Logging

Every log line is one JSON object on stdout, in every environment, in every
prototype. No prose logs, no per-environment format switch.

### 2.1 Payload

| Field | Type | Always | Meaning |
| --- | --- | --- | --- |
| `ts` | string | yes | ISO-8601 UTC with milliseconds, `Z` suffix |
| `level` | string | yes | `debug` \| `info` \| `warn` \| `error` |
| `event` | string | yes | dotted name from §2.3, e.g. `order.place` |
| `phase` | string | yes | `will` \| `doing` \| `did` \| `refused` \| `failed` |
| `msg` | string | yes | one human sentence, present tense for `will`/`doing`, past for `did` |
| `request_id` | string | on requests | one per HTTP request; echoed as `X-Request-Id` response header; honoured from an incoming `X-Request-Id` only when it matches `^[A-Za-z0-9_-]{1,64}$` |
| `session_id` | string | on requests | value of the `sid` cookie (`ses_<ulid>`), minted on the first response a browser gets and kept for a year, unchanged by sign-in/out; carried from the point the session is available — a framework's outermost request line may carry `request_id` alone |
| `actor_type` | string | when known | `seller` \| `customer` \| `admin` \| `system` |
| `actor_id` | string | when known | the actor's prefixed id; an anonymous customer's `cus_…` counts as known |
| `txn_id` | string | inside a unit of work | `txn_<ulid>` minted when an action's transaction opens; every line inside it carries the same value |
| `data` | object | when useful | entity ids and the small facts the line is about (`order_id`, `amount_cents`, `status_from`, `status_to`, …). Ids are prefixed ids. |
| `error` | object | on `failed` | `{ "type": "<class or code>", "message": "<text>" }` and, in development, `"stack"` |
| `duration_ms` | number | on `did`/`refused`/`failed` when a `will` preceded it | wall time since the `will` line |

Additional keys are allowed at the top level for framework-native fields the
stack's logger adds (Fastify's `pid`/`hostname`, Rails' `pid`), but nothing in
the table may be renamed, nested, or omitted where marked always.

Redaction: no cookie values, magic-link tokens, card numbers, or email
addresses in `data`. An actor's id identifies them; the address does not
appear. A line the framework writes with no event of its own uses the event
`app.log`.

### 2.2 The story

Every action that writes goes through `will` → `did` (or `refused` / `failed`).
`refused` is a domain refusal (a `TransitionError`, `DomainRuleViolation`,
validation failure) — the world is unchanged and the line is `info`. `failed`
is an exception the action did not expect — `error` level. `doing` is optional
and marks a long step inside the unit of work (a drain loop, a sweep over N
orders). Requests log `will` on entry (`http.request`) and `did` on response
with `status` and `duration_ms` in `data`.

Example, one checkout:

```json
{"ts":"2026-08-23T18:00:00.001Z","level":"info","event":"http.request","phase":"will","msg":"POST /checkout","request_id":"req_1","session_id":"ses_01J…","actor_type":"customer","actor_id":"cus_01J…","data":{"method":"POST","path":"/checkout"}}
{"ts":"2026-08-23T18:00:00.004Z","level":"info","event":"order.place","phase":"will","msg":"placing an order from the cart","request_id":"req_1","session_id":"ses_01J…","actor_type":"customer","actor_id":"cus_01J…","txn_id":"txn_01J…","data":{"cart_id":"crt_01J…","line_count":2}}
{"ts":"2026-08-23T18:00:00.019Z","level":"info","event":"order.place","phase":"did","msg":"placed the order","request_id":"req_1","session_id":"ses_01J…","actor_type":"customer","actor_id":"cus_01J…","txn_id":"txn_01J…","duration_ms":15,"data":{"order_id":"ord_01J…","total_cents":12000,"status":"awaiting_payment","fulfillment_ids":["ful_01J…","ful_01K…"]}}
{"ts":"2026-08-23T18:00:00.021Z","level":"info","event":"http.request","phase":"did","msg":"POST /checkout 303","request_id":"req_1","session_id":"ses_01J…","actor_type":"customer","actor_id":"cus_01J…","duration_ms":20,"data":{"status":303}}
```

### 2.3 Event vocabulary

`<subject>.<verb>` in the imperative; the `phase` field carries tense. Every
prototype emits every event below that its features support.

| Event | Emitted by |
| --- | --- |
| `http.request` | every request (will on entry, did on response), including 404s and CSRF refusals |
| `magic_link.request` | sign-in form submit; `refused` when the address is not admitted or the rate limit trips |
| `magic_link.consume` | verification; `refused` on expired/used/foreign token |
| `customer.merge` | anonymous → verified fold |
| `listing.create`, `listing.update`, `listing.publish`, `listing.transition` | seller portal; `transition` carries `status_from`/`status_to` |
| `listing.view` | the storefront listing page, once per (listing, customer, hour) collapse — log the collapse as `refused` at `debug` |
| `cart.add`, `cart.update`, `cart.remove` | storefront |
| `order.place` | checkout; `refused` carries the blocked lines |
| `order.pay` | card submit; `did` on approval, `refused` on decline with `decline_reason` |
| `order.cancel` | customer cancel, admin cancel, and the stale sweep (`actor_type` says which) |
| `order.sweep` | the stale-order sweep run (`doing` per order, `did` with the count) |
| `fulfillment.ship`, `fulfillment.deliver`, `fulfillment.decline` | seller / customer / seller |
| `refund.issue` | seller decline and admin refund; `data` names `refund_id`, `fulfillment_id`, `amount_cents`, `reason` |
| `ledger.write` | every ledger entry (`debug`) |
| `payout.run`, `payout.pay` | the weekly payout: one `run` with the period, one `pay` per seller |
| `conversation.open`, `message.post`, `faq.publish`, `faq.unpublish` | messaging |
| `notification.write`, `notification.deliver` | in-app write and transport delivery |
| `moderation.remove_listing`, `moderation.lift_listing_removal`, `moderation.block_customer`, `moderation.lift_customer_block` | admin |
| `rate_limit.exceed` | any limit trip (`warn`), `data` carries `limit`, `key`, `retry_after_seconds` |
| `migrate.run`, `migrate.apply`, `seed.run` | CLI |
| `app.boot`, `app.shutdown` | process lifecycle |

The vocabulary is closed: a write with no event above stays silent rather than
minting a name one prototype has and the others lack. Reserved for a future
round: `favorite.toggle`, `conversation.read`, `faq.update`, `session.start`.

## 3. Rate limits

Every limit has a name, an env variable, and a key. Values are
`<count>/<window>` where window is `<n>s`, `<n>m`, or `<n>h`. Setting a
variable to `off` disables that limit. Defaults apply when the variable is
unset. Limits are read at boot; a malformed value refuses to boot.

| Name | Env | Default | Keyed by | Guards |
| --- | --- | --- | --- | --- |
| `magic_link_request` | `RATE_LIMIT_MAGIC_LINK_REQUEST` | `5/15m` | email address (lowercased) and, separately, client ip | every sign-in POST on all three sites, and guest checkout's implicit link |
| `magic_link_consume` | `RATE_LIMIT_MAGIC_LINK_CONSUME` | `20/15m` | client ip | the verification GET |
| `message_post` | `RATE_LIMIT_MESSAGE_POST` | `30/1h` | actor id | every message POST |
| `conversation_open` | `RATE_LIMIT_CONVERSATION_OPEN` | `10/1h` | actor id | listing question, support, fulfillment thread opens |
| `checkout` | `RATE_LIMIT_CHECKOUT` | `10/1h` | customer id | POST checkout |
| `payment_attempt` | `RATE_LIMIT_PAYMENT_ATTEMPT` | `5/15m` | order id | POST pay |
| `listing_write` | `RATE_LIMIT_LISTING_WRITE` | `60/1h` | seller id | listing create/update/upload |

Behaviour on trip: HTTP 429, `Retry-After: <seconds>` header, the site's own
HTML page ("Too many requests — try again in N minutes"; for a form, the form
re-renders with that sentence as a field-less error), one `rate_limit.exceed`
log line, no side effect performed. A POST that came from a form re-renders
that form — every form, including the storefront question box and the
fulfillment message form. An email-keyed `rate_limit.exceed` line logs
`sha256:<first 16 hex>` of the address, never the address.

Storage: a fixed-window counter per (name, key, window_start) that survives a
process restart. Node — a `rate_limit_windows` table in the same SQLite file;
PHP — Laravel's `RateLimiter` over the database cache store; Rails — the
built-in `rate_limit` controller macro over Solid Cache (or an equivalent
table-backed store). Client ip comes from the socket in development and from
the first trusted proxy header only when `TRUSTED_PROXIES` is set.

## 4. Transaction lifecycle

### 4.1 States

Order:

```
pending_verification ─verify─▶ awaiting_payment ─approve─▶ paid
        │                          │  ▲                     │
        │                     decline  retry                 ├─▶ partially_shipped ─▶ shipped ─▶ delivered
        │                          ▼  │                     │
        │                     payment_failed                 └─▶ refunded  (every fulfillment declined or refunded)
        └──────────── cancel ──────┴─────────▶ cancelled
```

- `cancelled` is reachable from `pending_verification`, `awaiting_payment`,
  and `payment_failed`, by the customer, by an admin, or by the stale sweep.
  Stock is restored. A cancelled order cannot be paid.
- `refunded` is reached when every fulfillment on a paid order is `declined`
  or `refunded`. A mixed order (one shipped, one declined) rolls up from its
  live fulfillments only: `paid` / `partially_shipped` / `shipped` /
  `delivered` are computed over fulfillments that are neither declined nor
  refunded. `orders.refunded_cents` carries the sum of its refunds.
- An admin cancel records a reason, like decline and refund.
- The stale sweep cancels `pending_verification` orders strictly older than
  `STALE_ORDER_HOURS` (default `24`). It runs from `make sweep` (a CLI /
  artisan command / rake task) and is idempotent.

Fulfillment:

```
awaiting_shipment ─ship─▶ shipped ─deliver─▶ delivered
        │                    │                  │
     decline              refund             refund
        ▼                    ▼                  ▼
     declined            refunded           refunded
```

- `decline` is the seller's, allowed only from `awaiting_shipment`, with a
  reason (1–500 chars). It restores that fulfillment's stock (`sold →
  for_sale` where the listing was sold out) and issues a refund for the
  fulfillment's `subtotal_cents`.
- `refund` is the admin's, allowed from `shipped` and `delivered` (a dispute
  outcome) and also from `awaiting_shipment` (admin acting for a silent
  seller), with a reason. It does not restore stock. It issues a refund for
  the fulfillment's `subtotal_cents`.
- Decline or refund on a fulfillment already `declined`/`refunded` is refused.
  Ship after decline is refused. Both checks run inside the transaction that
  writes.

Payment (unchanged): `payments` rows record each card attempt, `approved` or
`declined` with `decline_reason`. The fake card table stays: `4242…`
approved, `…0002` declined, `…9995` declined (insufficient funds), other
numbers invalid at the form. The customer may retry after a decline or cancel.

Refund: a `refunds` row per issue — `id` (`rfd_`), `order_id`,
`fulfillment_id`, `payment_id`, `amount_cents`, `reason`, `issued_by_type`
(`seller` | `admin`), `issued_by_id`, `created_at`, with `fulfillment_id`
unique — one refund per fulfillment. `issued_by_type` is a plain enum plus an
id, never a framework polymorphic association. Refunds always succeed
(no gateway); the amount is always the whole fulfillment subtotal (no partial
line refunds in this cut).

### 4.2 Ledger

Entry types: `held`, `released`, `paid_out`, `refunded`. The balance is still a
fold, and the fold groups by `(fulfillment_id, entry_type)` — a flat per-type
fold cannot reproduce the timings below. "Refund after release" is decided by
whether that fulfillment's `released` entry exists, not by its status. A `refunded` entry carries `-net_cents` for the fulfillment:

- Refund before release: `held` +net, `refunded` −net → the seller's held
  balance returns to zero for that fulfillment; nothing releases.
- Refund after release (shipped/delivered): `held` +net, `released` moves it
  to available, `refunded` −net → available balance drops; a negative
  available balance is carried and netted against the seller's next payout.
- Refund after payout: `refunded` −net against the seller; the next payout
  period settles the negative (a payout of ≤ 0 writes no `paid_out` row and
  the negative carries forward).

The platform fee on a refunded fulfillment is forgone: accounting reports
`fees_earned_cents` over fulfillments that are not declined/refunded, and a
`fees_refunded_cents` total beside it.

### 4.3 Sad paths every prototype covers with tests

- Pay a cancelled / refunded / already-paid order → refused.
- Cancel a paid order → refused (the path is refund).
- Decline after ship → refused. Ship after decline → refused.
- Refund twice → refused. Refund an unpaid order's fulfillment → refused.
- Seller declines another seller's fulfillment → 404.
- Customer cancels another customer's order → 404.
- Sweep never touches `awaiting_payment` or anything younger than the cutoff.
- Stock: decline restores exactly the declined quantities; a listing that was
  `sold` because of this order returns to `for_sale`; admin refund restores
  nothing.
- Balance fold after each of the three refund timings matches the table in
  §4.2.

### 4.4 Surfaces

- Customer: Cancel button on unpaid orders; order page shows declined /
  refunded fulfillments with the reason and the refund amount.
- Seller: Decline form (reason) on `awaiting_shipment` fulfillments; earnings
  page shows `refunded` movements.
- Admin: order detail and fulfillment detail pages with a Cancel (unpaid) and
  Refund (per fulfillment, with reason) action; refund rows listed on the
  order; `refunded` filter values on orders/fulfillments lists; ledger browser
  filters by `refunded`.
- Notifications: the counterpart is notified in-app on decline, refund, and
  cancel (customer ← seller decline; seller ← admin refund/cancel; customer ←
  admin refund/cancel).

## 5. Admin feature set

The Node admin site (`prototype/node/docs/admin.md`) is the reference. PHP and
Rails implement the same pages and actions with the same filters; page
layout is per stack.

| Path | Content |
| --- | --- |
| `/admin` | tallies for every listing / order / fulfillment status (zero rows still listed), platform money (held, available, paid out, fees earned, fees refunded, refunded), page views this week |
| `/admin/sellers`, `/admin/sellers/:id` | list with balances folded once; detail with listings, fulfillments, payouts, ledger balance |
| `/admin/customers?standing=all\|verified\|anonymous\|blocked`, `/admin/customers/:id` | anonymous rows included; detail with orders, favorites, cart, block history, merge history |
| `/admin/listings?status=&seller=&removed=any\|removed\|visible`, `/admin/listings/:id` | across sellers |
| `/admin/orders?status=&customer=`, `/admin/orders/:id` | detail with items, payments, fulfillments, refunds, actions (§4.4) |
| `/admin/fulfillments?status=&seller=`, `/admin/fulfillments/:id` | detail with refund action |
| `/admin/accounting` | per-seller reconciliation (held / available / paid out / refunded), fees earned and refunded |
| `/admin/ledger?seller=&type=` | ledger browser with folded totals for the filtered set |
| `/admin/payouts?seller=`, `POST /admin/payouts` | payout history; run the weekly payout for every seller (`as_of` optional) |
| `/admin/stats` | page views by day (7-day window) and by route pattern, listing event tallies |
| `POST /admin/listings/:id/removals`, `…/removals/lift` | temporary / permanent removal with reason; lift refused for permanent |
| `POST /admin/customers/:id/blocks`, `…/blocks/lift` | block with reason; block removes cart add, checkout, pay, message post |
| `/admin/messages`, `/admin/messages/:id` | existing |

Decisions carried by this table:

- Payouts are a platform action. The seller-portal "run payouts" debug button
  in PHP and Rails is removed; sellers see balances and payout history only.
- Page views are rolled up at response time into `page_view_counts
  (site, path_pattern, day, count)`; a `listing_events` `view` is collapsed to
  one row per (listing, customer, UTC hour). PHP and Rails adopt both.
- Ownership refusals answer 404 everywhere; admin pages are behind one guard
  hook/middleware/`before_action`, never per route.
- An empty filter value means "all"; an unrecognised value answers 400.
- `path_pattern` is stored bare (`/art/:slug`, no format suffix); HEAD
  requests are not counted as page views.
- A removed listing leaves every storefront surface: browse, search,
  `/art/:slug`, the favorites page, and an existing cart line (the row stays,
  the card is marked unavailable and excluded from the total).

## 6. Workflows

### 6.1 Make vocabulary

Every prototype `Makefile` answers these targets with these meanings. A
target the stack has no use for still exists and prints one line saying so.

| Target | Meaning |
| --- | --- |
| `up`, `down`, `build`, `logs`, `shell` | the compose stack |
| `test` | the full suite, with the stack's coverage gate |
| `smoke` | the smoke walk only |
| `coverage` | the suite with an HTML/LCOV report |
| `lint` | style + static analysis, read-only (`eslint`+`tsc`; `pint --test`+`phpstan`; `rubocop`) |
| `lint-fix` | the auto-fixable subset applied |
| `assets` | build CSS/JS |
| `check` | `lint` → `assets` → `test`; the commit gate |
| `migrate`, `fresh`, `seed` | schema and data |
| `routes` | print the route table |
| `payouts`, `sweep`, `outbox` | the scheduled jobs, by hand (`AS_OF=`, `DIR=` as today) |
| `image`, `run-image` | build the production image (the Dockerfile's `runtime` target); run it standalone on a host port that does not collide with `up`'s |
| `hooks` | (root Makefile) `git config core.hooksPath .githooks` |

`COMPOSE_PROJECT_NAME` is exported by every prototype Makefile as
`<checkout-dir>-<prototype>` so a worktree and the main checkout never share a
container. Published ports still collide; `make up` in two checkouts of the
same prototype is refused by Docker, as intended.

### 6.2 Commit gate

`.githooks/pre-commit` at the repository root runs `make -C prototype/<x>
check` for every prototype with staged changes outside `work/` and `docs/`.
`make hooks` at the root installs it. CI (`.github/workflows/<x>.yml`) runs
the same `make check` per prototype on push and pull request, so the hook and
CI cannot disagree.

## 7. Decisions recorded

From `__local__/prototype-alignment.md` §8:

1. Live thread updates: badge-only is the shared CX. Rails keeps its open-thread
   Turbo stream as a stack strength; Node and PHP do not add one.
2. Support-thread keying stays per-operator (first admin by id). Reworking the
   conversation shape is deferred; Rails `IMPRV-002` stays open.
3. Node adopts CSRF tokens (double-submit or synchronizer) on every POST form.
4. Attachments stay as they are per stack.
5. Payouts run from the admin site only (§5).
6. ULID prefixes: §1. Timezone rendering: deferred; UTC stays the rendered zone.
7. CSRF refusal status stays per-stack idiom — Node 403, Laravel 419,
   Rails 422 — each recorded in its `docs/review.md`.
8. `customer_blocks` stay behind on merge in all three, so a blocked anonymous
   customer can escape a block by verifying into an unblocked account. Shared
   gap, held for a product decision; no prototype fixes it unilaterally.

## 8. Reconciliation log

2026-08-25, after the three alignment lanes finished: §1 (freezable clock,
monotonic-within-ms, timestamp-then-id ordering, bare ids), §2 (`session_id`
availability, `duration_ms` on `refused`, `app.log`, closed vocabulary,
`http.request` on 404s), §3 (form re-render, hashed email keys), §4 (strict
sweep cutoff, admin-cancel reason, fulfillment-grouped fold, released-entry
timing, unique refund per fulfillment), §5 (400 on unknown filters, bare
`path_pattern`, no HEAD, removal reach), §7 (decisions 7–8). Known deviations
outstanding: PHP answers "treat as absent" where §5 now says 400 on an
unrecognised filter value, and PHP lacks the `listing_faqs
(listing_id, source_message_id)` uniqueness — both queued as PHP follow-ups.
