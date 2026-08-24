---
id: MAINT-001
type: maintenance
status: resolved
created: 2026-08-22
---

# MAINT-001: Small leftovers from the refactor sweep

## Problem
Four small things the RFCTR-001 to RFCTR-013 agents noticed and left in place to stay in scope:
- `src/test/smoke_test.rb` defines a private `create_listing` (puts a listing up over HTTP) that shadows `TestRecords#create_listing`.
- `PayoutPeriod#covers?` (`src/app/models/payout_period.rb`) has no caller outside its own test.
- `docs/data-model.md` still draws `sellers`/`customers` → `notifications` lines although the table now carries a polymorphic `recipient` and no foreign key; the `magic_links` precedent omits such lines.
- Several test files carry lines over 120 characters (`seeds_test.rb`, `models/order_lifecycle_test.rb`, `models/listing_test.rb`, `models/payout_test.rb`) and the repository has no RuboCop configuration, so nothing enforces a line length or the `test "..."` style RFCTR-002 established.

## Goal
Nothing in the tree contradicts the conventions the sweep set.

## Outcome
The smoke test's helper carries a name of its own; `covers?` is used or deleted; the data-model diagram draws only real foreign keys; a `.rubocop.yml` (the `rubocop-rails-omakase` gem is the Rails 8 default) runs clean in the container and the README names the command.

## Why it matters
Each is a minute of work alone; together they are the difference between conventions that hold and conventions that were true once.

## Related work
- RFCTR-002
- RFCTR-009
- RFCTR-010

## Working

Closed as part of MAINT-002:

- `test/smoke_test.rb`'s private `create_listing` renamed to
  `submit_new_listing` (it posts a listing over HTTP); it no longer shadows
  `TestRecords#create_listing`.
- `PayoutPeriod#covers?` had no caller outside its own test — deleted, along
  with its two tests in `payout_period_test.rb`.
- `docs/data-model.md` no longer draws `sellers`/`customers`/`admins` →
  `notifications` lines — dropped all three, not only sellers/customers, so
  the diagram matches its own caveat that `notifications` carries no foreign
  key to any of the three (polymorphic `recipient`).
- The RuboCop config: `rubocop-rails-omakase` added and `make lint` runs
  clean. The over-120-character lines this ticket named were left as they
  are — a deliberate decision, not an oversight; see MAINT-002's Working
  note for the reasoning (omakase doesn't enable `Layout/LineLength`, and
  enabling it ourselves would be configuring past the doctrine's chosen
  style guide).
