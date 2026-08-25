---
id: IMPRV-023
type: improvement
status: open
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
