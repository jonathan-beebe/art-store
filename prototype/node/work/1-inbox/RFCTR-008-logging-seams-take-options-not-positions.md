---
id: RFCTR-008
type: refactor
status: open
created: 2026-08-25
---

# RFCTR-008: logging seams take options, not positions

## Problem

`logLine` has grown to seven positional parameters —
`(log, level, event, phase, line, durationMs?, root?)`
(`app/log-story.ts`) — and call sites now read
`logLine(log, level, event, phase, line, undefined, root)`. In
`app/plugins/request-log.ts`, the three story closers (`onResponse`,
`onRequestAbort`, `logRequestFailure`) each repeat the same
check-`storyClosed`, set, log shape — flagged by BUG-009's validation
review as a close-once helper waiting to exist.

## Goal

The logging seams read as one obvious shape.

## Outcome

`logLine`'s optional facts travel as one trailing options value and no call
site passes a positional `undefined`; the request story is closed through
one close-once seam that all three enders use; log output is byte-identical.

## Why it matters

These are the seams every future logging change goes through — FEAT-021's
ingest included. A positional boolean at the end of seven arguments is
where the next bug hides, and three hand-rolled close paths is how a story
gets closed twice or not at all when a fourth ender appears.

## Discovery notes

Advisory: `{ durationMs?, root? }` as a trailing options object; a
`closeStory(request, ...)`-shaped helper in `request-log.ts` owning the
guard and the line. Last in the sequence — it touches the same files as
RFCTR-005..007 and is the most mechanical.

## Related work

- 4ceb7e9 (BUG-009) — where the third closer and the flag generalization landed
- bae9212 (IMPRV-023) — where `root` joined the signature
