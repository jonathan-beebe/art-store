---
id: BUG-004
type: bug
status: open
created: 2026-08-24
---

# BUG-004: The check gate is red on a fresh checkout

## Problem
`App\Support\RateLimiting\RateLimitsConfigTest`'s first case fails on any
checkout whose `.env` was made by the entrypoint. The case sets
`putenv('RATE_LIMIT_CHECKOUT=not-a-limit')` and expects
`config/rate_limits.php` to refuse; when `.env` already defines
`RATE_LIMIT_CHECKOUT`, the Dotenv repository answers with the `.env` value
and `putenv` never reaches `env()`, so nothing throws.

`.env.example` gained the seven `RATE_LIMIT_*` lines in FEAT-021, and
`docker/entrypoint.sh` copies `.env.example` to `.env` when `.env` is
missing. A checkout made after FEAT-021 therefore starts red:

```
Tests:  1 failed, 1826 passed (4933 assertions)
```

A checkout whose `.env` predates FEAT-021 is green, which is why this
survived MAINT-004's validation run.

## Goal
`make check` is green on a checkout that has never been touched, and stays
green whatever `.env` holds.

## Outcome
`make check` passes from a clean clone (`rm -f src/.env` then `make up`,
then `make check`), and the two cases in `RateLimitsConfigTest` pass whether
or not `.env` defines `RATE_LIMIT_*`.

## Why it matters
Every worktree, every agent lane, and CI on a fresh runner start from a
`.env` the entrypoint wrote. The gate that is supposed to hold the branch is
failing for a reason that has nothing to do with the change under test, and
the first person to see it will assume they broke it.

## Discovery notes
`env()` reads Dotenv's `RepositoryInterface`, which is populated from `.env`
at boot and shadows `putenv`. The test has to write through the same
repository it reads — `Illuminate\Support\Env::getRepository()->set(...)` /
`->clear(...)` — rather than through `putenv`, and it must restore whatever
was there before rather than clearing unconditionally.

Verified: with the seven `RATE_LIMIT_*` lines stripped from `.env`, both
cases pass; with them present, the first fails. The second case ("reads the
default for every limit when nothing is set") passes either way today only
because `.env.example`'s values happen to equal the documented defaults —
it is asserting nothing while `.env` is set, and should be made to assert
against a repository with those keys cleared.

## Related work
- FEAT-021 (configurable rate limits, added the `.env.example` lines)
- MAINT-004 (validation run that missed it)
- RSRCH-001
