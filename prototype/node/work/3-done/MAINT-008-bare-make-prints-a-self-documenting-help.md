---
id: MAINT-008
type: maintenance
status: resolved
created: 2026-08-25
---

# MAINT-008: Bare make prints a self-documenting help

## Problem
The four Makefiles (repository root, `prototype/node`, `prototype/php`, `prototype/rails`) document their targets only in comment prose and in `docs/alignment.md` §6.1; nothing lists the targets at the command line. Bare `make` runs the first target instead — `up` in the prototypes — so typing `make` to see what exists silently starts the compose stack.

## Goal
Bare `make` in any checkout directory lists every target with a one-line description.

## Outcome
Running `make` (or `make help`) in the root and in each prototype prints an aligned target/description listing covering every target; bare `make` no longer starts the stack or runs any other target; all existing targets behave unchanged when named explicitly.

## Why it matters
The make vocabulary is the whole operational interface of this repo, and today discovering it means opening the Makefile or the contract doc. Decided by the user on 2026-08-25. Bare `make` starting the stack is a surprise the change also removes.

## Discovery notes
- The standard self-documenting pattern, chosen by the user: each target line carries a `## one-line description`; a `help` target greps `$(MAKEFILE_LIST)` for `^[a-zA-Z0-9_-]+:.*?## ` and awk-formats the pairs; `.DEFAULT_GOAL := help`. Use `grep -h` so filenames don't leak into the output.
- The existing multi-line prose comments above targets stay — the `##` line is the summary, the paragraphs remain the depth.
- Write the `##` summaries against the post-MAINT-007 meanings (`test` ungated, `coverage` carries the gate, `check` the commit gate) and consistent with §6.1's wording so the two don't lie apart; no automated drift check — accept the risk until it drifts.
- `help` joins the §6.1 vocabulary table.
- The root Makefile is small (`hooks`); it gets the same pattern.

## Related work
- MAINT-007 (must land first — the summaries describe its new vocabulary)
- MAINT-001 (established the make vocabulary)

## Working
- 2026-08-25 — Behavior change confirmed as intended: the prototypes' first target was `up` and the root's was `hooks`, so bare `make` started the stack (root: installed the hook). With `.DEFAULT_GOAL := help` bare `make` prints the listing in all four directories.
- Pattern applied identically in all four Makefiles: `##` summary on every target line, `help` greps `$(MAKEFILE_LIST)` with `grep -h` and awk-formats `%-12s  %s`; `help` first in `.PHONY` and in the file so it heads the listing. No `gensub`; verified under BSD awk.
- Summaries written to the post-MAINT-007 vocabulary: `test` the full suite ungated, `coverage` the gate + report, `check` lint -> assets -> coverage (the commit gate). Variable-taking targets name their variable (`payouts`/`sweep` `AS_OF=`, `outbox` `DIR=`) per §6.1.
- The node and php Makefiles carried the `COMPOSE_PROJECT_NAME` export block duplicated (3x node, 3x php, 2x rails) — identical `?=` lines, so no-ops; collapsed to one per file while rewriting.
- §6.1 gains the `help` row; the three prototype READMEs' Commands intros gain one sentence pointing at bare `make`/`make help`. Root README has no make section — left alone.
- Verified: help listing covers every `.PHONY` target in all four files (diffed programmatically); `make -n up` / `make -n hooks` still run the named recipes.
