---
id: IMPRV-030
type: improvement
status: resolved
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

## Working

- 2026-08-26 — re-validated: `storyEmoji` (`app/core/logging/story-emoji.ts`)
  still returns 🟢 for every `did`; the root block covers `will` and `failed`
  only. The change is one arm: `did` moves into the `if (root)` block.
- Sweep of 🟢 across `src/app` finds six test pins. Four shed the prefix
  (nested did): `story-emoji.test.ts` (unit + table rows), `prepare-db.test.ts`
  `migrate.apply`, `run-payouts.test.ts` `payout.pay`, `checkout.test.ts`
  `order.place`. Two keep it (root did): `log-story.test.ts:183` (`root: true`
  story) and `request-log.test.ts:155` (`http.request`).
- `sweep-stale-orders.test.ts:75` already pins the no-prefix pattern on a
  nested `will`; the shed pins adopt the same `doesNotMatch` form.
- Check order after the change: warn keeps outranking the nested `did` arm
  (which now falls through to `null`), so `rate_limit.exceed` still reads ⚠️.
- The reorder exposed one gap: `closeStory`'s `did` branch in
  `plugins/request-log.ts` passed no `root` while its `will` and `failed`
  writes do — the unconditional 🟢 had masked it. `root: true` added to that
  options object so the `http.request` close keeps its 🟢. Every other root
  story threads `root` through `tellStory` and needed nothing.
