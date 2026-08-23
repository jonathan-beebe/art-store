---
id: IMPRV-003
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-003: Structured logging through the built-in logger

## Problem
The Fastify logger is configured (`app/app.ts:51`, `Fastify({ logger: { level: config.logLevel } })`) and never called. `grep -rn "\.log\." app/` returns nothing outside imports; `grep -rn "request\.log\|req\.log\|app\.log\|\.log\.info" app/` returns zero hits. Fastify's built-in per-request access log runs, but `placeOrder`, `runWeeklyPayout`, `sendMagicLink`, `signInWithMagicLink`, `blockCustomer`, and `removeListing` write rows and emit nothing. A declined card, a consumed magic link, and a payout run are invisible in the log stream. No `setErrorHandler` exists anywhere in the tree either, so an unexpected throw is logged only by Fastify's default handler.

The eight `console.log` calls in the tree are all in the three CLI entrypoints (`app/cli/run-payouts.ts:16,18,20`, `app/db/migrate.ts:9,18,21`, `app/db/seed.ts:11,14`), which is defensible for a CLI's own stdout. `run-payouts.ts` is meant to run from cron per `docs/architecture.md`, but it produces unstructured text, never sets an exit code, and logs nothing on failure beyond the default unhandled-rejection dump. Nothing in the eslint config stops a stray `console.log` from appearing in a route handler — `src/eslint.config.js` sets only `complexity` and `max-depth`.

## Goal
Every request and every business event worth an operator's attention is a structured log line through the built-in logger, and no `console.log` can appear outside a CLI entrypoint undetected.

## Outcome
- Every request line carries a request id taken from `x-request-id` or generated.
- Identity and flash cookies are redacted.
- Order placed/paid/declined, payout run, magic link requested/consumed, listing removed, customer blocked each emit one structured line from the shell.
- The CLIs log through the same logger and set `process.exitCode` on failure.
- eslint forbids `console` outside `app/cli` and `app/db` entrypoints.
- Core stays silent.

## Why it matters
The doctrine calls for structured logging via the built-in logger — a child logger per request, no `console.log`. As configured, the logger produces nothing beyond Fastify's default request/response autolog, so a payout run or a declined card leaves no trace an operator could search for. The gate that would catch a stray `console.log` in a route handler does not exist, so the rule is observed by convention rather than enforced.

