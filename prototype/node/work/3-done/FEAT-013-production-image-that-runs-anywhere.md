---
id: FEAT-013
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-013: A production image that runs anywhere

## Problem
`Dockerfile:1-25` is a single stage. It `COPY`s exactly one file, `docker/entrypoint.sh` — no application source, no `package.json`, no `npm ci`. Installation happens at container start into the bind mount:

```
if [ ! -d node_modules ] || [ package-lock.json -nt node_modules ]; then npm ci; fi
```

`npm ci` with no `--omit=dev` installs all 263 packages; the lockfile splits 89 production against 174 development, so eslint, typescript, typescript-eslint, and platform-specific prebuilt binaries all land in the running container. The entrypoint then runs migrations, seeds demo data, and rebuilds the Tailwind stylesheet on every start. `CMD ["node", "--watch", "app/server.ts"]`. There is no `USER` directive — the container runs as whatever uid `docker-compose.yml` injects (`user: "${UID:-1000}:${GID:-1000}"`), which works only because the source arrives through a host bind mount. There is no `NODE_ENV`.

`docker/entrypoint.sh:14-15` and `Makefile:47-48` claim seeding adds operators only; `db/seed.ts:11-18` in fact seeds full demo data as well as admins, so the comment and the Makefile both misstate what a production-flavored boot would do.

`docker-compose.yml:14` restates the image's `CMD` verbatim (`["node","--watch","app/server.ts"]`) — the two will drift against each other since nothing keeps them in sync.

`package.json:1-5` declares no `engines` field, and no `.nvmrc`/`.node-version` exists in `prototype/node/`. The code cannot run below Node 22.18 (unflagged type stripping, `import.meta.dirname`, `node --test` with a glob argument), and two transitive deps already assert the floor (`better-sqlite3` `{node: ">=22"}`, `kysely` `{node: ">=22.0.0"}`) while the app itself states nothing machine-readable.

No `.dockerignore` exists. The build context is the whole of `prototype/node/`, including `src/node_modules` (263 packages with prebuilt binaries for eight platforms each), the SQLite files, `src/public/uploads/`, `docs/`, `work/`, and `__local__/`. Today the Dockerfile `COPY`s only the entrypoint, so nothing lands in the image, but the daemon still tars and transfers the full context on every `make build`.

The Tailwind stylesheet is generated at container start (`npm run --silent assets` in the entrypoint, run after `npm ci`, `migrate`, `seed`), not at image build — so start-up time scales with the Tailwind scan and a container can start with no stylesheet if that step fails.

## Goal
An image exists that runs the app in production with no bind mount, no dev dependencies, and no host-supplied uid.

## Outcome
- `make image` builds a multi-stage image: a builder stage runs `npm ci` and the asset build; a runtime stage has production dependencies only, `USER node`, `NODE_ENV=production`, a `HEALTHCHECK`, and `CMD node app/server.ts`.
- The image serves the app with a declared volume for storage and uploads.
- `make up`'s dev flow is unchanged.
- `.dockerignore` and `engines` are present.
- `docker/entrypoint.sh` and `Makefile` comments state truthfully what seeding does.

## Why it matters
"Deployment: container that runs anywhere: multi-stage build, non-root user, production deps only, config from env" is a stated doctrine line this image does not meet today. Neither `prototype/rails` nor `prototype/php` has a production image either — every Dockerfile in the repo is single-stage with no `USER` directive, so this is uncontested ground for whichever prototype claims it first. `prototype/rails`'s README brags "no Node in the image"; `prototype/php` installs Node 20, Composer, and Vite in its own image. Once FEAT-012 drops `better-sqlite3`, the Node entry needs no compiler toolchain at all in its production stage — a stronger position than either competitor's.

## Discovery notes
Add a second target rather than rewriting the existing image: keep today's image as the `dev` target so `make up` and the bind-mount loop stay exactly as they are, and add a `runtime` target that `COPY`s `package*.json`, runs `npm ci`, copies source, runs `npm run assets`, then a slim stage on `node:24-bookworm-slim` (or later, per FEAT-012's pinned-tag note) that runs `npm ci --omit=dev`, copies `app/` and the built `public/`, declares `USER node`, sets `NODE_ENV=production`, and runs `node app/server.ts` with no `--watch` and no seeding. The compose file selects the target via `build.target`.

