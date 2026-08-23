---
id: IMPRV-008
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-008: Plugins are Fastify plugins, type-aware lint, printed route table

## Problem
`app/app.ts:66-69` calls `addFlash(app)`, `addIdentity(app)`, `addPageViewRollup(app)`, `addUnreadMessages(app)` synchronously against the root instance rather than through `app.register(...)`. Defined at `app/plugins/flash.ts:34`, `app/plugins/identity.ts:50`, `app/plugins/page-views.ts:13`, `app/plugins/unread-messages.ts:17`. They work — root decorators and root hooks are what each needs — but the ordering relative to `app.register(fastifyCookie, …)` on the line above is implicit, none can declare a dependency, and `app.printPlugins()` shows none of them in the tree. `app/plugins/form-body.ts` and `app/plugins/id-param.ts` are plain request helpers with no Fastify involvement at all, sitting in the same directory as the four real plugin candidates.

`src/eslint.config.js:1-14` extends `tseslint.configs.recommended`, not `recommendedTypeChecked`, and sets no `languageOptions.parserOptions.project`. The rules that need type information — `no-floating-promises`, `no-misused-promises`, `await-thenable`, `require-await`, `no-unnecessary-condition`, `no-unnecessary-type-assertion`, `consistent-type-assertions`, `no-non-null-assertion` — are unavailable. This is an all-async codebase: Kysely `.execute()` everywhere, async route handlers, and `app/server.ts:22` already carries a hand-written `void app.close()`, which is exactly the annotation `no-floating-promises` exists to require. As configured, eslint catches little beyond two complexity gates that `tsc --noEmit` does not already catch.

No route anywhere declares `schema: { body, querystring, params }`. `grep -rn "schema:"` over `app/` returns only zod-internal parameter names, never a Fastify route option — the ajv serializer and validator are never engaged, and `@fastify/formbody` is kept with a documented tradeoff (finding 14 in 01-deps-platform.md: a five-line `URLSearchParams` parser would cover every current form, but repeated/nested keys are handled today and would need re-deciding).

`printRoutes` exists on the installed Fastify 5.12.1 (`node_modules/fastify/types/instance.d.ts:683`) and is called nowhere. The app registers 68 route handlers across four encapsulated site plugins with no printed artifact of that structure.

`docker-compose.yml:14` restates the Dockerfile's `CMD` (`["node","--watch","app/server.ts"]`) verbatim — the same fact stated in two places that can drift.

## Goal
Every root-level cross-cutting feature is a registered Fastify plugin visible in the plugin tree, eslint's type-aware tier catches the async hazards the codebase's own shape invites, and the route table is a printed, verifiable artifact rather than an unconfirmed claim.

## Outcome
- Flash, identity, page-view rollup, and unread messages are registered plugins visible in `printPlugins`, with explicit ordering.
- `form-body.ts` and `id-param.ts` live outside `app/plugins/` (or are deleted, if IMPRV-002 supersedes them).
- eslint runs `recommendedTypeChecked` with `projectService`, and the suite is clean under `no-floating-promises` / `no-misused-promises`.
- `make routes` prints `app.printRoutes()`.

## Why it matters
The doctrine states cross-cutting features are encapsulated plugins; a decorator or hook applied outside `register()` is invisible to Fastify's own introspection and cannot declare a dependency on `fastifyCookie` being registered first, which today is an ordering fact enforced only by file position. `printRoutes()` printed via `make routes` is, per the showcase review (07-showcase.md #9), the single clearest artifact a reviewer can read in ten seconds that proves the "three sites, one deployable, features as encapsulated plugins" claim — it uses the framework's own introspection rather than a hand-maintained route table. Switching eslint to the type-aware tier closes the gap between what the doctrine claims eslint enforces and what it actually enforces: the unreachable `undefined` guard in `declineMessage`, the `?? []` widening on a total `Record`, and the four remaining non-`as const` casts in the tree are exactly what `no-unnecessary-condition` and `consistent-type-assertions` exist to catch, and none of them is caught today.

## Discovery notes
- Wrap each of the four root-level modules as a `FastifyPluginCallback` and `app.register(...)` it, which makes boot order explicit and keeps the plugin tree honest. Move `form-body.ts` and `id-param.ts` out of `app/plugins/` since they are pure request helpers with no plugin shape — or drop them entirely if IMPRV-002's typed-validation work removes their reason to exist; land IMPRV-002 first if both are in flight, so this ticket does not move code IMPRV-002 is about to delete.
- Switch to `tseslint.configs.recommendedTypeChecked` with `parserOptions: { projectService: true }`. Expect the first run to surface floating-promise and `no-misused-promises` hits in Fastify hook registrations; fix or deliberately downgrade individual rules rather than reverting wholesale. The cost is lint wall time, already absorbed by `make test`.
- A five-line `app/cli/print-routes.ts` that builds the app over an in-memory DB, awaits `app.ready()`, and logs `app.printRoutes({ commonPrefix: false })`, plus a `make routes` target. Optionally paste the output into `docs/architecture.md` with a `make docs-check` comparison — treat that as a stretch, not required for this ticket's outcome.
- The `@fastify/formbody` dependency question (01-deps-platform.md finding 14) is a judgment call already resolved in the findings toward keeping it — record the reasoning in a comment next to `formBody` in `app/plugins/form-body.ts` (or wherever it lands) rather than revisiting the decision here.
- Once `docker-compose.yml`'s production `CMD` work lands (FEAT-013), the compose `command:` overriding the image's default `CMD` with `--watch` is the correct division and the current duplication resolves on its own — no separate action needed here beyond noting it.

Files expected to touch: `app/app.ts`, `app/plugins/flash.ts`, `app/plugins/identity.ts`, `app/plugins/page-views.ts`, `app/plugins/unread-messages.ts`, `app/plugins/form-body.ts`, `app/plugins/id-param.ts`, `eslint.config.js`, new `app/cli/print-routes.ts`, `Makefile`, `package.json`.

Land after FEAT-013 (production image) and IMPRV-002 (typed validation, which may delete `form-body.ts`/`id-param.ts`) if both are in flight, so this ticket's plugin-directory cleanup does not conflict with work already moving those files.

## Related work
- 05-shell-ops.md: "The files in `app/plugins/` are not Fastify plugins", "`docker-compose.yml` restates the image's `CMD`"
- 01-deps-platform.md: "`typescript-eslint` runs without type-aware rules", "`@fastify/formbody` for flat forms" (decision: keep, record why in a comment)
- 02-types-boundaries.md: "ESLint runs the untyped tier, so the rules that police this dimension are off"
- 07-showcase.md: opportunity #9, "`make routes` printing `app.printRoutes()`"
- Related tickets: FEAT-013 (production image, `CMD` duplication resolves there), IMPRV-002 (may delete `form-body.ts`/`id-param.ts`)
