---
id: MAINT-002
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-002: Common make vocabulary, RuboCop gate, CI, and a test database that resets

## Problem
`Makefile` has no `lint`, `lint-fix`, `check`, `seed`, `routes`, `payouts`, `sweep`, or `hooks` targets and there is no static-analysis gate at all (no RuboCop config — `MAINT-001`); `bin/ci` runs `test app lib` only and no GitHub workflow exists for Rails; a killed test run leaves `storage/test.sqlite3` poisoned so the next run fails without saying why (`BUG-003`). `docs/alignment.md` §6 fixes the target vocabulary and says CI and the pre-commit hook (`.githooks/pre-commit`, runs `make check`) run the same command.

## Goal
One command, `make check`, is the commit gate, the CI job, and the developer's habit, with the same target names as the other two prototypes, and a test run always starts from a database it can use.

## Outcome
Every §6.1 target exists with its meaning (`sweep` may print that it lands with FEAT-017 until it does); `make lint` runs `rubocop` with `rubocop-rails-omakase` and the tree is clean; `make check` runs lint → assets → test (with the `COVERAGE_MIN` gate) and fails on any; `.github/workflows/rails.yml` runs `make check`; `make test` prepares the test database so a killed prior run cannot poison it; `MAINT-001`'s leftovers are closed or explicitly carried; a commit touching `prototype/rails/src` with a failing test is refused by the hook installed by `make hooks` at the repository root.

## Why it matters
The commit gate and CI must be the same command or one of them lies; Rails is the only prototype without a lint gate, which the comparison will count.

## Discovery notes
`rubocop-rails-omakase` is the doctrine's choice (`__local__/prompts/rails-doctrine.md`); `bin/rails db:test:prepare` before the suite, or `maintain_test_schema` plus a truncation, fixes BUG-003. `make routes` is `bin/rails routes`; `make seed` is `db:seed`; `make payouts` is the existing rake task. Work `BUG-003` and `MAINT-001` from `1-inbox/` as part of this ticket and move them to done with it.

## Related work
- docs/alignment.md §6
- BUG-003, MAINT-001

## Working

Done:

- `Makefile` answers every §6.1 target: `up`, `down`, `build`, `logs`, `shell`,
  `test`, `smoke`, `coverage`, `lint`, `lint-fix`, `assets`, `check`,
  `migrate`, `fresh`, `seed`, `routes`, `payouts`, `sweep`, `outbox`, plus the
  pre-existing `console` (kept — not in §6.1, but useful and harmless).
  `COMPOSE_PROJECT_NAME` export left unchanged. Every target run and checked
  by hand (`make lint`, `make lint-fix`, `make assets`, `make test`, `make
  smoke`, `make coverage`, `make migrate`, `make fresh`, `make seed`, `make
  routes`, `make payouts` with and without `AS_OF=`, `make sweep`, `make
  outbox`, `make check`, `make down`, `make build`).
