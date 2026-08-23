---
id: RFCTR-001
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-001: Run the suite with bin/rails test from a conventional test/ tree

## Problem
Tests are sidecars inside the source tree: `src/app/domain/money_test.rb` beside `money.rb`, `src/db/seeds_test.rb`, `src/lib/tasks/payouts_test.rb`, `src/app/views/layouts/debug_alert_test.rb`. Running them needs `bin/rails test app lib db test`, a Zeitwerk `ignore("**/*_test.rb")` in `src/config/application.rb`, and a SimpleCov `skip(/_test\.rb\z/)` in `src/test/test_helper.rb`. `bin/rails test` on its own runs only `test/smoke_test.rb`.

## Goal
A Rails developer finds and runs the tests where Rails puts them.

## Outcome
`bin/rails test` with no arguments runs every test (645 runs at the time of filing); `find app lib db -name '*_test.rb'` is empty; `config/application.rb` has no autoloader exception for tests; README, Makefile and docs describe the conventional layout.

## Why it matters
Every Rails tool (`bin/rails test`, editors, CI templates, SimpleCov defaults) assumes `test/models`, `test/controllers`, and so on. The sidecar layout costs a custom autoloader rule and a custom command that every newcomer has to learn.

## Discovery notes
Mirror `app/<dir>/` under `test/<dir>/`; `git mv` keeps history. Domain tests currently boot without Rails (`require "minitest/autorun"` + `require_relative`); switching them to `require "test_helper"` is the simpler shape and the no-Rails mode has no consumer. `make test`, `make coverage`, README "Tests"/"Coverage"/"Layout", and `docs/architecture.md` "Testing" all name the old command.

## Related work
- ff47f67 (rails prototype)

## Working

Resolved in `d4044ad`. 109 test files moved with `git mv` into `test/`
mirroring `app/` (`test/domain`, `test/actions`, `test/controllers`,
`test/models`, `test/delivery`, `test/views`, `test/tasks`, `test/seeds_test.rb`).
The 52 domain tests and the placeholder-image test that booted without Rails
now `require "test_helper"` and subclass `ActiveSupport::TestCase`.
`config/application.rb` lost the Zeitwerk ignore and `test/test_helper.rb`
the SimpleCov skip. `make test`/`make coverage` call `bin/rails test`; README,
`docs/architecture.md`, `docs/orders.md`, `docs/review.md` updated.
`zeitwerk:check` clean; `bin/rails test` 645 runs, 1604 assertions, 0 failures,
100% line coverage, identical to the baseline. The `work/3-done` FEAT tickets
and older journal lines still say "sidecar"; they are dated records and were
left alone.
