---
id: IMPRV-008
type: improvement
status: open
created: 2026-08-24
---

# IMPRV-008: The entrypoint rebuilds the bundle on every container start

## Problem
`docker/entrypoint.sh` runs `npm run build` unconditionally, with a comment
saying Tailwind scans the Blade files so the CSS cannot be rebuilt only when
`public/build` is missing. The conclusion does not follow from the premise:
Tailwind scanning Blade means the build depends on the Blade files, not that
it has to run when nothing has changed.

The entrypoint is the image's `ENTRYPOINT`, so it runs on every
`docker compose run` as well as on `up`. Every `make test`, `make shell`,
`make seed`, `make routes`, `make fresh` pays for a full Vite build before
its own command starts. `make check` is `lint assets test`: `lint` bypasses
the entrypoint, `assets` pays it and then runs `npm run build` again as its
own command, and `test` pays it once more — **three builds per gate run**.

Measured (RSRCH-001 M3), against a 1.31 s no-op `docker compose run`:

| phase | cost |
|---|---|
| `npm run build` | 4.38 s |
| `php artisan migrate --force` | 1.58 s |
| `php artisan storage:link --force` | 1.04 s |
| M2 warm restart to first `200 /up` | 3.88 s |

## Goal
The bundle is built when its inputs have changed, and not when they have
not.

## Outcome
RSRCH-001 M2 (warm restart to the first `200 /up`, dependencies present,
nothing edited since the last start) drops from 3.88 s to **under 2 s**. A
second `make check` with no file edited between the two runs is measurably
shorter than the first — record both wall times. Editing a Blade file, a
file under `resources/`, or `package.json` and starting again rebuilds:
prove it by touching `resources/views/components/layouts/shop.blade.php`,
starting, and showing the build ran.

## Why it matters
It is the cost every agent lane and every local iteration pays, several
times per commit, for output that is already on disk and already correct.

## Discovery notes
A content hash over the build's real inputs — everything under
`resources/`, `vite.config.js`, `package.json`, `package-lock.json` — written
to a file beside the output (`public/build/.inputs-hash`, which is already
gitignored along with the rest of `/public/build`). The entrypoint builds
when the hash file is missing, when it disagrees, or when
`public/build/manifest.json` is missing, and skips otherwise. Be generous
about what counts as an input: a missed input means a stale bundle, which
costs far more than a redundant build.

`make assets` calls `npm run build` as its own command and must keep
building unconditionally — `docs/alignment.md`'s make table gives `assets`
the job of building CSS/JS, and that is the escape hatch when a hash is
wrong.

Leave `migrate --force` and `storage:link --force` alone. Both are already
idempotent and cheap, and both are what make a container that starts against
a half-set-up checkout come up correct.

The comment above the build in `docker/entrypoint.sh` states the current
reasoning and is part of the change.

## Related work
- FEAT-001 (dockerized Laravel foundation)
- MAINT-003 (common make vocabulary, check gate, CI)
- RSRCH-001 (M2, M3)
