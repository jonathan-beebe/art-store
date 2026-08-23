---
id: FEAT-012
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-012: SQLite through `node:sqlite` behind a Kysely dialect owned by the app

## Problem
`better-sqlite3` ^13.0.3 is a native dependency compiled by a toolchain the Dockerfile still installs on a premise no longer true. `Dockerfile:3-12` runs `apt-get install -y g++ make python3` with a comment saying better-sqlite3 falls back to compiling from source. `better-sqlite3` 13.0.3 declares `"gypfile": false`, has no `install`/`postinstall` script, and ships `prebuilds/` for `linux-x64`, `linux-arm64`, and other targets; `lib/binding.js:42-44` resolves `prebuilds/${platform}-${arch}.node` directly, so nothing ever compiles on the `node:24-bookworm-slim` base the image runs on.

The installed types package lags the library: `package.json:35` pins `"@types/better-sqlite3": "^9.6.0"` against `package.json:26`'s `"better-sqlite3": "^13.0.3"`. Anything added to the API in versions 10 through 13 is invisible to `tsc`, and anything changed is typed wrong.

The database connection sets one pragma and relies on an undocumented library default for the rest. `app/db/database.ts:10-20`'s `openDatabase` sets `foreign_keys = ON` and nothing else; the 5000ms busy timeout exists only because better-sqlite3 defaults `timeout: 5000` (`node_modules/better-sqlite3/lib/database.js:33`) — nothing in this codebase states it. Kysely's SQLite driver also emits a plain deferred `begin` (`node_modules/kysely/dist/dialect/sqlite/sqlite-driver.js`). That is harmless inside the server process, which holds one connection behind a mutex, but `app/cli/run-payouts.ts:11` opens a second connection to the same file, and `runWeeklyPayout` reads the whole ledger and the payouts table before inserting (`run-weekly-payout.ts:25-32`). Under WAL, a deferred transaction that upgrades to a write after another process has written returns `SQLITE_BUSY_SNAPSHOT` immediately — the busy handler does not retry that case, so `make payouts` against a live `make up` stack can fail this way.

## Goal
The app runs on the SQLite that ships in the Node 24 runtime, with no compiled native dependency and an explicit, owned connection configuration.

## Outcome
- `better-sqlite3` and its types are gone from `package.json`.
- `app/db` has a small Dialect (a driver over `node:sqlite`'s `DatabaseSync` plus Kysely's `SqliteAdapter`/`SqliteIntrospector`/`SqliteQueryCompiler`) whose transactions begin `IMMEDIATE` and whose `busy_timeout` and `foreign_keys` pragmas are set explicitly.
- The Dockerfile has no compiler layer.
- The experimental warning is disabled with a comment saying why.
- The suite passes unchanged.
- The risk is noted in the README: `node:sqlite` is a release candidate, not stable, and reverting is one file.

## Why it matters
"Platform wins: prefer what ships in Node 24" is a stated doctrine line, and this is the most legible instance of it available — it deletes a native dependency, the `g++`/`make`/`python3` apt layer, and the node-gyp compile from every `npm ci`. `prototype/rails`'s README (`rails/README.md:143-144`) claims "no Node in the image"; `prototype/php` installs Node 20 plus Composer plus Vite. Dropping the compiler toolchain answers "no Node in the image" with "no compiler in the image" — a stronger version of the same claim, on the Node entry itself.

## Discovery notes
Kysely does not depend on `better-sqlite3` — its `SqliteDialectConfig` declares a structural interface: `close()`, `prepare(sql)`, and a statement exposing `reader`, `all(params)`, `run(params)`, `iterate(params)`. `node:sqlite`'s `StatementSync` has `all`/`run`/`iterate` but takes varargs rather than an array, and exposes `columns()` instead of a `reader` flag — so the adapter is a driver object whose `prepare` wraps a `node:sqlite` statement, exposing a `reader` getter computed from `columns().length > 0` and forwarding `all`/`run`/`iterate` with spread arguments. This was verified against the real `kysely@0.29.5` installed in `src/node_modules` run against a `DatabaseSync`: `insertInto().returningAll()` returns a correct `InsertResult` with `insertId` as a bigint; `CamelCasePlugin` maps rows correctly; `db.transaction().execute()` commits and a throw inside it rolls back; `pragma foreign_keys = ON` set via `exec()` enforces (`FOREIGN KEY constraint failed`); `columns().length` correctly classifies an INSERT as non-reader and a SELECT as reader; binding rules match better-sqlite3 where it matters (`true` and `undefined` throw in both).

