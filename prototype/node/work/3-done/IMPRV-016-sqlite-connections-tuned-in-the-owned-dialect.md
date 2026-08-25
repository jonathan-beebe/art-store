---
id: IMPRV-016
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-016: SQLite connections tuned in the owned dialect

## Problem
`NodeSqliteDriver.init()` (`app/db/node-sqlite-dialect.ts:61-72`) sets only `foreign_keys = ON` and `busy_timeout = 5000`. `journal_mode = WAL` is persistent (set once by migration `20260822000001-enable-write-ahead-logging.ts`), but `synchronous` is per-connection and stays at its FULL default — so every autocommit write (the page-view upsert per HTML GET, the rate-limit upsert per guarded POST, each outbox stamp) pays a full fsync. SQLite's documented recommended pairing under WAL is `synchronous = NORMAL`, which never risks corruption and at worst loses the last commit on power loss.

Separately, `executeQuery` (`node-sqlite-dialect.ts:113-137`) calls `this.#database.prepare(compiledQuery.sql)` on every execution, so SQLite re-parses and re-plans identical SQL each time — the same fixture inserts and hot queries run thousands of times across the suite and repeatedly per request in production.

## Goal
Every connection gets SQLite's recommended WAL configuration and stops re-parsing SQL it has already prepared.

## Outcome
Commits on the hot write paths no longer fsync per statement beyond NORMAL's guarantee; executing the same SQL text twice on a connection reuses its prepared statement; all behavior is unchanged and the suite is green.

## Why it matters
The fsync-per-write tax lands on every page view (page-view upsert) and every guarded POST; the per-execution prepare tax lands on everything, tests included. Both fixes live entirely inside the dialect the repo already owns (FEAT-012), invisible to every caller.

## Discovery notes
- `PRAGMA synchronous = NORMAL` belongs beside the existing two pragmas in `init()`, with the same comment discipline stating the durability trade.
- A small bounded `Map<sql, StatementSync>` on the connection, cleared in `destroy()`, keeps statement reuse inside the driver.
- `cache_size` / `mmap_size` are also per-connection and unset; optional, measure before bothering.

## Related work
- FEAT-012 (node:sqlite behind an owned Kysely dialect)

## Working

- 2026-08-25 re-validated: `init()` (node-sqlite-dialect.ts:61-72) still sets only
  `foreign_keys` and `busy_timeout`; `executeQuery` (:113-137) and `streamQuery`
  (:131-137) still call `prepare()` per execution.
- Plan: add `PRAGMA synchronous = NORMAL` in `init()` beside the existing pragmas,
  comment stating the WAL+NORMAL durability trade. Cache prepared statements in
  `NodeSqliteConnection` as a bounded `Map<sql, StatementSync>` with FIFO eviction
  (Map insertion order), cleared by the driver's `destroy()`.
- `executeQuery` completes each statement synchronously (`all()`/`run()`), so a
  cached statement is never mid-iteration when the same SQL runs again. `streamQuery`
  iterates lazily across awaits — a shared statement there could be reset mid-stream
  by a second execution of the same SQL, so streams keep preparing fresh statements.
- Cache lives per connection; each Kysely instance builds its own driver/connection,
  so :memory: test databases cannot share statements.
- `cache_size` / `mmap_size` skipped: no measurement showing parse/plan or page-cache
  pressure beyond what the statement cache addresses.
- 2026-08-25 resolved: `init()` sets `synchronous = NORMAL` beside the existing
  pragmas with the WAL durability trade documented. `NodeSqliteConnection` caches
  prepared statements in a `Map<string, StatementSync>` bounded at
  `STATEMENT_CACHE_LIMIT` (100) with FIFO eviction; `destroy()` clears the cache
  before closing the handle. `streamQuery` prepares fresh (mid-stream reset risk).
  Six tests added: pragma value, repeat-execution results, schema-change
  re-execution, eviction boundary, cross-connection isolation, destroy/reopen.
  1938 tests green (baseline 1932); coverage 99.43/95.89/99.50.
