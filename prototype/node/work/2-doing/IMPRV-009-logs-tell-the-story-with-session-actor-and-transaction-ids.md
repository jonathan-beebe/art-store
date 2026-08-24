---
id: IMPRV-009
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-009: Logs tell the story with session, actor, and transaction ids

## Problem
IMPRV-003 gave the app structured pino logs with 17 named events and a request id, but request lines carry no actor (identity cookies are redacted, so nothing says who), there is no session id independent of sign-in, no unit-of-work id linking the lines of one action, and events are emitted once after the fact — there is no `will` → `did`/`refused`/`failed` story. The payload shape is pino's default, which differs from what PHP and Rails will emit. `listing.published` is still unplaced. `docs/alignment.md` §2 fixes the shared payload, phases, and event vocabulary.

## Goal
Reading the log for one request or one `txn_id` tells what was about to happen, what happened, and why it stopped, in the payload every prototype shares.

## Outcome
Every log line is one JSON object with the §2.1 fields (`ts`, `level`, `event`, `phase`, `msg`, `request_id`, `session_id`, `actor_type`, `actor_id`, `txn_id`, `data`, `error`, `duration_ms`); a `sid` cookie is minted on the first response; every write action logs `will` then `did`/`refused`/`failed` under one `txn_id`; every event in §2.3 that Node supports is emitted with its name; cookie values, tokens, card numbers, and email addresses never appear; a test asserts the payload shape of a sampled request + action, and one test reads a captured log for the checkout story in order.

## Why it matters
The user is in rapid development: the log is the primary debugging surface, and a line without a session or actor cannot be joined to the lines around it.

## Discovery notes
pino child loggers per request already exist; a `txn_id` can be a second child created where the transaction helper opens. Pino's `timestamp`/`formatters` options can produce `ts`/`level` as strings. The existing `src/app/test/log-lines.ts` helper captures lines for assertions. A rename map from today's names (`order.placed` → `order.place` with `phase: did`, etc.) belongs in the ticket's Working notes and in `docs/architecture.md`'s logging section.

## Related work
- docs/alignment.md §2
- IMPRV-003

## Working

### What was done

**Payload (§2.1).** `app/logging.ts` shapes every line: pino's `timestamp`
writes `ts` (`new Date().toISOString()`, so ISO-8601 UTC with milliseconds and
a `Z`), `formatters.level` writes the level's name in place of its number, and
`redact: { paths: ['error.stack'], remove: true }` drops the stack outside
development — so where a stack is allowed is decided once, where the
environment is already known, rather than by every caller. Fastify's `pid` and
`hostname` ride alongside, which §2.1 allows. Nothing in the table is renamed,
nested, or dropped.

**Fastify's own request lines are off.** They carry no `event` or `phase`.
`logController: new LogController({ disableRequestLogging: true,
requestIdLogLabel: 'request_id' })` — the top-level `disableRequestLogging` and
`requestIdLogLabel` options are deprecated in Fastify 5 and warn on every boot.

**`request_id`.** `requestIdHeader: false` and `genReqId` reads the header
itself, because `requestIdHeader` takes the value as it arrived whatever it
holds and never calls `genReqId`. `acceptRequestId` honours it only when
`isAcceptableRequestId` (`core/logging/request-id.ts`,
`^[A-Za-z0-9_-]{1,64}$`) passes, and mints a `randomUUID()` otherwise. Echoed
as `X-Request-Id` from the `onRequest` hook. Both cases are tested.

**`session_id`.** A `sid` cookie holding `ses_<ulid>`, minted in `onRequest`
(so the first response a browser gets carries it), `Max-Age` one year, `Path=/`,
`HttpOnly`, `SameSite=Lax`, `Secure` under `config.secureCookies`. Unsigned —
see the decisions below. `signIn` and `signOut` touch only the identity
cookies, so it survives both; tested.

**`actor_type` / `actor_id`.** `core/logging/request-actor.ts` is pure: the path
picks the side of the marketplace (`/admin` → admin, `/seller` → seller,
anything else → customer) and the identity cookie for that side names the
actor; when the side being visited has none, the strongest other identity the
browser carries stands in (admin → seller → customer). The ids come from
`identityId`, which unsigns and parses the cookie without a query. An anonymous
customer's `cus_…` counts. The address never appears. `createCliLogger` binds
`actor_type: "system"`.

**`txn_id`.** `runInTransaction` mints `txn_<ulid>` where it opens a unit of
work and hands the work a child logger carrying it. An action that joins its
caller's transaction joins its caller's `txn_id`, so one checkout reads back as
one id however many actions it ran. Nothing passes it by hand.

**The story (§2.2).** `app/log-story.ts` holds the primitive over a small
`AppLogger` type that pino's logger, Fastify's per-request child, and a test
double all satisfy: `tellStory` writes `will`, runs the work, and closes with
`did` / `refused` from the story's `ended`, or `refused` (a thrown
`TransitionError`, at `info`) / `failed` (anything else, at `error`, with
`{ type, message, stack }`). The exception is always rethrown, so logging the
story never changes what happens. `duration_ms` is `performance.now()` since
the `will`. `app/actions/action-story.ts` wraps it over an `ActionContext`:
`actionStory` opens the transaction and the story in one call, `actionStep`
writes a `doing`, `actionDid` writes a `did` with no `will` in front of it.

**Where the logging lives.** In the actions, not the routes — the `will` has to
be inside the transaction to carry the `txn_id`, and an action called from two
sites should log the same either way. `ActionContext` gained `log?: AppLogger`;
a caller with nowhere to write (a seed, a fixture, a unit test about the write)
leaves it out and the action stays silent through `SILENT_LOG`. Routes hand it
down with `requestActions(request)` (`app/http/request-actions.ts`). The
route-level logging that existed before is gone: `sites/shop/order-events.ts`
is deleted, and `moderation.ts`'s `logEvent` member with it.