Two API gaps to know about, neither hit by this codebase today: `node:sqlite` has no `db.pragma()` helper (use `prepare('PRAGMA journal_mode = WAL').get()`; `exec()` discards results and only the `foreign_keys` pragma needs setting today), and no `db.transaction(fn)` wrapper — but Kysely issues its own `begin`/`commit`/`rollback` through `SqliteDriver`, so nothing depends on it.

`node:sqlite` is Stability 1.2 — Release Candidate, not experimental-with-a-flag; the flag requirement went away in v23.4.0. The experimental warning itself was removed from `lib/sqlite.js` in Node **v24.15.0**. Pin the image tag to a concrete Node 24.19-or-later digest rather than floating `node:24`, verify no warning prints in the container, and describe it in the README as "release candidate," not "stable." Reversibility is real: the blast radius is `app/db/database.ts` alone.

Do not take one of the community dialect packages (`kysely-sqlite`, `kysely-node-native-sqlite`, `kysely-node-sqlite`, `kysely-generic-sqlite`) — they run 18 to 7,770 weekly downloads each, trading a 10M-downloads/week native module for a far smaller one is a worse dependency, not a better one. Fourteen owned lines is the point.

While rewriting `openDatabase`, set `busy_timeout` explicitly next to `foreign_keys` rather than relying on the driver default, and start the payout CLI's transaction as `BEGIN IMMEDIATE` (or accept the retry) so a second process reading before writing does not lose its snapshot under WAL.

Files expected to touch: `app/db/database.ts` (rewrite `openDatabase`, roughly 20 lines), `package.json` (drop `better-sqlite3` and `@types/better-sqlite3`), `Dockerfile` (drop the `apt-get install g++ make python3` layer), `app/db/database.test.ts`, `README.md` stack notes, `docs/architecture.md`.

FEAT-013 (production image) depends on this ticket landing first — dropping `better-sqlite3` removes the reason FEAT-013's builder stage would otherwise need a compiler toolchain, so landing this first avoids building and then tearing down that layer.

## Related work
- 07-showcase.md — showcase opportunity #1 (verified adapter shape, ranked highest showcase value)
- 04-data-layer.md — "`busy_timeout` relies on an undocumented library default; `BEGIN` is deferred"
- 01-deps-platform.md — "Native build toolchain installed on a premise that is no longer true" (finding 2)
- 01-deps-platform.md — "`@types/better-sqlite3` is four majors behind" (finding 12)
- FEAT-013 (production image) depends on this ticket

## Working

### Verified against the code
- `Dockerfile:3-11` installed `g++ make python3` on the better-sqlite3 premise; `better-sqlite3@13.0.3` in `src/node_modules` declares `"gypfile": false` and ships `prebuilds/`, so nothing compiled. Confirmed.
- `@types/better-sqlite3` was pinned at `^9.6.0` against the library's 13.0.3. Confirmed.
- `openDatabase` set `foreign_keys` only; the 5000 ms busy timeout came from better-sqlite3's own `timeout` default. Confirmed.
- Kysely 0.29.5's `SqliteDriver` issues a plain deferred `begin`. Confirmed by reading `node_modules/kysely/dist/dialect/sqlite/sqlite-driver.js`.
- WAL is not set by the connection — migration `20260822000001-enable-write-ahead-logging` sets `PRAGMA journal_mode = WAL`, and it persists in the file header. The new dialect leaves it alone; verified `pragma journal_mode` still reads `wal` after a fresh migrate.

