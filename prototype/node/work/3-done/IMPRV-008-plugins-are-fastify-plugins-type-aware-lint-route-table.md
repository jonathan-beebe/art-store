---
id: IMPRV-008
type: improvement
status: resolved
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

## Working

### Verified against the code first

Every claim in the Problem section held. `app/app.ts` called the four modules
synchronously against the root instance, `printPlugins()` showed none of them,
`eslint.config.js` ran the untyped tier with no `project`, no route declared a
`schema`, and `printRoutes` was called nowhere. Fastify 5.12.1 also prints
`FSTDEP024` on every boot for `requestIdLogLabel` — reproduced in the baseline
test run before any change.

### Plugins

`app/plugins/root-plugin.ts` is new: `rootPlugin({ name, dependencies }, extend)`
wraps a body in a `FastifyPluginCallback` and sets `Symbol.for('skip-override')`
and `Symbol.for('plugin-meta')` with `Object.defineProperty`. That is the whole
of what `fastify-plugin` does that this needs, so the package stays out of
`package.json`.

Eight modules are now registered plugins: `errorPages`, `securityHeaders`,
`flashCookie`, `identityCookies`, `pageViewRollup`, `unreadMessages`, `eventBus`
(root scope, via `rootPlugin`) and `healthCheck` (a plain
`FastifyPluginCallback` — it registers a route, so encapsulation is right and it
gets its own subtree). `flashCookie` and `identityCookies` declare
`dependencies: ['@fastify/cookie']`, which fails the boot rather than the first
request if the cookie plugin ever moves below them.

Ordering is now explicit and load-bearing: **all eight register before the first
site**. A site inherits the root's hooks as they stand when its own context is
built, so a root hook added after `app.register(shopSite)` would never reach a
shop route. The comment in `app.ts` says so. `@fastify/cookie`, `formbody`,
`static` and `view` are all `fastify-plugin`-wrapped themselves, so they add no
context and can stay above the group — which is what lets the `@fastify/cookie`
dependency resolve.

`signInRoutes` and `unreadEventsRoute` returned anonymous arrows; both now
return a named `FastifyPluginCallback`, because `printPlugins()` otherwise
renders the first two lines of the function body as the node's name.

### Request helpers moved

`form-body.ts` and `id-param.ts` are `git mv`d to `app/http/` with their tests.
`app/http/` over `app/sites/shared/`: both are about reading an HTTP request,
and all four sites use them. 18 import lines rewritten. `formBody` now takes
`Pick<FastifyRequest, 'body'>` — it reads nothing else, and the narrower
parameter is what let its test double drop an assertion.

### `make routes`

`app/cli/print-routes.ts` exports `routeReport()` (the text) and
`main(out = process.stdout)` (writes it), with the `import.meta` guard
`run-payouts.ts` uses. It boots the real `buildApp` over `:memory:` with
`LOG_LEVEL=silent` and `UPLOADS_DIR=os.tmpdir()` (`@fastify/static` refuses a
root that does not exist), prints `printRoutes({ commonPrefix: false })` then
`printPlugins()`, and closes. `npm run routes` and `make routes` run it.
`print-routes.test.ts` asserts the report names a route unique to each of the
four sites, the four site plugins, and four of the root plugins.

Writing to a stream rather than through pino: the artifact is an ASCII tree a
person reads, and a pino line would JSON-escape every newline in it. `no-console`
is untouched — `process.stdout.write` is not `console`.

### eslint

`recommendedTypeChecked` with `projectService: true` and `tsconfigRootDir`.
First run: 2143 problems. Three decisions, each recorded in the config:

- **`no-floating-promises`** (1443 hits) — all but a handful were top-level
  `test(...)` calls. `allowForKnownSafeCalls` for `node:test`'s
  `test`/`it`/`describe`/`suite` rather than switching the rule off: the runner
  owns that promise, and every other floating promise still fails the build.
- **`unbound-method`** (670 hits) — all from `t.after(world.close)`. `TestApp`,
  `CommerceWorld`, `TravellingClock`, `SendMagicLinkDependencies` and
  `SignInRoutesOptions` declared function-valued properties with method
  shorthand, which is what made the rule read them as methods. Declaring them as
  properties (`close: () => Promise<void>`) is the honest shape and removed all
  670.
