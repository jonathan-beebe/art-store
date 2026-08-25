---
id: MAINT-006
type: maintenance
status: resolved
created: 2026-08-25
resolved: 2026-08-25
---

# MAINT-006: Container start skips work already done

## Problem
`docker/entrypoint.sh` runs three full Node process boots plus a Tailwind build before the server listens, on every container start: `node app/db/migrate.ts`, `node app/db/seed.ts` (an idempotent no-op after the first run), and `npm run --silent assets` unconditionally — the script's own comment notes CSS is "rebuilt on every start rather than only when public/app.css is missing". The Tailwind run scans all 71 templates each time. The Render `deploy` script (`package.json:25`) has the same shape: `migrate && seed && start`, three sequential cold boots. The compose healthcheck's 30-second `start_period` exists to absorb exactly this.

## Goal
Restarts reach "listening" without redoing work whose outputs already exist.

## Outcome
A `make down` / `make up` cycle with an up-to-date database and fresh CSS starts the server without a Tailwind rebuild and with fewer process boots; first-run and post-`fresh` behavior is unchanged; the production image (which builds CSS at image build) is untouched.

## Why it matters
The dev restart loop pays tens of seconds of fixed cost per cycle, most of it recomputing outputs that have not changed. Small, contained, and felt on every restart.

## Discovery notes
- The entrypoint already has the staleness idiom for `node_modules` (`-nt` checks); the same pattern gates the CSS rebuild on whether any template/asset input is newer than `public/app.css`.
- Migrate and seed can share one Node boot (a small CLI that calls both mains), dropping a process start from both the entrypoint and the deploy chain.

## Related work
- FEAT-013 (production image), BUG-008 (runtime asset handling)

## Working

- 2026-08-25 — re-validated. The entrypoint still runs three boots plus an
  unconditional `npm run assets` per start; `deploy` is still
  `migrate && seed && start`. Since the ticket was written, IMPRV-020 changed
  the assets output set: `npm run assets` now writes `public/app.css`, hashed
  copies of `app.css`/`app.js`, `.gz`/`.br` siblings of each, and
  `assets-manifest.json` (all gitignored). The staleness gate has to treat a
  missing manifest or missing hashed/compressed sibling as stale even when
  `app.css` looks fresh — a fresh clone otherwise serves pages that point at
  manifest paths that do not exist.
- Entrypoint scope confirmed: only the `dev` Dockerfile stage installs the
  entrypoint; `build` runs `npm run assets` at image build and `runtime` copies
  the built `public/` — the production image path stays untouched.
- Plan: `app/cli/prepare-db.ts` (print-routes CLI pattern) runs migrate then
  seed in one Node boot, skipping seed when migrate left `process.exitCode` at
  1; entrypoint and the `deploy` script both call it. Entrypoint gates the
  assets build on: manifest exists, every manifest-named hashed file plus its
  `.gz`/`.br` siblings exist, and nothing under `app/` (or `public/app.js`,
  or `public/app.css`) is newer than the manifest — the last file the build
  writes.
- Landed: `app/cli/prepare-db.ts` + `prepare-db.test.ts` (2 tests: one boot
  migrates and seeds a fresh temp DB — 6 sellers, 4 demo + 2 wizarding; a
  migrate failure leaves exit code 1, logs `migrate.run failed`, and writes no
  `seed.run` line); `deploy` script is
  `node app/cli/prepare-db.ts && npm run start`; `entrypoint.sh` gained
  `assets_current()` and calls `npm run --silent assets` only when it returns
  false; README First-run/Styling/tree text updated. `migrate`/`fresh`/`seed`
  npm scripts and make targets untouched.
- Verified in Docker (all via make targets / `docker compose run` against the
  rebuilt dev image — the first check ran against a container whose baked-in
  entrypoint predated the change, which looked like a gate failure until the
  image was rebuilt):
  - clean state (no `public` build outputs, no db): `make up` → healthy in 5s,
    full asset set present, db migrated + seeded, page serves the hashed
    stylesheet (200).
  - warm restart and `make down`/`make up`: healthy in 2–3s,
    `assets-manifest.json` mtime unchanged — rebuild skipped.
  - `touch` on an EJS template under `app/`: next start rebuilds (manifest
    mtime advances). Deleting one `.br` sibling: next start rebuilds and
    restores the set.
  - `make fresh`: stops the app, wipes + remigrates, reseeds (6 sellers),
    restarts healthy. The entrypoint's prepare-db run does the reseeding on
    the `docker compose run` the target issues; the explicit seed step then
    reports already-seeded — same shape as before the change.
  - `make image` builds; the runtime image ships the built `public/` set and
    the new deploy chain; `node app/cli/prepare-db.ts` in that image with the
    production env (COOKIE_SECRET, MAGIC_LINK_DELIVERY=outbox) migrates and
    seeds, exit 0. The entrypoint stays dev-stage-only — `build` compiles
    assets at image build, `runtime` copies them.
- Reviewer: accept, no functional defects; three fixes applied — two README
  passages still described the unconditional rebuild, and the failure test
  only proved the exit code (now also asserts `migrate.run failed` and the
  absence of any `seed.run` line via `captureLogLines`). Noted pre-existing,
  not blocking: `loadConfig` throws escape both mains uncaught.
- `make check` green: 2024/2024 tests (2022 baseline + 2), coverage
  99.38/95.58/99.45 against the 95/90 gate (the uncovered direct-invocation
  guard in the new CLI moves branches from the 95.88 baseline, same shape as
  the other CLIs).