### What changed
- `src/app/db/node-sqlite-dialect.ts` (new): `NodeSqliteDialect` implementing Kysely's `Dialect` over `node:sqlite`'s `DatabaseSync`, with Kysely's own `SqliteAdapter`, `SqliteIntrospector`, and `SqliteQueryCompiler`. One connection created in `init`, closed in `destroy`. `PRAGMA foreign_keys = ON` and `PRAGMA busy_timeout = 5000` set on open. `beginTransaction` issues `begin immediate`. `executeQuery` splits reads from writes on `statement.columns().length > 0`, returning `numAffectedRows` and `insertId` as bigints. `streamQuery` uses `iterate`.
- `src/app/db/node-sqlite-dialect.test.ts` (new): 10 tests — select, stream, insert result, insert with returning, transaction commit, transaction rollback, `begin immediate` asserted through Kysely's query log, a second `DatabaseSync` refused the write lock before the transaction reads anything, `foreign_keys`/`busy_timeout` readback, unbindable parameter.
- `src/app/db/database.ts`: `openDatabase` builds `new NodeSqliteDialect(file)`; the pragma and the better-sqlite3 import are gone.
- `src/app/db/database.test.ts`: added one test that an opened database reports a 5000 ms busy timeout. Existing tests unchanged and passing.
- `src/package.json` / `package-lock.json`: `npm uninstall better-sqlite3 @types/better-sqlite3`. Every script that runs app code passes `--disable-warning=ExperimentalWarning`; `test` and `coverage` set it through `NODE_OPTIONS` because the test runner gives each test file its own process, which inherits `NODE_OPTIONS` but not the parent's flags. A top-level `"//"` key carries the comment, since JSON has none.
- `Dockerfile`: the apt layer keeps `ca-certificates` and drops `g++ make python3`; the comment now says why there is nothing to compile.
- `README.md`, `docs/architecture.md`: stack row, layout lines, and the Database section. The README calls `node:sqlite` a release candidate, names the warning, and says reverting is one file.

### Decisions taken
- **No mutex in the driver.** The brief asked for the mutex Kysely's `SqliteDriver` uses; that driver has none in 0.29.5. `RuntimeDriver` owns the `ConnectionMutex` and installs it whenever `adapter.supportsMultipleConnections === false`, which `SqliteAdapter` reports. Reusing `SqliteAdapter` gets the serialization; writing a second lock would duplicate it.
- **Rows are copied into ordinary objects.** `node:sqlite` returns rows with a null prototype where better-sqlite3 returned plain ones. `CamelCasePlugin` rebuilds every row, so the app never saw the difference — but a dialect whose rows behave unlike every other dialect's is a trap for anything reading a row without the plugin, so `asRow` spreads them in the driver.
- **`Dockerfile` `ENV NODE_OPTIONS`.** Territory was the apt layer only, but `docker/entrypoint.sh` and `docker-compose.yml` invoke `node app/…` directly and both are outside it. One `ENV` line covers the entrypoint, the compose command, and every `docker compose run` without restructuring the image, which FEAT-013 still owns.
- **`app/cli/run-payouts.ts` untouched.** `BEGIN IMMEDIATE` now comes from the driver, so every transaction in the process gets it and the CLI needs no special case.
- **Binary parameters narrow on `instanceof Uint8Array`.** The schema has no blob column; `Buffer` is a `Uint8Array`, and `NodeJS.ArrayBufferView` is a union of concrete views that the structural `ArrayBuffer.isView` guard does not satisfy.

### Verification
- `npm run check`: green — typecheck clean, lint clean with no rules disabled, 1206 tests pass.
- `npm run coverage`: green; `node-sqlite-dialect.ts` at 100% lines, 96.97% branches.
- No `ExperimentalWarning` text in any script's output.
- End to end against a temp database file: `npm run migrate`, `npm run seed`, `npm run payouts` all succeed with no warning.

### Test counts
- Before: 1176 tests.
- After: 1206 tests. This ticket adds 11 (10 dialect, 1 database); the rest arrived from other workers in this shared tree.

### Left alone
- Migrations, schema types, and every route: untouched.
- `docker/entrypoint.sh`, `docker-compose.yml`, and the Dockerfile's `CMD`: outside territory; the `ENV NODE_OPTIONS` line covers them.
- The image tag stays `node:24-bookworm-slim`. The discovery note asks for a pinned 24.19-or-later digest; that is FEAT-013's restructure, and `NODE_OPTIONS` keeps the output clean until then.
- Nothing. `npm run check` and `npm run coverage` are both green over the whole tree, including the other workers' concurrent changes.
