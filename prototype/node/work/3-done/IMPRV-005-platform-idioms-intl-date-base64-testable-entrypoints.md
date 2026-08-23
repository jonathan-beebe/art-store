---
id: IMPRV-005
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-005: Platform idioms: Intl formatting, one date formatter, web-standard base64, testable entrypoints

## Problem
`app/sites/seller/format.ts:3-39` hand-rolls date formatting with a twelve-entry `MONTH_ABBREVIATIONS` table and manual 12-hour clock arithmetic, justified by a comment claiming it "reads no `Intl` locale data, so it renders the same in every environment." That premise is false on the Node in use: `process.config.variables.icu_small === false` on Node 24.12, and `Intl.NumberFormat.supportedLocalesOf(['de','ja'])` returns both. `app/core/shop/day-label.ts:3-8` already uses `Intl.DateTimeFormat` with an explicit locale and `timeZone: 'UTC'`, three directories away from the duplicate. A third formatter, `formatMoment` in `app/sites/admin/page.ts:21-23`, does its own string slicing. Three implementations of "an instant as a person reads it" exist across three layers.

`app/core/money.ts:44-57` duplicates `Intl.NumberFormat` by hand: `withThousandsSeparators` uses `wholeDollars.replace(/\B(?=(\d{3})+(?!\d))/g, ',')`, and `formatCents` assembles sign, dollars, and padded cents manually. `new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })` matches the existing output on every case in the test range (`0 → "$0.00"`, `5 → "$0.05"`, `1234567 → "$12,345.67"`, `-4800 → "-$48.00"`).

`app/sites/seller/routes/messages.ts:20` copies an array to read one element: `const lastFromSeller = [...thread.messages].reverse().find((message) => message.isMine)`, where `Array.prototype.findLast` has shipped since Node 18.

`app/sites/seller/test-fixtures.ts:69` generates a unique value with `` `buyer-${forSale.id}-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com` `` — the only `Math.random()` call in the tree, in a file inside the production typecheck and lint surface.

`app/core/listings/placeholder-image.ts:54` encodes base64 through `Buffer.from(placeholderImageSvg(title), 'utf8').toString('base64')`, a Node-only API, inside `app/core/**`, which the doctrine wants free of environment coupling. Node 24 ships the TC39 base64 methods (`Uint8Array.prototype.toBase64`).

`app/server.ts`, `app/db/migrate.ts`, `app/db/seed.ts`, and `app/cli/run-payouts.ts` are top-level side-effecting modules with no exported entry point. None appears in the coverage report because none can be imported without running. `migrate.ts:7` also parses `--fresh` with `process.argv.includes('--fresh')`, which matches `--fresh` appearing as another flag's value, and `app/cli/parse-as-of.ts:9` scans argv by hand rather than using `node:util.parseArgs`.

A test fixture at `app/db/seed-page-views.ts:36` casts `Object.entries(PATH_PATTERNS_BY_SITE) as [PageViewSite, readonly string[]][]` to recover key types `Object.entries` throws away.

## Goal
Every place that formats a date, a currency amount, a unique id, or base64 uses the platform primitive already used correctly elsewhere in the tree, and every CLI entrypoint is importable and testable without running it.

## Outcome
- One core formatting module (`dayLabel`, `dateLabel`, `dateTimeLabel`) built on `Intl` with explicit locale and UTC, used by all three sites.
- `formatCents` uses `Intl.NumberFormat`.
- `MONTH_ABBREVIATIONS` and `formatMoment` are gone.
- The placeholder SVG encodes base64 via `TextEncoder`/`toBase64()`.
- `server`, `migrate`, `seed`, and `run-payouts` each export `main(argv, env)` with a test for `run-payouts`.
- Fixtures use the injected clock and `randomUUID` instead of `Date.now()`/`Math.random()`.

