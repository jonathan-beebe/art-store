---
id: IMPRV-024
type: improvement
status: open
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
