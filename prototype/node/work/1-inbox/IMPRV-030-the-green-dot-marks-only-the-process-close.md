---
id: IMPRV-030
type: improvement
status: open
created: 2026-08-26
---

# IMPRV-030: the green dot marks only the process close

## Problem

`storyEmoji` (`app/core/logging/story-emoji.ts`) prefixes every `did` line
with 🟢, so a request that runs several actions shows green dots in the
middle of its story. `docs/alignment.md` §2.4 (commit 2ad81ff) now reserves
the boundary emoji for the process: 🎬 opens, 🟢 or ❌ closes, once each —
a nested `did` stays unprefixed so the boundaries stand out.

## Goal

The emoji mark the beginning, the warnings, and the end — nothing else.

## Outcome

Only the root `did` carries 🟢; a nested `did` writes its sentence with no
prefix. The rest of the table is unchanged: one 🎬 per request or CLI run,
❌ on a failed close, 🛑 on nested `failed`, ⚠️ on `refused` and warn lines
(a nested `did` at `warn` — `rate_limit.exceed` — still reads ⚠️). Log
output is otherwise byte-identical, and user-facing text stays emoji-free.

## Why it matters

The human stated it directly: the green in the middle is distracting. The
prefix's job is to make a request's boundaries and its trouble findable in
an interleaved stream; a repeated success marker dilutes exactly that
signal.

## Discovery notes

Advisory: the change is the `did` arm of `storyEmoji` gaining the same
`root` condition `will` and `failed` already have; the tests that pin
nested `did` messages (action stories, CLI steps, log-story and story-emoji
suites) shed their 🟢 expectations.

## Related work

- 2ad81ff — the §2.4 amendment this implements
- bae9212 (IMPRV-023) — where the derivation lives
