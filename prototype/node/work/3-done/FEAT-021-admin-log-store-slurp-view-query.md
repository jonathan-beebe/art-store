---
id: FEAT-021
type: feature
status: done
created: 2026-08-25
---

# FEAT-021: admin log store — slurp, view, query

## Problem

Every log line the node prototype writes is one JSON object on stdout
(`docs/alignment.md` §2) and nothing else. Reading the story means shell
access — `make logs` piped through `jq` — and the lines live only as long as
the container's log buffer. The admins (the founders, per
`docs/ontology.md`) have no way to read, view, or query the log story, and
the story the error-round built (🎬 → 🟢/⚠️/🛑 → ❌ under one `request_id`)
is invisible to anyone off the terminal.

## Goal

An admin reads, views, and queries the log story from the admin site.

## Outcome

Every log line the prototype writes — server and CLI runs alike — is stored
in a queryable SQLite store, and stdout stays exactly as §2 specifies. The
admin site gains a logs view: filterable by request, transaction, session,
actor, event, level, phase, and time range, with a story view that renders
one request's lines in order. Stored lines are pruned on a retention window.
A formal definition of the log store — payload-to-table mapping, ingest
semantics, retention — is added to the docs first, and the alignment doc
then incorporates it, so the other prototypes can adopt the same store.

## Why it matters

The error-story round made the log the debugging narrative; a narrative only
the terminal can read stops at the two founders' laptops. Persisting and
rendering it makes the admin site the place where "what happened to this
order?" gets answered, and the formal definition keeps the three prototypes
comparable the way §2 already does for the lines themselves.

## Discovery notes

Delivery constraint from the human: one ticket, three phases, one commit per
phase — (1) ingest and schema, (2) admin viewer, (3) retention — and the
formal definition document lands before the alignment amendment does.

Advisory design, from the scoping conversation: an in-process second pino
destination (worker-thread transport) parsing each line into a WAL-mode
`logs.db` separate from the commerce DB, so `make fresh` and the app
migrator stay untouched and every logging process (server, CLI) feeds the
same store; batched inserts so the request path never waits. Table columns
mirror §2.1 (`ts`, `level`, `event`, `phase`, `msg`, `request_id`,
`session_id`, `actor_type`, `actor_id`, `txn_id`, `duration_ms`) with `data`
and `error` as JSON text plus the raw line; indexes on `ts`, `request_id`,
`txn_id`, `event`. Retention can follow the sweep CLI's
rate-limit-window-prune pattern. In production the store's home is the
Render volume. `docs/alignment.md` §5 fixes the admin feature set, which is
why the amendment is part of the outcome; php/rails adoption is follow-up
work outside this ticket.

## Design (settled 2026-08-26)

The formal definition is `docs/log-store.md` (landed with phase 1); it is
the spec the phases implement. Decisions ratified by the human: the store
is a separate SQLite file. Decisions settled by the design round, recorded
in the doc: ensure-on-open schema versioned by `PRAGMA user_version`
(`LOG_DATABASE_FILE`, default `storage/logs.sqlite3`, `off` disables);
one `log_lines` table — rowid PK (stated §1 exception), nullable §2.1
mirror columns + `data`/`error` JSON text + verbatim `raw`, six indexes,
no CHECKs; ingest as a pino destination stream at the two choke points
(`loggingOptions`, `createCliLogger`) — stdout passthrough first,
setImmediate-batched inserts, `process.once('exit', flushSync)`, stderr
for the store's own failures, `debug` stored; viewer at `/admin/logs`
(filters incl. `key`/`value` any-attribute filter via `json_extract` on
`raw`, level stat tiles, 50 rows/page) and `/admin/logs/requests/:requestId`
story view; retention `LOG_RETENTION_DAYS` default 14 pruned in the sweep
CLI in 5000-row batches with `incremental_vacuum`.

## Phases

1. **Ingest and schema** — `app/log-store.ts` (bootstrap DDL, batch
   writer, stream), `app/db/logs-schema.ts`, config
   (`LOG_DATABASE_FILE`), wiring at the two choke points,
   `docs/log-store.md` + README row, tests.
2. **Admin viewer** — dialect accepts an existing `DatabaseSync`, `logsDb`
   decoration + `buildTestApp` support,
   `queries/log-rows.ts`, `routes/logs.ts`, the two views, prefix-link
   helper, nav link, `docs/admin.md` rows, tests.
3. **Retention** — `LOG_RETENTION_DAYS` config, prune in the sweep CLI,
   `docs/alignment.md` §2.5 + §5 rows + §8 changelog line, tests.

One commit per phase.

## Related work

- 2d44906, b93c450 — the §2 contract this store persists
- IMPRV-023..028 (bae9212, 4fbb01f, 098e871, d451322, b3fc42c, d297965) — the error-story round the viewer renders
- docs/alignment.md §2 (payload), §5 (admin feature set)
