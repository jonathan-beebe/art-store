---
id: BUG-009
type: bug
status: open
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

## Related work

- bae9212 (IMPRV-023) — the story emoji this leaves dangling
- 2d44906, b93c450 — §2.2's exactly-one-close rule