Add `.dockerignore` covering `src/node_modules`, `src/storage/*.sqlite3*`, `src/public/app.css`, `src/public/uploads`, `src/coverage`, `docs`, `work`, `__local__`, `.git` — mirror and extend `prototype/node/.gitignore`.

Add `"engines": { "node": ">=24" }` to `package.json` so `npm ci` and a CI runner can enforce the floor machine-readably rather than only in README prose.

`DATABASE_FILE` defaults to a relative path inside the image (`storage/development.sqlite3`); the runtime stage needs a declared `VOLUME` or an env override, and `storage/` and `public/uploads/` must be writable by the `node` user — say so in the README rather than shipping an image that crashes on first write.

Once the image's `CMD` is `node app/server.ts` in production, `docker-compose.yml`'s `command:` overriding it with `--watch` for the dev target is the correct division of responsibility, and the duplicate-`CMD` finding resolves on its own without a separate fix.

Files expected to touch: `Dockerfile`, `docker-compose.yml`, `Makefile`, new `.dockerignore`, `docker/entrypoint.sh` (comment corrections), `package.json` (`engines`), `README.md` (a Deployment section).

This ticket depends on FEAT-011 (its `HEALTHCHECK` targets `/health`) and FEAT-012 (dropping `better-sqlite3` removes the reason a builder stage would need a compiler toolchain) landing first — building this image before either lands means adding a compiler layer and a placeholder healthcheck that both get torn out again.

## Related work
- 01-deps-platform.md — "The Docker image is a development image with no production path" (finding 1)
- 01-deps-platform.md — "No `.dockerignore`" (finding 13)
- 01-deps-platform.md — "`package.json` declares no `engines`" (finding 3)
- 05-shell-ops.md — "The container image is a dev image with no production path"
- 05-shell-ops.md — "`docker-compose.yml` restates the image's `CMD`"
- 06-tests-views.md — "Tailwind is rebuilt on every container start, and there is no production image"
- 07-showcase.md — showcase opportunity #3 (multi-stage, non-root, production-deps Dockerfile)
- Depends on FEAT-011 and FEAT-012

## Working

Re-validated against current code: FEAT-011 (`/health`) and FEAT-012
(`node:sqlite`, no compiler toolchain) had already landed, so the ticket's
own precondition — build this after both — is satisfied. `docker-compose.yml`
already carried a `healthcheck:` (added by another ticket in flight); left
it untouched per instructions and only added `build.target: dev`.

**Changed:**
- `Dockerfile` — rewritten as three named stages on one `base`:
  `FROM node:24.19.0-bookworm-slim` (pinned; `docker pull node:24-bookworm-slim`
  resolves to `v24.19.0` today, ≥ the 24.15 floor where the `node:sqlite`
  `ExperimentalWarning` stops printing). `dev` is byte-for-byte the old
  single-stage image (entrypoint, `HOME=/tmp`, `--watch` CMD) so `make up`'s
  behavior is unchanged — verified by inspecting the built image's
  Entrypoint/Cmd/Env and diffing `docker compose config`. `build` runs
  `npm ci` (full deps, for the Tailwind CLI) and `npm run assets` once, at
  build time. `runtime` runs `npm ci --omit=dev`, copies `app/` and the
  built `public/app.css` `--chown=node:node`, sets `NODE_ENV=production`,
  `DATABASE_FILE`/`UPLOADS_DIR` defaults pointing at `storage/` and
  `public/uploads/` (created and chowned to `node`), declares both as
  `VOLUME`s, runs as `USER node`, carries a `HEALTHCHECK` against `/health`
  (same `node -e fetch` form as the compose healthcheck, since the image has
  no `curl`), and `CMD ["node", "app/server.ts"]` — no `--watch`, no
  entrypoint, no seeding.
- `docker-compose.yml` — `build: .` → `build: { context: ., target: dev }`.
  Nothing else touched.
- `.dockerignore` (new) — mirrors and extends `.gitignore` (`src/node_modules`,
  `src/coverage`, `src/public/app.css`, `src/public/uploads`, the SQLite
  files) plus `docs/`, `work/`, `__local__/`, `.git/`.