## Discovery notes
Do not add a logger to `ActionContext` — that puts I/O into the layer that composes core, and every action signature would change. Log in the shell, where the request already is:
- `genReqId` from the incoming `x-request-id`, falling back to `crypto.randomUUID()`; `requestIdHeader`/`requestIdLogLabel` so the id is in every child-logger line.
- Serializers that redact the identity cookies (`seller_id`, `customer_id`, `admin_id`) and the `flash` cookie, which currently carries the debug magic link in cleartext.
- A handful of business events at the route level, after the core result is applied: `request.log.info({ event: 'order.paid', orderId, amountCents })`, `'listing.published'`, `'payout.run'`, `'moderation.listing_removed'`, `'magic_link.requested'`. Roughly ten events, not a hundred.
- Add a `setErrorHandler` that logs the error with the request id before rendering (this overlaps IMPRV-001's error-handler work — coordinate rather than duplicate the registration).
- Add `'no-console': 'error'` to the eslint config with an override allowing it under `app/cli/**` and `app/db/*.ts`.
- Give the payout CLI a pino instance from the same config as the server, log one structured line per payout and a summary, and set `process.exitCode` on failure so a scheduler notices.
- `logLevel: 'silent'` is already set in `app/test/build-test-app.ts:22`, so tests stay quiet with no further change.
- Leave the CLI `console.log` calls as they are if moving them to the logger would make `make payouts` output less readable as plain stdout — say why in a comment either way.

Files expected to touch: `app/app.ts` (logger config, serializers, request id), possibly a new `app/plugins/request-logging.ts` if the config grows past a few lines, `src/eslint.config.js`, `app/cli/run-payouts.ts`, and roughly ten route handlers across all three sites where business events are emitted.

## Related work
- 01-deps-platform.md: "The Fastify logger is configured and never used; `console.log` is the only output"
- 05-shell-ops.md: "The logger is configured and then never used; no business event is ever logged", "The payout CLI reports through `console.log` with no exit code"
- 07-showcase.md: showcase opportunity #6 ("Structured request logging + business events through the built-in logger")
- IMPRV-001 (both touch `setErrorHandler`; coordinate the registration)

## Working

Re-validated the problem against the current tree before touching anything. `setErrorHandler` already exists — IMPRV-001 landed it as `plugins/error-pages.ts` (`addErrorPage`), and it already logs a server fault through `request.log.error({ err }, ...)` with the request id the child logger carries. Nothing further needed there; no duplicate registration added.

**Logger config** — new `app/logging.ts` (not an anchored edit to `app.ts`'s logger block, since another worker was concurrently decorating `app.ts` with `events`): `loggingOptions(config, { stream? })` returns `genReqId` (reads `x-request-id`, falls back to `crypto.randomUUID()`), `requestIdHeader: 'x-request-id'`, `requestIdLogLabel: 'requestId'`, and a `logger.serializers.req` that extends Fastify's own request fields with a `cookies` object — parsed from the raw `Cookie` header, `seller_id`/`customer_id`/`admin_id`/`flash` values replaced with `[redacted]`, everything else passed through. `flash` is redacted because it carries the debug magic link in cleartext. `createCliLogger(config, { stream? })` builds a plain pino instance at the same `LOG_LEVEL` for the four CLI entrypoints. `app.ts` took two small anchored edits: import `loggingOptions`, and `Fastify({ ...loggingOptions(config, { stream: loggerStream }), trustProxy })` in place of the old inline `logger: { level }` — `loggerStream` is a new optional field on `AppDependencies`/`buildTestApp` overrides, unset in the running app (pino writes to stdout), set by a test that wants to capture what was logged (`app/test/log-lines.ts`'s `captureLogLines()`).

Fastify's `requestIdLogLabel` (and, per its own deprecation notice, `disableRequestLogging`) are marked `@deprecated` in favor of a `logController` option, removed only in fastify@6; we're pinned to `^5.12.1`. Used it anyway since the ticket names it explicitly — it prints one `FSTDEP024` warning line per test-app build, harmless but noisy in `npm test` output. Flagged, not fixed; migrating to `logController` is a bigger surface than this ticket's scope.

**Business events** — one `request.log.info({ event, ...ids }, message)` per route, after the action result is applied, never before:
- `order-payments.ts`: `order.paid` / `order.declined` (`orderId`, `amountCents`) from the POST `/orders/:id/pay` charge outcome.
- `admin/routes/payouts.ts`: `payout.run` (`count`, `totalCents`) after `runWeeklyPayout`.
- `auth/sign-in-routes.ts`: `magic_link.requested` (`actorType`, `email`) — deliberately never logs `delivered` (the flash payload carrying the debug magic link) or the URL/token.
- `auth/index.ts`: `magic_link.consumed` (`actorType`, `actorId`) on success; `magic_link.refused` (`actorType?`, `reason`) for both the `refused` outcome (`reason` = the `MagicLinkRefusal`) and the `unknown` outcome (no link at all — `reason: 'unknown_token'`, no `actorType` since none exists).
- `admin/routes/moderation.ts`: added a `logEvent(subjectId, adminId, submitted)` field to the existing `ModerationCommand` factory (one function per write, called once after `apply` succeeds) — `moderation.listing_removed` (`listingId`, `adminId`, `kind`, `reason`), `moderation.listing_removal_lifted` (`listingId`, `adminId`), `moderation.customer_blocked` (`customerId`, `adminId`, `reason`), `moderation.customer_block_lifted` (`customerId`, `adminId`).

**Left alone, per territory**: `order.placed` at checkout, `order.paid` at the checkout-time payment branch, and `fulfillment.shipped` — `shop/routes/checkout.ts`, `carts.ts`, and everything under `seller/routes/` are RFCTR-003's territory this cycle. Follow-up ticket needed to place those three events once that refactor lands. `listing.published` (an admin-site event named in the ticket's discovery notes) has no assigned route either — publishing a listing is a seller-portal write (`seller/routes/listings.ts`), same held territory; left for the same follow-up.

**CLIs** (`app/cli/run-payouts.ts`, `app/cli/drain-outbox.ts`, `app/db/migrate.ts`, `app/db/seed.ts`): each now takes an optional `logger?: pino.Logger` (defaults to `createCliLogger(loadConfig(env))`), replaced every `console.log` with `log.info({ event, ...fields }, message)` (one line per unit of work plus a summary line), and wraps its body in `try { ... } catch (error) { log.error({ err: error }, '...'); process.exitCode = 1 } finally { await db.destroy() }` — a failed run is a structured error line and a nonzero exit code, not a raw stack trace. Argument parsing (`parseArgs`) stays outside the `try`, so a bad flag still rejects the promise the way the existing tests expect. Manually smoke-tested `migrate.ts`/`seed.ts` end-to-end against a scratch database (`node app/db/migrate.ts` then `node app/db/seed.ts`, `LOG_LEVEL` default) — both produced structured JSON lines and exited 0.

**`pino` dependency**: not in `package.json` before this ticket — only present as `fastify`'s own transitive dependency. Added `"pino": "^10.3.1"` to `dependencies`, pinned to the version already resolved in `node_modules`/`package-lock.json` (`npm ls pino` showed `fastify@5.12.1 └── pino@10.3.1 deduped` before the change), so the new top-level import is declared rather than borrowing an undeclared transitive one. Hand-edited `package-lock.json`'s root `packages[""].dependencies` rather than running `npm install` — a first attempt regenerated unrelated lockfile drift (`engines`, several optional `@tailwindcss/oxide-wasm32-wasi` transitive entries) that had nothing to do with this ticket, reverted and applied the one-line change by hand instead.

**eslint**: added `'no-console': 'error'` with no override. Every remaining `console.*` call in the tree was already confined to the four CLI/db entrypoints (`grep -rn "console\." app --include="*.ts"` before this change showed only those, plus one unrelated `console.ts` filename match), and all four now log through the logger instead, so no `app/cli/**`/`app/db/*.ts` carve-out was needed — matches the ticket's stated preference.

**Core stays silent**: no logger reached `app/core`, `app/actions`, or `ActionContext` — every `request.log`/CLI `log` call lives in `app/sites/**/routes` or `app/cli`/`app/db`, consistent with the ticket's explicit instruction not to add a logger to `ActionContext`.

**Tests**: `app/logging.ts` + sidecar `app/logging.test.ts` (8 unit tests: cookie redaction incl. an `=`-bearing non-redacted value, request-id fallback/first-of-array, `loggingOptions` wiring). `app/test/log-lines.ts` (new, small: `captureLogLines()` — a pino `DestinationStream` that keeps every line written to it, parsed as JSON, for a test to assert on) used by every test below. `app/app.test.ts`: 3 new tests — a request line carries an incoming `x-request-id`, one is generated when absent, and identity/flash cookies are redacted in the request log while an unrelated cookie passes through in cleartext. One new/extended test per business-event route (order-payments, admin payouts, sign-in-routes, auth/index ×3, moderation ×1 covering all four writes) asserting the event and its fields are on the captured stream. `run-payouts.test.ts` and `drain-outbox.test.ts` rewritten off the old `console.log` monkey-patch onto `captureLogLines()`/`createCliLogger()`, plus one new failure-path test per CLI (an unmigrated database triggers the `catch`, asserting `process.exitCode === 1` and an `err` field on the logged line, with `process.exitCode` restored in `t.after`) — `migrate.ts`/`seed.ts` got no new sidecar tests (none existed before; verified by hand instead, see above).

Ran `npm run check` (typecheck, then lint, then `--experimental-test-coverage` suite) clean: exit 0, 1492 tests / 1492 pass / 0 fail, coverage 99.57% lines / 96.60% branches / 99.55% functions (gate is 95/90) — `app/logging.ts` and `app/app.ts` both 100% lines. The shared tree had other tickets landing concurrently (RFCTR-003, FEAT-016) during this work; two transient failures appeared mid-run at one point (`listing-status.test.ts`, `listing-stock.test.ts`, `decline-reason.test.ts` — all outside this ticket's files) and were gone on the next run once that concurrent edit settled — not something this ticket touched or needed to fix. No lint rule disabled to get green; the one lingering lint failure seen during this work (`seller/listing-form.ts` complexity) was RFCTR-003's WIP file, outside territory, and resolved on its own before the final `npm run check`.

Files changed: `app/logging.ts` (new), `app/logging.test.ts` (new), `app/test/log-lines.ts` (new), `app/app.ts` (2 anchored edits), `app/test/build-test-app.ts` (1 line), `app/app.test.ts`, `src/eslint.config.js`, `package.json`, `package-lock.json`, `app/sites/shop/routes/order-payments.ts` + `.test.ts`, `app/sites/admin/routes/payouts.ts` + `.test.ts`, `app/sites/auth/sign-in-routes.ts` + `.test.ts`, `app/sites/auth/index.ts` + `.test.ts`, `app/sites/admin/routes/moderation.ts` + `.test.ts`, `app/cli/run-payouts.ts` + `.test.ts`, `app/cli/drain-outbox.ts` + `.test.ts`, `app/db/migrate.ts`, `app/db/seed.ts`.
