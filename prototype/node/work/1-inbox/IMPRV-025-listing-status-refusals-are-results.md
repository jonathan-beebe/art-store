---
id: IMPRV-025
type: improvement
status: open
created: 2026-08-25
---

# IMPRV-025: listing status refusals are results

## Problem

An illegal listing status move travels as a thrown `TransitionError`
(`app/core/listings/listing-status.ts:25`,
`app/actions/listings/change-listing-status.ts:72`) and is caught in
`app/sites/seller/routes/listings.ts:373`. A stale form or a
no-longer-possible move is an expected outcome (`docs/principles.md`), yet it
is modeled as an exception, and the refused log line's reason is a class
name.

## Goal

A refused listing status change is a normal result with a named reason.

## Outcome

Listing status actions answer an illegal move with a refusal value naming the
reason and the facts (`status_from`, `status_to`); the route renders the
refusal from the result it was returned; the `refused` log line carries
`data.reason`; no listing path throws `TransitionError`.

## Why it matters

The return/throw rule: a refusal is the answer the person gets, and a throw
is a defect. While refusals travel as exceptions, a real defect on the same
path can be swallowed by the same catch, and the route cannot tell a stale
form from a bug.

## Related work

- IMPRV-024 — errors carry a reason and data (lands the refusal shape this migration uses)
- 2d44906 — docs: log contract gains emoji prefixes, refusal reasons, and error reason/data
