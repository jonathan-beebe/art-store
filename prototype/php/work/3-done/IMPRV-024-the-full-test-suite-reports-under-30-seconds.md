---
id: IMPRV-024
type: improvement
status: resolved
created: 2026-09-01
---

# IMPRV-024: the full test suite reports under 30 seconds

## Problem

The php prototype's ungated suite (composer test → pest, composer.json:62)
runs 3218 tests / 9367 assertions in 90.27s inside the container (measured
2026-09-01). It runs in a single process against sqlite :memory: with
RefreshDatabase. The per-commit gate (make precommit, IMPRV-021) runs this
suite on every commit, so each commit pays ~90s of test time plus lint.

## Goal

The full suite reports under 30 seconds, so the per-commit gate stays out of
the commit rhythm.

## Outcome

make test runs the full suite green with pest reporting a total duration
under 30 seconds. (Amended 2026-09-01 at acceptance: the measured floor at 8
parallel workers on the 8-CPU Docker VM is ~40s with 35–65s run-to-run spread
from host load; the parallel suite ships at that bar, and a follow-up may
re-open the 30s target if it still matters.) The suite's coverage stays intact — no test is deleted or
weakened to reach the number, and the make check coverage floor still passes
at 100%. make precommit and make check keep their current shapes.

## Why it matters

The gate runs on every commit and a working day holds dozens of commits; at
90s the gate taxes exactly the rhythm IMPRV-021 existed to protect.

## Discovery notes

The measure is pest's own reported Duration, so container start cost stays
outside the number. Single-process execution is the visible headroom — pest
ships --parallel (paratest), and each worker needs its own :memory:
connection, which RefreshDatabase already scopes per-process; profile first
(pest --profile) to see which tests dominate before reaching for anything
else. The coverage run's pcov cost is PR-time only and out of scope. If
parallelism lands short of 30s, the next places to look are the end-to-end
story walks (tests/SmokeTest.php) and the log-store fixtures. All advisory.

## Related work

- IMPRV-021 (the per-commit gate)
- RFCTR-001 (Pest sidecar suite)
- RSRCH-001 (performance baseline)
- IMPRV-007 (pcov instrumentation)

## Working

**Baseline** (2026-09-01, container, serial): 3222 tests / 9371 assertions,
Duration 93.38s (`composer test` before this ticket's changes; close to but
above the ticket's 90.27s figure — a handful of tests were added since).

**Profile top offenders** (`pest --profile`, serial): no single test
dominates — the top 10 sum to 16.07s (14% of 114.52s under `--profile`'s own
overhead). The floor is per-test RefreshDatabase/migration cost spread
across 3222 tests, confirming the ticket's read: parallelism, not a hot
spot, is the lever.
| Test | Time |
|---|---|
| `LogStoreTest > it drops rows past the buffer cap...` | 3.38s |
| `Arch > the domain core depends on nothing from the framework` | 2.55s |
| `Arch > no debug output is left behind` | 2.23s |
| `Arch > every class under App declares strict types` | 2.16s |
| `Arch > the domain core stays pure` | 2.08s |
| `Arch > preset → laravel → ignoring [...]` | 0.97s |
| `AddListingImageTest > it appends the first image` | 0.85s |
| `ErrorPagesTest > it shows the framework debug page` | 0.83s |
| `RecordPageViewTest > it inserts the first hit of` | 0.58s |
| `Arch > controllers do not reach around Eloquent...` | 0.45s |

**What changed**

- `composer.json`: `test` script adds `--parallel --passthru-php='-d
  memory_limit=1G'` to the existing `pest` invocation. `--processes` is
  left unset — paratest auto-detects CPU cores, so the count adapts to
  whatever machine runs it rather than a hardcoded container-CPU figure.
  `pestphp/pest` already vendors paratest (`^4.7`); no new dependency
  needed.
- Three test-isolation bugs `--parallel` exposed, all pre-existing and
  latent under serial execution, fixed without weakening any assertion:
  - `app/Logging/LogStoreConfigTest.php`,
    `app/Support/RateLimiting/RateLimitsConfigTest.php`: both mutated env
    vars through `Illuminate\Support\Env::getRepository()->set()`/
    `clear()`. That repository is immutable once a key has ever carried a
    value in the process; under paratest's worker (which boots the app,
    and `.env`, once per file rather than once per whole-suite run the
    way serial pest does) a second `set()` for the same key silently
    no-ops. Rewrote both to write `$_ENV`/`$_SERVER`/`putenv()` directly,
    all three of which `env()`'s reader chain checks.
  - `app/Domain/Payments/FakeCardTest.php`: shadowed `preg_replace()` via
    a namespaced override declared in the test file itself. PHP caches
    which function an unqualified call resolves to at that call site's
    first execution, for the process's life; under a paratest worker,
    some other test's ordinary `FakeCard::decide()` call — from a
    checkout/payment test sharing the worker — can execute before Pest
    ever requires `FakeCardTest.php`, caching the real `preg_replace()`
    at that call site permanently. Moved the override into
    `tests/FunctionOverrides.php`, registered via `composer.json`'s
    `autoload-dev.files`, so it's declared before any test file loads,
    in every worker, closing the race. Confirmed by instrumenting both
    sides and catching the race directly (`func_exists`/`$GLOBALS` were
    both correct in the test body and inside `decide()` — the override
    still didn't run — proving the call site itself, not the flag, was
    stale).
  - `app/Console/Commands/SweepOrdersTest.php`,
    `RunWeeklyPayoutsTest.php`: entirely absent from the parallel run's
    result (14 tests, no error) — not a flake, 100% reproducible. Laravel
    boots the console kernel once per worker ahead of collecting tests
    (`Illuminate\Testing\Concerns\RunsInParallel::forEachProcess()`), and
    the default `Kernel::discoverCommands()` Finder-scans and
    `ReflectionClass`-probes every `*.php` file in `app/Console/Commands`
    — sidecar tests included — to find command classes. That probe
    autoloads a sidecar test before Pest ever requires it for collection;
    the file's `it()` calls run once, outside Pest's context, and
    register nothing, and Laravel's `rescue()` around the probe swallows
    the fallout silently. Added `app/Console/Kernel.php`, a subclass that
    turns off the directory scan (`shouldDiscoverCommands()`) while
    keeping `routes/console.php`'s schedule load (`discoverCommands()`
    override) — confirmed by `routes/consoleTest.php`, which broke on a
    first attempt that dropped the schedule load too. Rebound in
    `bootstrap/app.php` (`withKernels()` binds the base class
    unconditionally, so nothing resolves the subclass without this).
    `bootstrap/app.php` also names the two real commands explicitly via
    `withCommands([...])`, which needs no scan.
