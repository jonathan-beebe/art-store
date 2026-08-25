---
id: IMPRV-015
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-015: Rendered pages reuse compiled templates

## Problem
`app.ts:121` registers `@fastify/view` as `{ engine: { ejs }, root: APP_ROOT, viewExt: 'ejs' }` — no `production`, no `options.cache`, no `maxCache`. Three consequences:

1. EJS `include()` resolves at render time through `handleCache` with `cache` unset, so every include does `fs.readFileSync` plus a full `ejs.compile` per call, per render — even in production (the runtime image sets `NODE_ENV=production`, which only engages @fastify/view's page-level LRU, not EJS's include cache). The shop layout carries 5 includes and `home.ejs:41` includes `listing-card` once per listing — a storefront home render is ~17 synchronous file reads and compiles on the event loop.
2. @fastify/view's LRU defaults to 100 entries while each rendered template consumes two keys; 71 `.ejs` files across ~60 pages plus 4 layouts puts steady-state at ~128+ keys, so full-site traffic evicts continuously and even the production page cache degrades to read-and-compile.
3. The test container sets no `NODE_ENV`, so all ~680 `inject` calls in the suite recompile layout + page + partials from scratch every render.

Development relies on re-reading: `node --watch` does not watch `.ejs` files, so live template editing needs the cache off there.

## Goal
Template compilation happens once per process everywhere except development.

## Outcome
In production and under test, rendering the same page a second time performs no template file reads and no compiles; development keeps live template editing; pages render byte-identical to today.

## Why it matters
Every HTML response on all three sites pays repeated synchronous file I/O and compiler runs for templates that never change mid-process. It is the single largest per-render CPU cost found, it blocks the event loop, and the same waste is a measurable slice of the 680-render test suite.

## Discovery notes
- The knobs are all on the one registration line: `production` / `options.cache` keyed off the existing `AppConfig.environment` (so tests count as cached, dev does not), and `maxCache` sized comfortably above the app's key count.
- EJS keys its own module-level include cache by filename; enabling `cache` engages it.

## Related work
- MAINT-004 / MAINT-005 (test wall-clock; this ticket removes the per-render compile slice)

## Working
- 2026-08-25: Re-validated against `app.ts:121` on `node/performance` — the registration is still `{ engine: { ejs }, root: APP_ROOT, viewExt: 'ejs' }` with no `production`, `maxCache`, or `options.cache`.
- `@fastify/view` 12.0.0: `production` defaults from `process.env.NODE_ENV`; the LRU (`toad-cache` LruMap) defaults to 100 entries via `opts.maxCache || 100`; `options` flows into `ejs.compile` as the compile options, so `options.cache: true` is what includes inherit and what engages `ejs.cache` in `handleCache`.
- ejs 6.0.1 (ESM build, `lib/esm/ejs.js`): includes read files through the overridable `ejs.fileLoader` (`ejs.fileLoader = fs.readFileSync`), which gives a test a seam to count template file reads without mocking `node:fs`.
- 71 `.ejs` files, two LRU keys per rendered template → steady state ~142+ keys; `maxCache: 500` sits comfortably above it.
- Plan: key `production` and `options.cache` off `config.environment !== 'development'` so production and test cache while development keeps re-reading for live editing (`node --watch` does not watch `.ejs`).
- Tests: in `app.test.ts` conventions — render a page twice in a test-environment app and assert the second render performs zero `.ejs` reads through `ejs.fileLoader`; render twice in a development-environment app and assert the second render still reads; assert repeated renders are byte-identical.
- Landed: `app.ts` registers `@fastify/view` with `production: cachesTemplates`, `maxCache: 500`, `options: { cache: cachesTemplates }` where `cachesTemplates = config.environment !== 'development'`. Three tests in `app.test.ts`: second render performs zero include reads (test env), development re-reads every render, repeated renders are byte-identical. TDD order held — the zero-reads test failed (4 reads) before the registration change.
- Validation pass confirmed: `opts.production` fully overrides the `NODE_ENV` fallback in @fastify/view 12.0.0; the `prod` flag gates only cache-lookup branches, so headers/output/error paths are untouched; ejs `handleCache` calls `fileLoader` unconditionally when `cache` is falsy, so development re-reads even after a cached render warmed `ejs.cache` in the same process; `ejs.fileLoader` restoration runs via `t.after` even on assertion failure.
- `make check` green: 1932 tests (baseline 1929 + 3), coverage 99.43% lines / 95.88% branches / 99.50% functions (branch delta is rounding against the 95.89% baseline).