- `test` = `bin/rails db:test:prepare && bin/rails test` with
  `COVERAGE_MIN=100` (the suite's standing line coverage). `coverage` runs
  the same prepare-then-test without the gate, for reading the report — no
  minimum is enforced there now (previously an unrelated looser 80% gate
  lived on `coverage`; dropped, since `test`/`check` carry the real gate at
  100 and a second, looser one on `coverage` no longer meant anything).
  `check` = `lint` → `assets` → `test`.
- `rubocop-rails-omakase` added to the Gemfile's `:development` group;
  `src/.rubocop.yml` inherits its `rubocop.yml`; `bin/rubocop` binstub added.
  `make lint` was clean after `make lint-fix` corrected 212
  `Layout/SpaceInsideArrayLiteralBrackets` offenses across 44 files (app,
  db/migrate, lib/tasks, test) — array literals without the interior space
  omakase's style wants. No manual line-length fixes: see the decision below.
- BUG-003 fixed by wiring `bin/rails db:test:prepare` ahead of every
  `bin/rails test` invocation in the Makefile (`test`, `smoke`, `coverage`),
  the "wire it into the Make target" option from the ticket's discovery
  notes. Nothing in the normal workflow needs to know `db:test:prepare`
  exists. Proof below.
- MAINT-001's four leftovers: see its own Working note.
- `.github/workflows/rails.yml` added: push/PR on `prototype/rails/**` and
  the workflow file itself, one job that runs `make -C prototype/rails
  check` — the same command the pre-commit hook runs, so the two cannot
  disagree.
- `bin/ci` and `config/ci.rb` deleted. They were the Rails-generator default
  CI runner (`bin/setup`, `bundler-audit`, `bin/rails test app lib`,
  `db:seed:replant`) — a different step list than `check` (`lint` → `assets`
  → `test`), and a script that runs `bundle`/`rails` directly rather than
  through Docker. Keeping both invited exactly the drift `docs/alignment.md`
  §6.2 rules out ("the hook and CI cannot disagree"); `make check` replaces
  it fully. `bundler-audit` the gem stays (unused by any target now, but out
  of this ticket's scope — MAINT-002 doesn't ask for a security-audit target
  in §6.1's vocabulary).
- README's Commands table rewritten to the new target list; added a
  "Linting" section; the Coverage and Tests sections updated for the
  `COVERAGE_MIN=100` gate and the `db:test:prepare` reset.
  `docs/architecture.md`'s testing section updated to match (coverage gate,
  the db reset, RuboCop).

Decisions:

- **Layout/LineLength: left disabled.** `rubocop-rails-omakase` disables
  every cop department by default and opts individual cops back in;
  `Layout/LineLength` is never opted back in, so under the gem's own
  `rubocop.yml` it stays off. Enabling it would mean configuring past
  omakase rather than running it, which the doctrine (vanilla Rails,
  omakase over the community defaults) argues against. MAINT-001's
  over-120-character lines in `seeds_test.rb`,
  `models/order_lifecycle_test.rb`, `models/listing_test.rb`, and
  `models/payout_test.rb` are left as they are — closed by this decision,
  not by editing them.
- **`data-model.md`: dropped all three `→ notifications` lines, not only
  sellers/customers.** The task named sellers/customers specifically; the
  same caveat already in the doc ("no foreign key to any of them") covers
  `admins` too, since `recipient_type` is `Seller | Customer | Admin`.
  Leaving the `admins ||--o{ notifications` line while dropping the other
  two would have produced a diagram that contradicts its own caveat text for
  one of the three cases and not the other two. Dropped all three for
  "draw only real foreign keys."
- **`bin/ci` / `config/ci.rb`: deleted rather than reconciled.** See above.
- **`console` target: kept**, though it isn't in §6.1's vocabulary — a
  Rails-only convenience the other two prototypes don't need in the same
  form (Node/PHP have no equivalent target), and keeping it doesn't
  contradict "the same target names" for the targets that are shared.
- **`payouts` argument passing**: `bin/rails "payouts:run[$(AS_OF)]"` — an
  empty `AS_OF` yields `payouts:run[]`, which Rake treats as no argument
  (`args[:as_of]` is `""`, `.present?` is false), so `Time.current` is used,
  matching the pre-existing rake task's own default. Verified both with and
  without `AS_OF=`.

BUG-003 proof: with the fix in place, `make test` already passed cleanly
(748 runs, 2361 assertions, 0 failures, 0 errors, 100% line coverage — the
suite's standing numbers). To prove the fix actually does something, rather
than trusting a run that happened to land safely:

1. Killed a `bin/rails test` run mid-suite with `docker kill`, timed to land
   right after `seeds_test.rb` had printed its first "Seeded …" line (so a
   `Rails.application.load_seed` commit had already landed in
   `storage/test.sqlite3`). The container exited 137 (SIGKILL). This
   particular kill point turned out to leave the database recoverable (a
   follow-up bare `bin/rails test`, no prepare, still passed) — the race
   window for the actual corruption is narrow and didn't land this time, so
   this alone was not a strong proof.
2. Reproduced the poisoned state deterministically instead, the same way the
   ticket's own diagnosis does: ran `bin/rails db:seed` directly against
   `RAILS_ENV=test`, which commits real rows into `storage/test.sqlite3`
   with no enclosing test transaction — functionally identical to what a
   badly-timed kill leaves behind, and reliable rather than a race. This
   repository has no fixture files (`docs/architecture.md` already says so:
   "There are no fixture files"), so nothing resets those tables between
   runs on its own.
3. Ran the bare suite (no `db:test:prepare`): **65 failures, 29 errors** —
   the same shape as the bug report's "49 failures and 28 errors on an
   untouched tree," and nothing in the output names the cause, confirming
   the bug as described.
4. Ran `make test` (the fix): **748 runs, 2361 assertions, 0 failures, 0
   errors, 100% line coverage** — full recovery, run count and coverage
   unchanged from the pre-poisoned baseline.

Deviations from the contract: none noted beyond the decisions above.

Open questions: none.