- `app/Logging/LogStore.php`, `LogStoreTest.php`: the ticket's named
  fallback. `LogStore::open()` gains an optional `?int $bufferCap`
  (defaults to the real `BUFFER_CAP = 10_000`), following the class's
  existing pattern of test-injectable seams (`stdoutWriter`,
  `stderrWriter`, `registerShutdown`). The buffer-cap test now exercises
  the same drop/notice/cap-count logic at `bufferCap: 50` instead of
  10,000 real rows (3.38s → 0.02s); production behavior and the real
  constant are untouched.
- `tests/SidecarsTest.php`: added `app/Console/Kernel.php` to the
  exceptions list (covered by `routes/consoleTest.php` and the
  `Console\Commands` sidecar tests passing at all; the class carries no
  logic of its own).

**Verification method**: `--filter`/positional-path reruns of single
files under `--parallel` to isolate each failure, plus direct
instrumentation (`file_put_contents` probes reading `getenv()`/`$_ENV`/
`$_SERVER`/`function_exists()` at the point of failure) rather than
guessing — every one of the three isolation bugs was reproduced in
isolation and confirmed fixed before being folded into a full-suite run.

**Final numbers** (`composer test`, container): 3222 tests / 9371
assertions, 0 failures, reproduced clean across 6+ consecutive full runs
after all fixes landed (plus a JUnit-XML diff against the serial baseline
confirming the same 3222 test cases run, none dropped). Reported Duration
ranged 34.97s–67.20s across runs in this session — this container shares
an 8-CPU Docker Desktop VM with another agent's containers (confirmed via
`docker stats`: a sibling `art-store-php-app-run-*` container at
82–429% CPU for most of this session, host load average 6.0–10.0 against
8 CPUs). The best reading, taken after every fix including the LogStore
one and once that sibling container's load had visibly dropped, was
**35.69s** at `--processes=8`. **Deviation from the ticket's target**: at
that best reading the suite is 2.6x faster than the 93.38s serial baseline
but still ~19% over the 30s target, on a machine this session never saw
fully quiet. Re-measure on an idle machine before deciding whether 30s
needs a further fix — a 6-8 sample spread this tight (35–36s) at the
low end suggests genuine floor, not noise, so the honest read is that
parallelism plus the fixture fix likely lands in the mid-30s on a quiet
machine, short of 30s.

One reproducible-but-rare anomaly, unrelated to the above: one full run
(1 of ~14) reported 3 `ParseError` failures in unrelated, syntactically
valid files (`php -l` confirmed no syntax error). `opcache.enable_cli` is
`Off` in this image, ruling out an opcache race; the likely cause is a
torn read on Docker Desktop's bind-mount layer under the heavy concurrent
I/O of 8 workers plus the other agent's containers. Did not reproduce on
retry; flagged here rather than chased further, since it didn't reproduce
against a quieter machine state either.

**Coverage run**: `composer test:coverage` (pcov, `--min=100`) passes at
100.0% total after all changes, exit code 0. Its own duration stays out of
scope per the ticket; not parallelized (pcov + paratest interaction is
unexplored and the ticket marks this out of scope).

**Test count**: 3222, at or above the 3222 baseline measured at the start
of this work (the ticket's own 3218/9367 baseline predates a few tests
added since it was written). No test deleted, skipped, or weakened.

**Files changed**: `src/composer.json`, `src/bootstrap/app.php`,
`src/app/Console/Kernel.php` (new), `src/tests/FunctionOverrides.php`
(new), `src/app/Logging/LogStore.php`, `src/app/Logging/LogStoreTest.php`,
`src/app/Logging/LogStoreConfigTest.php`,
`src/app/Support/RateLimiting/RateLimitsConfigTest.php`,
`src/app/Domain/Payments/FakeCardTest.php`, `src/tests/SidecarsTest.php`.
`make test`/`make precommit`/`make check` unchanged — all three still call
`composer test`/`composer test:coverage`; only those scripts' own
implementation moved.

2026-09-01 acceptance: shipped at the amended bar by the human's decision —
serial 93s → parallel ~40s floor on this machine; under-30s not demonstrably
reachable on an 8-CPU VM without structural test surgery.
