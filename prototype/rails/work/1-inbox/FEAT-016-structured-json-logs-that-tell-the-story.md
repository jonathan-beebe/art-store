---
id: FEAT-016
type: feature
status: open
created: 2026-08-23
---

# FEAT-016: Structured JSON logs that tell the story

## Problem
The Rails prototype logs stock Rails prose (`request_id` as a tag in production only); there is no session id independent of sign-in, no actor on request lines, no unit-of-work id, and no domain events at all. `docs/alignment.md` §2 fixes the JSON payload, the `will`/`doing`/`did`/`refused`/`failed` phases, and the event vocabulary all three prototypes emit.

## Goal
Reading the log for one request or one `txn_id` tells what was about to happen, what happened, and why it stopped, in the payload every prototype shares.

## Outcome
Every log line is one JSON object on stdout with the §2.1 fields; `X-Request-Id` is echoed; a `sid` cookie is minted on the first response; every model/action that writes logs `will` then `did`/`refused`/`failed` under one `txn_id`; every §2.3 event the Rails prototype supports is emitted; cookie values, tokens, card numbers, and email addresses never appear; a test asserts the payload shape of a request + action and one test reads a captured log for the checkout story in order; `docs/architecture.md` gains a Logging section.

## Why it matters
The user is in rapid development: the log is the primary debugging surface, and a line without a session or actor cannot be joined to the lines around it.

## Discovery notes
Vanilla Rails: a custom `ActiveSupport::Logger` formatter emitting the JSON payload, `config.log_tags` replaced by a `CurrentAttributes` (`Current.request_id`, `Current.session_id`, `Current.actor`) that the formatter reads, `ActiveSupport::Notifications` for the request will/did lines, and a small `Story` module models call around `transaction do` that mints `txn_id`. Keep the default Rails request logging off (`config.rails_semantic_logger`-style silence is not needed; `Rails::Rack::Logger` can be muted).

## Related work
- docs/alignment.md §2
