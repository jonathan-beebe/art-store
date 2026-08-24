---
id: BUG-003
type: bug
status: open
created: 2026-08-23
---

# BUG-003: A blocked customer's ask leaves an empty thread, and a magic link can be consumed twice

## Problem
Opening a listing question and posting its first message are two transactions, so a blocked customer's refused first post leaves an empty `conversations` row (recorded as gap 10 in FEAT-017). Magic-link consumption reads the row then writes `consumed_at` in two statements, so two concurrent verifications of the same token both succeed (Node checks the `UPDATE … WHERE consumed_at IS NULL` row count).

## Goal
Thread opening and link consumption are each one atomic step.

## Outcome
A refused first post leaves no conversation row; the open + first message happen in one transaction; a second concurrent consume of the same magic link is refused, asserted by a test that consumes the same token twice with the row-count check; docs state both.

## Why it matters
Empty threads show up in the seller's inbox; a double-consumable link is a session-fixation primitive.

## Discovery notes
`firstOrCreate` inside the same `DB::transaction` as the post; `MagicLink::whereNull('consumed_at')->update([...])` returning the affected count is the Node shape in Eloquent.

## Related work
- FEAT-013, FEAT-017 (gap 10)
- prototype/node BUG-004
