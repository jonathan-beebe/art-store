---
id: IMPRV-008
type: improvement
status: resolved
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

## Working

### What changed

`docker/entrypoint.sh` computes a content hash over the bundle's inputs and
compares it with the hash recorded beside the bundle. It builds when the
manifest is missing, when the hash file is missing, or when the two hashes
disagree, and writes the hash only after `npm run build` returns. Everything
else in the entrypoint is untouched: `migrate --force` and
`storage:link --force` still run on every start.

The hash:

```
find resources app bootstrap config routes \
    vite.config.js package.json package-lock.json composer.lock \
    -type f -exec sha256sum {} + | LC_ALL=C sort | sha256sum | cut -d' ' -f1
```

It covers contents and paths, `sort` makes it independent of the order `find`
walks the tree, and nothing in it reads an mtime. `sha256sum`, `find` and
`sort` are coreutils, already in `php:8.3-cli` (verified: GNU coreutils 9.7).
It costs 0.10–0.18 s in the container.

`make assets` is unchanged and still runs `npm run build` unconditionally.

### The hash file lives at `public/.vite-inputs-hash`, not in `public/build/`

`vite build` empties its output directory. Verified in the container: a
`public/build/.probe-file` written before a build is gone after it. A hash
file inside `public/build` would therefore be deleted by `make assets`, and
the next `docker compose run` would rebuild — the gate would keep paying two
builds. Beside the directory it survives, so a `make check` that changes
nothing pays exactly one build (the one `make assets` is there to run).
`/public/.vite-inputs-hash` is added to `src/.gitignore`.

### What counts as an input, and why

| input | why |
|---|---|
| `resources/` | the CSS entry point and every Blade template |
| `app/`, `bootstrap/`, `config/`, `routes/` | Tailwind v4 auto-detects sources across the project, so a class name in a PHP string is an input; none is there today, and the hash is what makes that safe to add |
| `vite.config.js` | decides what Vite produces |
| `package.json`, `package-lock.json` | pin the toolchain that produces it |
| `composer.lock` | pins the vendor pagination views `resources/css/app.css` names with `@source` |

Deliberately left out: `storage/framework/views`, the other `@source` target.
It is a cache compiled from `resources/views`, so its classes are already
covered by hashing the Blade sources, and which pages happen to have been
rendered would churn the hash without changing the output. Also out:
`vendor/` and `node_modules/` themselves — `composer.lock` and
`package-lock.json` pin both, at a fraction of the hashing cost.

### Numbers

M2 warm restart to the first `200 /up`, three samples each side:

| | samples | median |
|---|---|---|
| before | 6.19 s, 4.73 s, 4.14 s | 4.73 s |
| after | 1.77 s, 1.87 s, 1.76 s | 1.77 s |

`make check` wall time (`/usr/bin/time -p`), nothing edited between runs:

| run | wall |
|---|---|
| before | 104.42 s |
| after, first | 98.44 s |
| after, second | 91.39 s |

Both entrypoint invocations in each post-change run logged
`bundle inputs unchanged, skipping build`: three Vite builds per gate run
became one, the one `make assets` runs as its own command.

`make check` green: 1827 tests passed, 4946 assertions, 100.0 % lines.

### Rebuild-when-changed proof

```
touch src/resources/views/components/layouts/shop.blade.php
  -> entrypoint: bundle inputs unchanged, skipping build
printf '\n' >> .../shop.blade.php
  -> entrypoint: bundle inputs changed, building
git checkout -- .../shop.blade.php
  -> entrypoint: bundle inputs changed, building
  -> (next run) entrypoint: bundle inputs unchanged, skipping build

package.json edited      -> entrypoint: bundle inputs changed, building
package.json reverted    -> entrypoint: bundle inputs changed, building
  -> (next run) entrypoint: bundle inputs unchanged, skipping build
```

An mtime-only touch does not rebuild; a one-character content change does,
and so does reverting it.

`rm -rf src/public/build` with the hash file still present rebuilds (the
manifest is gone). Removing both and starting from nothing rebuilds, and the
page renders styled: `curl -s http://localhost:8000/` links
`build/assets/app-CdKlGM09.css`, which serves 200 with 46 020 bytes of
Tailwind 4.3.3 output.

### Left alone

- `make assets` still builds unconditionally. It runs behind the entrypoint,
  which writes the hash before the build command runs, so the hash is current
  after it either way.
- `migrate --force` and `storage:link --force`, per the ticket.
