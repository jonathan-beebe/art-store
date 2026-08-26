---
id: RFCTR-008
type: refactor
status: resolved
created: 2026-08-25
resolved: 2026-08-25
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

## Working

- Re-validated 2026-08-25: the seven-positional `logLine` and the three
  hand-rolled closers were as described; positional `undefined`/`true`
  trailers at `tellStory`'s `will` call and `request-log.ts`'s `will` call.
- `logLine` now ends in `{ durationMs?, root? }` (default `{}`); body and
  payload key order (`event, phase, data, duration_ms`) unchanged. Callers
  reshaped: `tellStory`'s two lines, `request-log.ts`'s `will` line. The
  other six call sites pass five arguments and needed no edit.
- `closeStory(request, close)` in `request-log.ts` owns the `storyClosed`
  guard-and-set and the closing line; `close` is a discriminated union —
  `{ phase: 'did', status, durationMs, facts? }` |
  `{ phase: 'failed', status, durationMs, error }`. `onResponse` and
  `onRequestAbort` keep their asset-path early returns and call it;
  `logRequestFailure`'s body is one `closeStory` call (it kept its
  no-asset-check guard, as before). The `failed` branch stays a raw
  `log.error` because its payload key order
  (`event, phase, duration_ms, error, data`) differs from `logLine`'s.
- Reviewer verdict: accept-with-nits. The one nit — `root: true` on the
  helper's `did` line, inert but semantically wrong — was fixed by dropping
  it (matches the pre-refactor implicit `false`).
- `make check` green: 2093 tests, 0 fail; coverage 99.40 lines / 95.74
  branches / 99.53 functions.

Out of scope, noted for a possible follow-up: `closeStory`'s two branches
use two write paths (`request.log.error` raw vs `logLine`) because `logLine`
has no `error` field; teaching `logLine` the `error`-carrying `failed` shape
would give the helper one write path.
