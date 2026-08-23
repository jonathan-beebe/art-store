---
id: IMPRV-003
type: improvement
status: open
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
