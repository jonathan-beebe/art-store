---
id: BUG-009
type: bug
status: resolved
created: 2026-08-25
---

# BUG-009: aborted requests close their story

## Problem

A request the client aborts never closes its log story.
`app/plugins/request-log.ts` writes the closing `did` line in an `onResponse`
hook, and Fastify does not run `onResponse` for a request whose socket the
client tore down; no `onRequestAbort` hook is registered. The SSE unread
streams (`GET <site>/events`, `app/plugins/events.ts`) end by client
disconnect in normal use — every navigation and closed tab — so their
stories log `will` (🎬) and nothing more. Observed 2026-08-26 UTC in the
dev log: request `23ca67d1` opened `GET /seller/events` at 02:11:30.068, the
browser navigated away at 02:11:30.616, and no closing line ever followed;
each navigation leaves another one. `docs/alignment.md` §2.2 requires
exactly one of `did` / `refused` / `failed` to close what `will` opened.

## Goal

Every request story closes, however the connection ends.

## Outcome

A client-aborted request logs a closing line carrying its duration and a
marker that distinguishes a disconnect from a completed response, under the
same `request_id`. An SSE stream abandoned by navigation reads as a closed
story in the log. A test pins it by aborting an in-flight request and
asserting the closing line.

## Why it matters

The story rule breaks constantly in normal operation — one forever-open
story per page navigation — and the FEAT-021 story view would render every
one of them as still in flight. An unclosed 🎬 is indistinguishable from a
hung request, which is exactly the signal the story exists to give.

## Discovery notes

Advisory: Fastify's `onRequestAbort` hook exists for this case; closing from
there with `did`, the status that was streaming, `duration_ms`, and
something like `data.disconnected: true` keeps the §2.1 payload (data keys
are free-form, so no contract change). Verify red-first that `onResponse`
really does not fire on abort in our Fastify version, and guard so a story
closes exactly once whichever hook runs first (the `loggedFailure` flag is
the existing shape for that). PHP and Rails likely share the gap; a §2.2
note on long-lived requests can ride the next alignment pass rather than
this ticket.

## Working

- 2026-08-25 — re-validated: `request-log.ts` registers `onRequest` (will) and
  `onResponse` (did) only; no `onRequestAbort`. `events.ts` streams SSE until
  `request.raw` closes, so a navigation aborts the request mid-response.
- Red first: a test opens `GET /seller/events` over a real socket via
  `buildLoggedTestApp` + `app.listen`, reads the first frame, aborts, and
  asserts a `did` line with `data.status`, `data.disconnected: true`, and a
  sane `duration_ms` under the will line's `request_id`.
- Fix shape: `onRequestAbort` hook closing the story with `did`; a single
  closed-once guard shared with `onResponse` and `logRequestFailure` so
  whichever hook runs first closes the story exactly once; asset paths stay
  excluded. §2.1 payload unchanged (`data` keys are free-form).
- Red confirmed: after aborting the SSE fetch, a 2s poll found zero closing
  lines for the request's `request_id` — `onResponse` does not fire on abort
  in Fastify 5.12.1. Fastify wires `onRequestAbort` to `req.on('close')`
  guarded by `req.aborted` (`lib/route.js:572`).
- Resolved: `request-log.ts` renames `loggedFailure` → `storyClosed` (checked
  and set by `onResponse`, `onRequestAbort`, and `logRequestFailure`), stamps
  `storyStartedAt` in `onRequest` for the abort-path duration (the abort hook
  receives no `reply`), and captures `sentStatus` in an `onSend` hook so the
  abort closer reports the status that was streaming. The aborted request
  logs `did` with `data: { status, disconnected: true }` and `duration_ms`.
  Test pins it: abort a live `GET /seller/events` fetch, exactly one closing
  line under the will line's `request_id`. 2084 tests green, `make check`
  green at 99.33/95.70 coverage.
- Noted for a later pass: the aborted `did` carries the 🟢 prefix like any
  completed response — `data.disconnected` is the marker. The three closers
  share a repeated guard-set-log shape; a single close-once helper is a
  refactor candidate.

## Related work

- bae9212 (IMPRV-023) — the story emoji this leaves dangling
- 2d44906, b93c450 — §2.2's exactly-one-close rule