- **`require-await`: off.** The remaining hits were a Kysely `Driver`, a Fastify
  `preClose` hook, and test doubles for async ports — each legitimately `async`
  with nothing to await. Turning them into `return Promise.resolve(x)` would
  make fourteen call sites worse to read. The forgotten `await` this rule is
  reached for is `no-floating-promises`, which is on.

**`consistent-type-assertions`: `{ assertionStyle: 'as', objectLiteralTypeAssertions: 'never' }`, not `'never'`.**
`'never'` does not fit this tree. `cents()` in `core/money.ts` mints a branded
`Cents` and there is no way to do that without an assertion; `asRow<R>` in
`node-sqlite-dialect.ts` names the caller's row shape at the driver boundary,
which is the function's whole job. Banning the *object literal* assertion keeps
the half that matters — a literal claiming to be a type it does not satisfy,
silently missing fields — and leaves the two casts that carry information
visible. Three sites were fixed to satisfy it.

**`no-unnecessary-condition` was considered and left off.** It is a
`strictTypeChecked` rule, not part of the tier this ticket's Outcome names, and
it produces 144 hits — 140 of them defensive `?.` in test assertions. The three
in shipped code that the ticket calls out were real and are fixed by hand:
`flash.ts` and `identity.ts` both guarded `unsigned.value === null` after
Fastify's union had already narrowed it to `string`, and `logging.ts` had
`request.socket?.remotePort` on a non-nullable socket.

Other fixes the new tier surfaced: `negateCents` was `-amount` on a branded
number (`no-unsafe-unary-minus`) and is now `subtractCents(ZERO_CENTS, amount)`,
which also drops its `-0` ternary; `toCount` took `unknown` and stringified it
into an error message, and now takes the four things a driver actually hands
back; `migrator.ts` rethrew Kysely's `unknown` error directly and now wraps a
non-`Error` through `node:util`'s `inspect`; six test files stopped assigning
`any` from `response.json()` / `JSON.parse`; three hook factories moved from
`preHandlerHookHandler` to `preHandlerAsyncHookHandler`, which is the half of
Fastify's union they actually satisfy (`no-misused-promises`).

`npm run lint` is **fully green** — nothing was left red, and no rule the
previous config enabled was disabled.

### FSTDEP024

`requestIdLogLabel: 'requestId'` removed from `logging.ts`; the label is
Fastify's default `reqId` now. `logging.test.ts` drops its assertion on the
option and `app.test.ts` reads `line.reqId`. Fastify 5 offers `logController`
as the replacement; the default label needs no option at all, so nothing
replaced it.

### Deliberately left alone

- **`docker-compose.yml`'s duplicated `CMD`** — the ticket says it resolves in
  FEAT-013 and needs no action here.
- **Route-level `schema:`** — IMPRV-002's territory. `@fastify/formbody` is kept;
  the reasoning is a comment at its registration in `app.ts`.
- **`app/cli/*` and `app/db/{migrate,seed}.ts` entrypoints** — untouched.
- **`no-unnecessary-condition`'s 140 test-file hits** — see above.

### Files in another worker's territory

IMPRV-007 holds `customer-order.ts`, `shop/routes/checkout.ts`,
`seller/routes/listings.ts`, `admin/routes/payouts.ts` and
`shop/routes/checkout.test.ts`. Moving `form-body.ts`/`id-param.ts` made the
import line in the first four a compile error, so each got the same one-line
path rewrite and nothing else; `checkout.test.ts` got one `as { alert?: string }`
on a `JSON.parse`. No lint hit was left red in any of them.

### Test counts

Baseline at HEAD: 1492 pass, 0 fail. The shared tree carries two other tickets
mid-flight, so `npm run check` was verified in an isolated worktree at HEAD
carrying only this ticket's files: **exit 0, 1498 pass, 0 fail**, coverage
99.54 lines / 96.56 branches / 99.46 functions. Six new tests
(`root-plugin.test.ts` × 3, `print-routes.test.ts` × 3).

Two tests fail in the shared tree and neither is this ticket's:
`shop/customer-order.test.ts` "someone else's order still answers not found"
(the test signs in twice with the same default email, so owner and stranger are
one customer) and `shop/routes/home` "a pagination link repeats the search that
led to it" (an `&` / `&amp;` mismatch in a template). Both are IMPRV-007 files
written minutes before the run.
