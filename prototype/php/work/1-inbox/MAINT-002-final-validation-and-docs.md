---
id: MAINT-002
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-002: Final validation — analyzer at zero on app and tests, docs current

## Problem
After the refactor tickets land, the numbers in `README.md`, `docs/review.md`, and `docs/architecture.md` (471 tests, PHPUnit, no analyzer) will be stale, `phpstan.neon` still excludes `**/*Test.php` and omits `tests/` from `paths`, and nobody has run the whole thing from a clean checkout since FEAT-008.

## Goal
A reviewer cloning the repo runs one command and sees lint, analyzer, and tests all pass, and the docs tell the truth about what they are looking at.

## Outcome
- `phpstan.neon` analyses `app`, `database`, `routes`, and `tests` including the sidecar tests at `level: max` with zero errors and no `ignoreErrors`.
- `make check` passes from a clean checkout (`make down && make build && make check`), `make smoke` passes, `make fresh` seeds, and both sites render on the seeded data (screenshot or route walk noted in Working).
- `README.md`, `docs/architecture.md`, `docs/review.md` state the current test count, coverage, the analyzer and lint gate, Pest sidecars, policies, form requests, events and notifications, and components; `docs/review.md` "Known gaps" is updated for what the tickets closed.
- `work/journal.md` has every ticket's done entry.

## Why it matters
The prototype is judged against two others by someone reading docs first; stale numbers cost more than missing features.

## Discovery notes
- Adding `tests` to PHPStan paths was measured at +114 errors before the model docblocks; most fall out after MAINT-001. Whatever remains is a test asserting on something it has not proven exists.
- Docker Desktop disk was near full on 2026-08-22; `docker system df` before `make build`.

## Related work
- MAINT-001 through RFCTR-008, IMPRV-001, BUG-001, BUG-002
