---
id: BUG-003
type: bug
status: open
created: 2026-08-23
---

# BUG-003: A killed test run poisons the test database and the next run fails without naming why

## Problem
`test/seeds_test.rb` calls `Rails.application.load_seed` and `test/smoke_test.rb` walks the product over HTTP; both write rows to `src/storage/test.sqlite3` outside the transactional-fixture rollback. A suite run that finishes cleans up after itself, but a run killed partway leaves those rows behind, and the next `make test` fails with dozens of unrelated errors (observed: 49 failures and 28 errors on an untouched tree, with `Seeded 1 admin, 4 sellers…` lines in the output as the tell). Nothing in the output names the cause; `docker compose run --rm app bin/rails db:test:prepare` clears it.

## Goal
A test run starts from a known-clean database whatever happened to the run before it.

## Outcome
Killing a suite mid-run and running `make test` again passes; nothing in the normal workflow requires knowing about `db:test:prepare`; the suite's run count and 100% line coverage are unchanged.

## Why it matters
A poisoned test database produces a wall of failures that reads as broken application code; anyone who hits it after an interrupt loses time to a cause the output does not name.

## Discovery notes
Options observed in the wild: reset the schema/data before the suite in `test_helper.rb`; have the tests that seed truncate what they wrote in a `teardown`; or wire `db:test:prepare` into the Make target ahead of the run. Whichever lands, the seeds and smoke tests are the only writers outside the fixture transaction, so the fix can stay near them.

## Related work
- FEAT-014 (extended the seeds and smoke tests)
- IMPRV-001 (where the failure was observed and diagnosed)