**Redaction.** The cookie serializer and `redactedCookies` are deleted — with
Fastify's request lines off there is nothing to redact, and no cookie value is
built into a line anywhere. `core/logging/loggable-path.ts` logs
`/auth/magic/:token` as its pattern so the token never reaches `data.path`.
The outbox drain no longer logs the recipient address. Tested: a sign-in
request's captured lines contain neither the address nor the token; a guest
checkout's contain neither the address nor the card number; a moderation write's
contain neither the reason nor the address.

### Rename map

| Old | New | Phase |
| --- | --- | --- |
| `magic_link.requested` | `magic_link.request` | `did` |
| `magic_link.consumed` | `magic_link.consume` | `did` |
| `magic_link.refused` | `magic_link.consume` | `refused` |
| `order.placed` | `order.place` | `did` |
| `order.paid` | `order.pay` | `did` |
| `order.declined` | `order.pay` | `refused` |
| `fulfillment.shipped` | `fulfillment.ship` | `did` |
| `moderation.listing_removed` | `moderation.remove_listing` | `did` |
| `moderation.listing_removal_lifted` | `moderation.lift_listing_removal` | `did` |
| `moderation.customer_blocked` | `moderation.block_customer` | `did` |
| `moderation.customer_block_lifted` | `moderation.lift_customer_block` | `did` |
| `payout.paid` | `payout.pay` | `did` |
| `payout.run` | `payout.run` | `will` + `did` |
| `outbox.drained` | `notification.deliver` | `doing` |
| `outbox.drain_run` | `notification.deliver` | `will` + `did` |
| `migrate.removed` | `migrate.run` | `doing` |
| `migrate.applied` | `migrate.apply` | `did` |
| `migrate.run` | `migrate.run` | `will` + `did` |
| `seed.admins` | `seed.run` | `doing` |
| `seed.demo_data` | `seed.run` | `did` |

Newly emitted, having had no line before: `http.request`, `customer.merge`,
`listing.create`, `listing.update`, `listing.publish`, `listing.transition`,
`listing.view`, `cart.add`, `cart.update`, `cart.remove`, `order.cancel`,
`fulfillment.deliver`, `ledger.write`, `conversation.open`, `message.post`,
`faq.publish`, `faq.unpublish`, `notification.write`, `app.boot`,
`app.shutdown`.

### Left out

`order.sweep` and the sweep's own `order.cancel`, `fulfillment.decline`,
`refund.issue`, and `rate_limit.exceed` — the features are FEAT-019's and
FEAT-020's, and inventing them here would be inventing the features. The names
are in `LOG_EVENTS` (`core/logging/log-event.ts`) so the vocabulary reads whole
and `log-event.test.ts` checks it against the §2.3 table; the code that emits
them arrives with the features.

### Decisions

- **The `sid` cookie is unsigned.** The identity cookies are signed, but a
  signed Fastify cookie's value is `<value>.<signature>`, and §2.1 says
  `session_id` is the cookie's value and that it reads `ses_<ulid>`. A
  correlation id with no authority buys nothing from a signature, and PHP and
  Rails have to be able to write the same bytes.
- **The actor is read from the cookies in `onRequest`, before identity
  resolution.** Resolving a customer can create a row, so only the storefront's
  own hook does it, and that runs later. The cookie names the same id the hook
  resolves in every case but one: a browser with no customer cookie at all, whose
  first request logs no actor and whose second logs the id the first minted.
  §2.1 says "when known", and on that request nobody was known yet.
- **`requestLog` is registered immediately after `@fastify/cookie` and ahead of
  the static and site plugins.** A route inherits the root's hooks as they stand
  when its own context is built, so a hook added after `fastifyStatic` would
  never see a request for `/app.css`.
- **A 500 closes the request with `failed`, not `did`.** The error handler calls
  `logRequestFailure`, which sets `request.loggedFailure` so the `onResponse`
  hook stands down. One terminal line per request either way.
- **`data` keys are snake_case** (`order_id`, `amount_cents`, `status_from`),
  matching the names §2.1 uses, rather than the camelCase the row types use.
- **`writeLedgerEntry`** (`actions/escrow/write-ledger-entry.ts`) is new: the
  three places that inserted a ledger row by hand now go through it, which is
  what gives `ledger.write` one home.
- **`recordListingView`** (`actions/listings/record-listing-view.ts`) is new: it
  wraps `recordListingEvent` with the `listing.view` story at `debug`, so the
  once-per-hour collapse is a `refused` line rather than silence.
- **The test helper is `logLines`, not `log`.** `buildLoggedTestApp` returns
  `TestApp & { logLines }`; naming it `log` would have made the returned value
  stop satisfying the `ActionContext` the fixtures take.

### Deviations from the contract

- **Three framework lines carry no `event` or `phase`.** Fastify writes two
  `Server listening at …` lines from `listen()`; `@fastify/static` warns when
  its root is missing; `@fastify/multipart` writes one `debug` line while
  parsing an upload. Silencing them would mean dropping third-party diagnostics
  wholesale, which is worse than the gap. Everything the application itself
  writes conforms.
- **`http.request` `will` has no `txn_id`** and neither does `did`: a request is
  not a unit of work. Only the action lines inside it carry one.

### What PHP and Rails must match

Field names, types, and the `ts` format; the `sid` cookie's name, one-year
lifetime, and `Path` / `HttpOnly` / `SameSite=Lax` / `Secure`-in-production
attributes; the `^[A-Za-z0-9_-]{1,64}$` accept pattern for an inbound
`X-Request-Id` and echoing it back; the event names and which phase each
outcome closes with; snake_case `data` keys. Field ordering does not matter.
