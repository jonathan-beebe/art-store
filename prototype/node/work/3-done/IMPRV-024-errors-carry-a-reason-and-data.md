---
id: IMPRV-024
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-024: errors carry a reason and data

## Problem

The domain has one error class, `core/transition-error.ts`, and it carries
only a message. Thrown defects surface as bare `Error`/`RangeError`/
`TypeError`; `describeError` (`app/core/logging/logged-error.ts`) logs
`{type, message}` with no sub-category and no ids, and a `refused` line's
reason (`app/log-story.ts:130`) is the exception's class name.
`docs/alignment.md` §2.1 now gives the `error` object `reason` and `data`,
and §2.2 expects `data.reason` to name the refusal within the event's
category.

## Goal

An error names its category, its reason within that category, and the facts
behind it — for refusals and defects both.

## Outcome

An expected "no" reaches its caller as a value carrying a reason and the
facts behind it, and its `refused` line names that reason in `data.reason`.
A thrown defect surfaces in the `failed` line's `error` object with `type`,
`reason`, `message`, and `data` when it carries them; an exception with
neither still logs `{type, message}` as today.

## Why it matters

A reason is a sub-category the log and the UX can branch on; a message is
prose for a person. Today only prose exists, so routes match on message
strings and log lines cannot be grouped by cause. The reason is also what a
route maps to the retry/wait/stop flows `docs/principles.md` requires.

## Discovery notes

Advisory: the split follows the return/throw rule — an expected outcome is a
returned refusal result (the `{outcome: 'refused', reason, data}` shape
`actions/auth/sign-in-with-magic-link.ts` already uses), and a defect is a
thrown error class whose `name` is the type, with `reason` and `data` fields
(data missing, config wrong, contract broken are candidate categories).
`describeError` can pick up `reason` and `data` when the exception carries
them. This ticket lands the vocabulary, the logging, and the shared shapes;
migrating the existing `TransitionError` sites is IMPRV-025 through
IMPRV-028.

## Related work

- 2d44906 — docs: log contract gains emoji prefixes, refusal reasons, and error reason/data

## Working

2026-08-25 — re-validated: `core/transition-error.ts` still carries only a
message, `describeError` still logs `{type, message}`, and
`log-story.ts` `logException` still writes `data.reason` as the class name.
IMPRV-023 (bae9212) landed the emoji prefixes; this ticket builds on it.

Design:

- `app/core/refusal.ts` — `Refusal<Reason extends string = string>` =
  `{ outcome: 'refused', reason: Reason, data?: Record<string, unknown> }`,
  plus `refused(reason, data?)` building one. Mirrors the shape
  `actions/auth/sign-in-with-magic-link.ts` returns; actions adopt it in
  IMPRV-025..028.
- `app/core/defect.ts` — abstract `Defect extends Error` with
  `reason: string` and `data?: Record<string, unknown>`; `name` comes from
  the concrete class via `new.target.name`. Categories:
  `MissingDataError` (a row or value the code requires is not there),
  `BadConfigError` (the environment or configuration cannot be used),
  `BrokenContractError` (a caller broke a function's contract).
- `core/logging/logged-error.ts` — `LoggedError` gains optional `reason` and
  `data`; `describeError` picks them up structurally off any `Error` that
  carries them (`reason` a non-empty string, `data` a non-null non-array
  object), so a `Defect`, a reasoned `TransitionError`, and a foreign error
  all log alike. Stack handling unchanged (redacted outside development by
  the logger).
- `core/transition-error.ts` — constructor gains an optional
  `{ reason?, data? }` second argument so a throw site can name its
  sub-category before IMPRV-025..028 retire the class. Plain construction
  is unchanged.
- `log-story.ts` `logException` — the refused line's `data` becomes the
  carried `data` spread under `reason: described.reason ?? described.type`;
  class-name fallback stays for plain `TransitionError`. The failed line's
  `error` object carries `reason`/`data` for free via `describeError`.

Out of scope (IMPRV-025..028): migrating the `TransitionError` throw sites
and the routes that catch them.

2026-08-25 — resolved. TDD red (16 new tests across 5 files, red confirmed)
→ green. Landed: `core/refusal.ts` (`Refusal<Reason>` + `refused()`),
`core/defect.ts` (`Defect` + `MissingDataError`/`BadConfigError`/
`BrokenContractError`), reasoned `TransitionError`, structural
`reason`/`data` pickup in `describeError`, refused-line
`data.reason ?? class name` in `logException`. Reviewer: accept, no
refactors. `make check` green: 2060 tests, coverage 99.39 lines /
95.65 branches / 99.46 functions. No `TransitionError` call site
migrated; `Defect` has no production throw sites yet — both by design,
adoption is IMPRV-025..028.
