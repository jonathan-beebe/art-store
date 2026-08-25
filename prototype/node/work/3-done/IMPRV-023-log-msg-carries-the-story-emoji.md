---
id: IMPRV-023
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-023: log msg carries the story emoji

## Problem

`docs/alignment.md` §2.4 (commit 2d44906) specifies an emoji prefix on every
log `msg`, derived from `(phase, level, root)`, where `root` is the story that
opens the process — the HTTP request or the CLI run. Node writes plain
sentences: `app/log-story.ts` `logLine` knows phase and level but has no
notion of the process root, and neither `app/plugins/request-log.ts` nor the
CLI entrypoints mark the story that opens a request or a run.

## Goal

One request's log reads as a story at a glance — where it began, what happened
inside, and how it ended.

## Outcome

Every line the node prototype writes carries the §2.4 prefix: one 🎬 per
request or CLI run, ❌ as the last line of a failed one, 🟢 on `did`, 🛑 on a
nested `failed`, ⚠️ on `refused` and on warn lines, and none on a nested
`will` or an info `doing`. Text rendered to a person — flash messages, error
pages, form errors — carries none.

## Why it matters

Debugging these prototypes means reading stdout. The prefix marks openings,
endings, and trouble without parsing JSON fields, and the one-🎬 / one-❌
rule makes each request's boundaries visible in a stream that interleaves many.

## Discovery notes

Advisory: derive the prefix in one place as a pure function of
`(phase, level, root)` so no call site picks an emoji and action code keeps
writing plain sentences. The request story is told in
`app/plugins/request-log.ts` (its terminal failure line by
`logRequestFailure`), and the CLI run stories in `app/cli/*`; those are the
only places that know they are the root.

## Related work

- 2d44906 — docs: log contract gains emoji prefixes, refusal reasons, and error reason/data

## Working

2026-08-25 — re-validated: `logLine` (`app/log-story.ts`) writes `line.msg`
untouched, `logRequestFailure` and `logException`'s failed branch write raw
sentences, and nothing marks a root story. The need stands.

Design fixed before implementation:

- `app/core/logging/story-emoji.ts` — `storyEmoji(phase, level, root)`, the
  one derivation, checked in this order: root `will` 🎬; root `failed` ❌;
  `warn` ⚠️; `refused` ⚠️; `did` 🟢; `failed` 🛑; otherwise none.
  `warn` sits above `did` so `rate_limit.exceed` (a `did` written at `warn`,
  the vocabulary's one warn line) reads ⚠️ — §2.4's "any warn line" row names
  it, and a green trip line would hide the trouble the level marks.
- `Story` gains `root?: boolean`; `logLine` gains a trailing `root` flag and
  prepends the emoji; `logException` takes `root` for its ❌/🛑 split.
- `ActionContext` gains `rootStory?: boolean` so a CLI entrypoint marks the
  story the action tells for it (`payout.run`, `order.sweep`) without the
  action knowing about processes; `actionStory` consumes the flag and hands
  `work` a context without it, so nested stories stay unmarked.
- Roots: the `http.request` story (`plugins/request-log.ts`, including
  `logRequestFailure`), and the CLI run stories — `migrate.run`, `seed.run`,
  `notification.deliver` (drain-outbox), `payout.run`, `order.sweep`.
  `app.boot`/`app.shutdown` stay unmarked: §2.4 names the HTTP request and
  the CLI run as the process openers, and the server process's stories are
  its requests. `prepare-db` chains the migrate and seed runs, so that one
  process prints one 🎬 per run it contains.
