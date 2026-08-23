---
id: FEAT-013
type: feature
status: open
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
