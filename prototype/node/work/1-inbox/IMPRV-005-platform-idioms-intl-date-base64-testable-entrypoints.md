---
id: IMPRV-005
type: improvement
status: open
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
