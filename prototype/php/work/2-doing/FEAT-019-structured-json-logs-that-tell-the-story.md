---
id: FEAT-019
type: feature
status: open
created: 2026-08-23
---

# FEAT-019: Structured JSON logs that tell the story

## Problem
The PHP prototype makes zero `Log::` calls; the stock Laravel `single` channel writes prose lines with no request id, no session id, no actor, and no unit-of-work id. `docs/alignment.md` §2 fixes the JSON payload, the `will`/`doing`/`did`/`refused`/`failed` phases, and the event vocabulary all three prototypes emit.

## Goal
Reading the log for one request or one `txn_id` tells what was about to happen, what happened, and why it stopped, in the payload every prototype shares.

## Outcome
Every log line is one JSON object on stdout with the §2.1 fields; `X-Request-Id` is echoed; a `sid` cookie is minted on the first response; every domain action logs `will` then `did`/`refused`/`failed` under one `txn_id`; every §2.3 event the PHP prototype supports is emitted; cookie values, tokens, card numbers, and email addresses never appear; a test asserts the payload shape of a request + action and one test reads a captured log for the checkout story in order; `docs/architecture.md` gains a Logging section.

## Why it matters
The user is in rapid development: the log is the primary debugging surface, and a line without a session or actor cannot be joined to the lines around it.

## Discovery notes
Idiomatic Laravel: a `stdout`/`stderr` channel with a custom Monolog formatter for the payload, `Log::withContext()` from one middleware for `request_id`/`session_id`/`actor_*`, and a small `Story` (or `Journal`) helper that domain actions call as `will()`/`did()`/`refused()`/`failed()` and that mints `txn_id` around `DB::transaction`. The existing `MessagePosted`-style events can carry the domain lines.

## Related work
- docs/alignment.md §2
- RFCTR-007 (events)