## Why it matters
The doctrine states "Platform wins: prefer what ships in Node 24" — `Intl`, `Array.prototype.findLast`, `node:crypto`'s `randomUUID`, the TC39 base64 methods, and `node:util.parseArgs` are all named directly. The same job done three different ways in one tree (date formatting) is duplication the doctrine's "abstraction only for duplication felt three times" rule already flags as past threshold. `app/core/**` is meant to stay free of environment coupling; a Node-only `Buffer` call inside core is a second such coupling alongside the existing `node:zlib` import. A `Math.random()`/`Date.now()` fixture inside an otherwise frozen-clock, deterministic test suite breaks the reproducibility the rest of the suite relies on — a failing run cannot be reproduced from the same seed. CLI entrypoints that cannot be imported without running are the one part of the shell the test suite structurally cannot reach through a real entry point, which is what "shell gets integration tests" requires.

## Discovery notes
- Replace `formatDate`/`formatDay` with module-level `Intl.DateTimeFormat` instances built once, the way `day-label.ts` already does, and delete `MONTH_ABBREVIATIONS`. `formatDateTime`'s current output differs from `Intl`'s default in comma placement and meridiem case — keep it hand-composed via `formatToParts` if that distinction matters, or accept `Intl`'s rendering and update the tests.
- Consolidate `dayLabel`, `dateLabel`, `dateTimeLabel`, and `dayFromReportKey` into one core module (extend `app/core/shop/day-label.ts` or add `app/core/presentation/`); have all three sites consume it.
- Swap `formatCents`'s body for a module-level `Intl.NumberFormat` and delete `withThousandsSeparators`. Keep `assertIntegerAmount` — it is the invariant, not the formatting. `dollarsInputValue` stays hand-built since it deliberately emits no separators or symbol for `<input value>`.
- `thread.messages.findLast((message) => message.isMine)` replaces the reverse-then-find.
- `` `buyer-${forSale.id}-${randomUUID()}@example.com` `` from `node:crypto` replaces the `Math.random()`/`Date.now()` fixture; `commerce-world.ts:71`'s monotonic `uniqueSuffix` counter is the pattern already used elsewhere for the same uniqueness problem.
- Swap the placeholder SVG's base64 encoding to `TextEncoder` + `toBase64()`. The `crc32` import from `node:zlib` in the same file is a separate, smaller question — deterministic and used only as a palette seed, so leave it with a comment or inline a small hash; not worth churn on its own.
- Give each CLI a `main(argv, env)` export and a three-line `await main(process.argv, process.env)` at the bottom of the file; a test can then call `main` with a temp DB path. `run-payouts.ts` is the one worth testing directly — it is the only path exercising `parseAsOf` + `runWeeklyPayout` + `payoutPeriodLabel` together.
- Replace the hand-rolled `--fresh` and `--as-of` argv scans with `node:util.parseArgs` (`{ 'as-of': { type: 'string' }, fresh: { type: 'boolean' } }`, `strict: true`), keeping `parseAsOf`'s existing `(argv, fallback) => Date` signature so its sidecar test still applies; add cases for the space-separated form and an unknown flag.
- Fix the seed-page-views cast by iterating `PAGE_VIEW_SITES`, which already exists as an `as const` array, instead of `Object.entries(...) as [...]`.

Files expected to touch: `app/sites/seller/format.ts`, `app/core/shop/day-label.ts` (or a new `app/core/presentation/` module), `app/sites/admin/page.ts`, `app/core/money.ts`, `app/sites/seller/routes/messages.ts`, `app/sites/seller/test-fixtures.ts`, `app/core/listings/placeholder-image.ts`, `app/server.ts`, `app/db/migrate.ts`, `app/db/seed.ts`, `app/cli/run-payouts.ts`, `app/cli/parse-as-of.ts` + its test, `app/db/seed-page-views.ts`.

No hard ordering dependency against the other tickets in this manifest.