- `Makefile` — added `image` (`docker build --target runtime -t art-store-node .`)
  and `run-image` (`docker run --rm -p 4100:4000 art-store-node`) targets
  plus their names in `.PHONY`; fixed the `seed` target's comment (was
  "adds the platform operators" only — `seed.ts` seeds admins *and* the full
  demo catalog, and both halves are idempotent). Did not touch `fresh`
  (owned by a concurrent worker).
- `docker/entrypoint.sh` — fixed the comment above `node app/db/seed.ts`
  to state it seeds admins and demo data, both idempotent (was "reference
  data, not demo data").
- `src/package.json` — added `"engines": { "node": ">=24.15" }` only (the
  16-char string-only comment field and everything else untouched).
- `README.md` — new "Deployment" section between Health and Seeded accounts:
  the three targets, `make image`/`make run-image` (and their raw `docker`
  equivalents), the explicit `node app/db/migrate.ts` step (the runtime
  image never seeds), the two volumes and their env-var defaults, a pointer
  to the Configuration table for the full env var list. Updated the Layout
  tree's `Dockerfile`/`docker-compose.yml`/`docker/entrypoint.sh`/`Makefile`
  one-liners for the new stages/target/dev-only-entrypoint/new targets, and
  added `.dockerignore` to the tree.

**Verified (Docker 29, all containers/images removed after except the
final `art-store-node` image):**
- `docker build --target runtime -t art-store-node .` from `prototype/node` —
  succeeds.
- `docker run --rm art-store-node node --version` → `v24.19.0` (≥ 24.15).
- `docker run --rm art-store-node id -u` → `1000` (not root).
- `docker run --rm art-store-node ls node_modules | grep -c eslint` → `0`.
- `docker run --rm -v <vol>:/var/www/src/storage -e COOKIE_SECRET=...
  art-store-node node app/db/migrate.ts` — all 10 migrations apply cleanly
  against a fresh volume.
- `docker run --rm -p 4100:4000 -v <storage-vol> -v <uploads-vol>
  -e COOKIE_SECRET=... art-store-node` then `curl http://127.0.0.1:4100/health`
  → `200 {"status":"ok","checks":{"database":"ok","migrations":"current"},...}`;
  the container's own `HEALTHCHECK` also reports `"Status":"healthy"` after
  the second probe. `COOKIE_SECRET` was supplied defensively; `config.ts` on
  this branch still defaults it (BUG-006, which will require it, is a
  concurrent in-flight change, not yet landed as of this run).
- `docker build --target dev -t art-store-node-dev .` — succeeds;
  `docker inspect` shows `Entrypoint=[entrypoint] Cmd=[node --watch
  app/server.ts]`, matching the pre-change image.
- Image size: **289MB** (`docker images art-store-node` → `289MB`; base
  `node:24.19.0-bookworm-slim` alone is 248MB, so the 87 production packages
  plus app code and the built stylesheet add ~41MB).

**Left alone / could not verify:**
- `npm run check` (typecheck, lint, coverage) is currently red on `main`
  of this worktree for reasons outside this ticket's territory: BUG-006 is
  mid-flight and has added `environment`/`publicUrl`/`trustProxy`/
  `secureCookies`/`showsDebugMagicLinks` to `AppConfig` in `app/config.ts`
  without yet updating `app/test/build-test-app.ts` to match (a `tsc`
  error) or trimming `loadConfig`'s complexity back under the eslint limit
  (a lint error), and one route test (`home.test.ts`) currently expects a
  `role="alert"` debug banner that isn't rendering yet. None of the three
  touch any file in this ticket's territory or in the diff above; confirmed
  via `git diff --stat` that `config.ts` and `build-test-app.ts` are
  modified-but-uncommitted (another worker's in-progress edit), not files
  this ticket changed. `npm test` (no coverage gate) on the current tree:
  1376 run, 1375 pass, 1 fail (the `home.test.ts` case above) — the same
  single pre-existing failure, unrelated to this ticket's diff. Did not
  attempt to fix any of it; it is BUG-006's territory. The orchestrator
  should re-run `npm run check` once BUG-006 lands.
- Did not add a compiler toolchain or a placeholder healthcheck (the ticket
  flagged both as unnecessary once FEAT-011/FEAT-012 land, and they had).
- Did not touch the Configuration table in README (another worker's
  in-flight addition per the brief) — the new Deployment section points at
  it by reference instead of restating it.
