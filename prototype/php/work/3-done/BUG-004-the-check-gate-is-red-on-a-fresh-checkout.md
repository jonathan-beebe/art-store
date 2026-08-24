---
id: BUG-004
type: bug
status: resolved
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

## Working

Fixed the test; `config/rate_limits.php` is correct and unchanged.

`env()` resolves through Dotenv's `RepositoryInterface`, whose first reader
is the server/const adapter that `.env` filled at boot. `putenv()` writes to
an adapter behind that one, so the `.env` value answered and the config file
parsed `10/1h` instead of `not-a-limit`.

`src/app/Support/RateLimiting/RateLimitsConfigTest.php` now writes and reads
through `Illuminate\Support\Env::getRepository()`:

- `beforeEach` records the current value of all seven `RATE_LIMIT_*`
  variables and clears them, so each case starts from "nothing is set"
  whatever `.env` holds.
- `afterEach` puts each one back — `set()` when the repository had a value,
  `clear()` when it had none — rather than clearing unconditionally.
- The malformed case sets `RATE_LIMIT_CHECKOUT` through the repository; its
  cleanup is `afterEach`, so the `try`/`finally` around the expectation is
  gone.
- The defaults case keeps its assertions and now runs against a repository
  with the seven keys cleared, so it asserts the §3 defaults instead of
  echoing `.env.example`.

`.env.example` is untouched.

### Before

`.env` as the entrypoint writes it:

```
  PASS  App\Support\RateLimiting\RateLimitsConfigTest
  ⨯ it refuses to boot when a rate limit env variable is malformed
  ✓ it reads the docs/alignment.md §3 default for every limit when noth…

  FAILED  App\Support\RateLimiting\RateLimitsConfigTest > it refuses to boo…
  Exception "InvalidArgumentException" not thrown.

  at app/Support/RateLimiting/RateLimitsConfigTest.php:22

  Tests:    1 failed, 1 passed (9 assertions)
```

### After

`.env` as the entrypoint writes it (all seven `RATE_LIMIT_*` present):

```
  PASS  App\Support\RateLimiting\RateLimitsConfigTest
  ✓ it refuses to boot when a rate limit env variable is malformed
  ✓ it reads the docs/alignment.md §3 default for every limit when noth…

  Tests:    2 passed (10 assertions)
```

`.env` with the seven `RATE_LIMIT_*` lines stripped:

```
  PASS  App\Support\RateLimiting\RateLimitsConfigTest
  ✓ it refuses to boot when a rate limit env variable is malformed
  ✓ it reads the docs/alignment.md §3 default for every limit when noth…

  Tests:    2 passed (10 assertions)
```

`.env` with `RATE_LIMIT_CHECKOUT=99/30s`, a value that is not the §3 default
— the defaults case still reads `3600`, which is the proof it asserts
against the cleared repository rather than `.env`:

```
  PASS  App\Support\RateLimiting\RateLimitsConfigTest
  ✓ it refuses to boot when a rate limit env variable is malformed
  ✓ it reads the docs/alignment.md §3 default for every limit when noth…

  Tests:    2 passed (10 assertions)
```

### Gate

`make check` (Pint, PHPStan level max, full suite under pcov) green end to
end, exit 0:

```
 [OK] No errors

  Tests:    1827 passed (4934 assertions)
  Duration: 146.75s

                                                     Total: 100.0 %
```