## Related work
- 01-deps-platform.md: "Date formatting hand-rolled against a stale justification", "Currency formatting duplicates `Intl.NumberFormat`", "`[...].reverse().find()` where `findLast` exists", "`Math.random()` for a unique value", "Base64 via `Buffer`", "Argument parsing hand-rolled where `node:util.parseArgs` exists"
- 03-core-shell.md: "Three date formatters across three layers"
- 06-tests-views.md: "Four entrypoint scripts are executed by nothing", "A fixture uses `Date.now()` and `Math.random()` inside an otherwise frozen-clock suite"
- 02-types-boundaries.md: "Two casts on `JSON.parse` results in tests" (adjacent cast list; the `seed-page-views.ts` cast noted above is the one item from that finding absorbed here)

## Working

Scope actually taken: this worker's assigned territory was `app/core/money.ts`, `app/core/shop/day-label.ts`, `app/sites/seller/format.ts`, `app/sites/admin/page.ts`, `app/core/listings/placeholder-image.ts`, `app/sites/seller/test-fixtures.ts`, `app/server.ts`, `app/db/migrate.ts`, `app/db/seed.ts`, `app/cli/run-payouts.ts`. The ticket also names `app/sites/seller/routes/messages.ts` (findLast), `app/cli/parse-as-of.ts` (parseArgs), and `app/db/seed-page-views.ts` (cast) — none of those are in this worker's territory (routes are explicitly out of bounds, and the other two were assigned elsewhere), so they were left untouched. `app/cli/parse-as-of.ts` already uses `node:util.parseArgs` and `app/db/migrate.ts` already used `parseArgs` for `--fresh` before this pass — that part of the Discovery notes no longer applied.

