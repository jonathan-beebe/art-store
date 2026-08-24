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
