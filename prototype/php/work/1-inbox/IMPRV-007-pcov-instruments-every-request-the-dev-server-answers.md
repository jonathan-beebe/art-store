---
id: IMPRV-007
type: improvement
status: open
created: 2026-08-24
---

# IMPRV-007: pcov instruments every request the dev server answers

## Problem
The Dockerfile installs pcov and enables it with no settings, so
`pcov.enabled` takes its default of 1 for every PHP process in the image —
including the `php -S` workers behind `artisan serve`. pcov exists to
measure line coverage during `composer test`; the rest of the time it is
paying to instrument code nobody is measuring.

Measured (RSRCH-001 M5), a `GET /` batch of 60, minimum of four alternating
rounds against a control server on the same host:

| ini | ms CPU/req on `/` |
|---|---|
| as shipped | 24.7 |
| `pcov.enabled=0` | **16.6** |

That is a third of the CPU of every page the prototype serves, spent on
coverage data that is discarded.

## Goal
Coverage instrumentation runs when coverage is being collected, and not
otherwise.

## Outcome
RSRCH-001 M5 on `/` drops from 24.7 to **at or below 18 ms CPU/req**
(minimum of four alternating rounds). `make test` still reports 100.0 % of
lines over 1827 tests and still fails the `--min=100` gate when a line goes
uncovered — verify by measurement, not by assumption: a silently disabled
pcov reports 0 % and the gate refuses, so a green coverage run is itself the
proof.

## Why it matters
It is the largest per-request cost in the prototype and the change is
confined to the image and one composer script. Nothing about the
application moves.

## Discovery notes
`pcov.enabled=0` goes in the image, as an ini file under
`/usr/local/etc/php/conf.d/` written by the Dockerfile beside the
`docker-php-ext-enable pcov` that turns the extension on. It has to be an
ini file rather than a `-d` flag on the serve command: `artisan serve` execs
`php -S` as a child and `ServeCommand::serverCommand()` passes it no `-d`
flags, so a flag on the parent never reaches the workers.

`composer test` and `composer test:coverage` in `src/composer.json` then
turn it back on for their own run, next to the `-d memory_limit=1G` both
already carry.

Do not enable OPcache while here. RSRCH-001 measured it: no effect on its
own, and worse in combination.

The Dockerfile comment above the pcov line should say what the setting is
for, in the same voice as the `gd` comment above it.

## Related work
- MAINT-001 (static analysis and lint gate)
- MAINT-003 (make vocabulary, the check gate)
- RSRCH-001 (M5)