**Date/time formatting.** Extended `app/core/shop/day-label.ts` in place (kept the file at its existing path rather than moving it to a new `core/format/` module, since the move would require editing `app/sites/shop/shop-page.ts`'s import and that file is outside this territory) with four module-level `Intl.DateTimeFormat` instances and: `dateLabel` (`Aug 9, 2026`), `dateTimeLabel` (`Aug 9, 2026 3:04pm`), `timestampLabel` (`2026-08-24 12:00`, replaces `formatMoment`'s hand slicing), `dayFromReportKey` (`Aug 9`, replaces `formatDay`'s table lookup). `dayLabel` (en-GB, `24 August 2026`, used by the storefront) is unchanged.

**`dateTimeLabel` decision:** `Intl`'s own `format()` for this combination renders `Aug 9, 2026, 3:04 PM` (extra comma, uppercase, space before meridiem), which does not match the existing seller-portal rendering. Rather than accept that and update the tests, `dateTimeLabel` composes its output from `formatToParts()` (month/day/year/hour/minute/dayPeriod, lowercased) to keep the exact prior string. This still removes the hand-rolled 12-hour arithmetic and `MONTH_ABBREVIATIONS` table — only the final join is manual, and it draws every part from `Intl`, not from `getUTCHours()`/`getUTCMonth()`. `app/sites/seller/format.test.ts`'s existing assertions (`'Aug 9, 2026 3:04pm'`, `'12:00am'`, `'12:00pm'`) pass unchanged, confirming the output is byte-identical.

`app/sites/seller/format.ts` and `app/sites/admin/page.ts`'s `formatMoment` are now thin wrappers/re-exports over the core module — `formatDay`/`formatDate`/`formatDateTime`/`formatMoment` keep their exact names and signatures, so none of the route files that import them needed to change.

**`formatCents`.** Replaced the hand-rolled sign/dollars/cents assembly and `withThousandsSeparators` with one module-level `Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })`. Verified byte-identical output against every existing `money.test.ts` case (`0 → "$0.00"`, `5 → "$0.05"`, `1234567 → "$12,345.67"`, `100_000_000 → "$1,000,000.00"`, `-1234 → "-$12.34"`) before and after — all pass unchanged. `dollarsInputValue` and `assertIntegerAmount` are untouched, as scoped.

**Base64 in `placeholder-image.ts`: left unchanged.** Checked `Uint8Array.prototype.toBase64` on the installed Node (`v24.12.0`) with `node -e`: it throws `TypeError: toBase64 is not a function`. `node --v8-options` confirms it sits behind `--js-base-64`, described as "in progress / experimental" — not on by default. Per the brief's explicit instruction, kept `Buffer.from(...).toString('base64')` as-is rather than hand-rolling a base64 encoder or requiring a V8 flag no npm script sets. Revisit when the Node version in use ships the method unflagged.

**`test-fixtures.ts`.** Replaced `` `buyer-${forSale.id}-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com` `` with `` `buyer-${forSale.id}-${randomUUID()}@example.com` `` (`node:crypto`), matching the pattern already used in `app/sites/seller/listing-image-upload.ts`. No dedicated test file exists for this fixture module; correctness is exercised transitively by every route test that calls `createFulfillment`.

**CLI entrypoints.** `app/server.ts`, `app/db/migrate.ts`, `app/db/seed.ts`, `app/cli/run-payouts.ts` each now export `async function main(argv: readonly string[], env: NodeJS.ProcessEnv): Promise<void>` holding all the previous top-level logic, followed by a guarded call: `if (process.argv[1] === fileURLToPath(import.meta.url)) { await main(process.argv, process.env) }`. `npm run start`/`migrate`/`fresh`/`seed`/`payouts` in `package.json` still invoke the same files directly, so they still run `main` the same way; `package.json` was not touched (another worker owns its scripts section right now). Added `app/cli/run-payouts.test.ts`: builds a real temp-file SQLite database (`mkdtemp` + `migrateToLatest`) with a seller/customer/listing/order taken through `finalizeOrder` → `markShipped` → `confirmDelivered` (via `commerce-world.ts` helpers and fixed clocks), closes that setup connection, then calls `main(['node', 'run-payouts.ts', '--as-of=2026-08-24'], { DATABASE_FILE: <temp file> })` and asserts on captured `console.log` output (period label, `seller <id> $405.00`, `1 seller(s) paid.`), plus a second case asserting the no-payout message on an empty database. `server.ts`/`migrate.ts`/`seed.ts` got no new tests — not required by the ticket's Outcome, which names only `run-payouts`.

**Verification.** `npm run lint` (whole `app/`): clean. Targeted `npx eslint` on every file this ticket touched: clean. `npm run typecheck`: fails, but only on `app/actions/messaging/conversation-actor.test.ts` and `app/plugins/unread-messages.test.ts` — both outside this territory and mid-edit by a concurrent worker on RFCTR-002 (confirmed via `git status`, which shows those files modified but uncommitted). None of this worker's files appear in the typecheck output. `npm run coverage` (full suite): 1302 tests, all passing on a clean run (a `seed-sellers` shop-name test flaked once under concurrent load in an earlier run, passed on rerun — unrelated to this ticket's files), coverage 99.67%/95.48%/99.80% lines/branches/functions against the 95%/90% thresholds. Targeted test run before/after: `money.test.ts`, `day-label.test.ts`, `format.test.ts`, `page.test.ts`, `placeholder-image.test.ts` — 62 tests, all passing; `run-payouts.test.ts` is new, 2 tests, both passing. Full suite before this ticket's changes was not captured in isolation (other workers were already mid-flight in the same tree when this ticket started), but the full run above (1302 passing, 0 failing on the clean run) is after.

Left alone, deliberately: `app/sites/seller/routes/messages.ts` (`findLast`), `app/cli/parse-as-of.ts` (already uses `parseArgs`; test cases for space-separated form / unknown flag not added — out of territory), `app/db/seed-page-views.ts` (cast) — all outside this worker's assigned territory. `app/core/listings/placeholder-image.ts`'s base64 encoding — Node 24.12 doesn't ship `Uint8Array.prototype.toBase64` unflagged (see above).
