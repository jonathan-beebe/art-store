---
id: RFCTR-005
type: refactor
status: open
created: 2026-08-25
---

# RFCTR-005: refusal data is typed per reason

## Problem

`Refusal.data` is `Record<string, unknown>` (`app/core/refusal.ts:13`), so
the facts a refusal carries are invisible to the compiler. `transitionFacts`
(`app/core/refusal.ts:28`) exists to recover `status_from`/`status_to` with a
runtime guard that throws `BrokenContractError('missing_transition_statuses')`
— the core re-checking a guarantee the core itself made. The copy mappers
accept bare `Refusal` (`fulfillmentTransitionRefusalCopy` in
`app/core/orders/fulfillment-status.ts`), so handing one a refusal of the
wrong reason compiles and detonates at runtime.

## Goal

The type of a refusal carries the facts it promises.

## Outcome

A refusal's data shape is fixed per reason in its type; the copy mappers
accept only refusals whose reason they word, and handing one the wrong
refusal fails to compile; `transitionFacts` and its runtime guard are gone;
every log line and every user-facing sentence is byte-identical to before.

## Why it matters

"Parse, don't validate": the action constructs the refusal with the statuses
in hand, so no later runtime check should be able to fail. Illegal states
unrepresentable beats guarded-against, and the guard's failure mode — a
defect thrown while wording a refusal — is the worst place to find out.

## Discovery notes

Advisory: a second type parameter, `Refusal<Reason, Data>` with `data`
required when the reason promises facts, and `refused()` inferring both;
transition refusals become e.g.
`Refusal<'illegal_transition', { status_from: FulfillmentStatus; status_to: FulfillmentStatus }>`.
Actions already carry the right values — this is a types-only tightening
plus the deletion of `transitionFacts`; runtime behavior should not change.

## Related work

- 4fbb01f (IMPRV-024) — the Refusal shape
- 93e095d (IMPRV-029) — transitionFacts and the copy mappers
