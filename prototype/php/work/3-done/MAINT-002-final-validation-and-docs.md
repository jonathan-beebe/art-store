---
id: MAINT-002
type: maintenance
status: resolved
created: 2026-08-23
---

# MAINT-002: Final validation — analyzer at zero on app and tests, docs current

## Problem
After the refactor tickets land, the numbers in `README.md`, `docs/review.md`, and `docs/architecture.md` (471 tests, PHPUnit, no analyzer) will be stale, `phpstan.neon` still excludes `**/*Test.php` and omits `tests/` from `paths`, and nobody has run the whole thing from a clean checkout since FEAT-008.

## Goal
A reviewer cloning the repo runs one command and sees lint, analyzer, and tests all pass, and the docs tell the truth about what they are looking at.

## Outcome
- `phpstan.neon` analyses `app`, `database`, `routes`, and `tests` including the sidecar tests at `level: max` with zero errors and no `ignoreErrors`.
- `make check` passes from a clean checkout (`make down && make build && make check`), `make smoke` passes, `make fresh` seeds, and both sites render on the seeded data (screenshot or route walk noted in Working).
- `README.md`, `docs/architecture.md`, `docs/review.md` state the current test count, coverage, the analyzer and lint gate, Pest sidecars, policies, form requests, events and notifications, and components; `docs/review.md` "Known gaps" is updated for what the tickets closed.
- `work/journal.md` has every ticket's done entry.

## Why it matters
The prototype is judged against two others by someone reading docs first; stale numbers cost more than missing features.

## Discovery notes
- Adding `tests` to PHPStan paths was measured at +114 errors before the model docblocks; most fall out after MAINT-001. Whatever remains is a test asserting on something it has not proven exists.
- Docker Desktop disk was near full on 2026-08-22; `docker system df` before `make build`.

## Related work
- MAINT-001 through RFCTR-008, IMPRV-001, BUG-001, BUG-002

## Working

### Numbers

| Measure | Before | After |
| --- | --- | --- |
| PHPStan errors, `app` + `database` + `routes` (`*Test.php` excluded) | 0 | — |
| PHPStan errors, `app` + `database` + `routes` + `tests`, nothing excluded | 2915 | 0 |
| Tests | 721 (1600 assertions) | 733 (1643 assertions) |
| Line coverage | 100.0% | 100.0% |
| Pint | clean, 322 files | clean, 322 files |

`make check` exits 0 in 32.5s. `make smoke` 4.6s, `make fresh` 3.4s.

### The analyzer over the tests

Adding `tests` to `paths` and dropping the `*Test.php` `excludePaths` surfaced
2915 errors. They closed in two groups.

**Pest's own types (2915 → 350), no test code changed.** Pest reaches the test
case, the expectation API, and the arch DSL through traits and
`expect()->extend()`, and PHPStan follows neither. `src/phpstan/` holds five
stub files that name what Pest carries:

| Stub | What it declares |
| --- | --- |
| `pest-test-call.stub` | `Pest\PendingCalls\TestCall` — the class Pest declares as the scope of every test closure — mixes in `Tests\StorefrontTestCase` and answers `expect()`, `and()`, `preset()` |
| `pest-tap-proxy.stub` | `Pest\Support\HigherOrderTapProxy`, what a no-argument `test()` returns, mixes in the same class |
| `pest-functions.stub` | `@param-closure-this Tests\StorefrontTestCase` on `it`, `test`, `beforeEach`, `afterEach` |
| `pest-expectation.stub` | `Pest\Expectation` gains `toBeMoney()` and `toHaveStatus()`, the two expectations `tests/Pest.php` registers |
| `pest-refs.stub` | The classes the four stubs above name. PHPStan validates a stub file against a reflection provider that knows only PHP's own classes and stub-declared ones, so without this every `@mixin` and `@method` in them reads as an unknown class |

`Tests\StorefrontTestCase` is the deepest of the three base classes
(`Tests\TestCase` → `Tests\CommerceTestCase` → `Tests\StorefrontTestCase`), so
naming it once gives every sidecar the members its own base carries. The
fixture helpers on `CommerceTestCase` and `StorefrontTestCase` became `public`:
a file-level closure reaches them through `test()`, from outside the class.

**Test code (350 → 0).** Four kinds, all fixed by making the test assert on
what it has proven:

- A nullable read dereferenced: `first()` → `sole()`/`firstOrFail()`, `fresh()`
  → `refresh()`, `$model->relation->is(...)` → `$model->relation()->sole()->is(...)`.
- Higher-order expectation chains (`expect($order)->status->toBe(...)`) resolve
  to `mixed`; rewritten as `expect($order->status)->toBe(...)->and(...)`.
- `Route::getRoutes()` is a `RouteCollectionInterface`, so the two
  `GuardedRoutesTest` walks read `->getRoutes()` for the `Route[]`.
- Dynamic properties: `MarkShippedTest`'s `$this->paidOrder` became a
  file-level closure, and `SmokeTest`'s sixteen step closures moved inside the
  `it()` body, where `$this` is the test case and the protected assertions
  (`assertDatabaseHas`, `assertAuthenticated`) are in reach.

No `ignoreErrors`, no `@phpstan-ignore`, no baseline. `App\Models\Listing`
gained `@property-read` for the three `withEventCounts` aliases, matching the
`$tally` alias already documented there.

### Arch preset

`tests/Arch.php` no longer ignores all of `App\Http\Controllers` for the
`laravel` preset. Pest's preset offers no per-expectation `ignoring`, so the
list names the nine controllers whose route methods are action verbs
(`CartController`, `CheckoutController`, `FavoriteController`,
`OrderPaymentController`, `AccountController`, `NotificationController`,
`SignOutController`, and the two login controllers). Every other controller is
now held to `toHavePublicMethodsBesides`.

That change exposed a red the suite carried in: the preset's
`expect('App\Http\Requests')->toHaveMethod('rules')` fails on
`App\Http\Requests\Shop\ShopRequest`, the abstract storefront base, which
carries the visitor rather than rules. Confirmed against the unmodified
`tests/Arch.php`, so `make check` was already failing before this ticket. The
base class is now an `ignoring` entry.

### The `/seller` rule out of the controller

`MagicLinkVerificationController::destinationFor()` is gone.
`ActorType::allowsPath(string $path): bool` answers whether an actor belongs on
a path, and `LocalRedirect::resolve(?string $requested, ActorType $actor, string
$fallback, string $origin)` applies it after the local-target check, parsing
the path itself. The controller passes `$link->actor_type` and redirects. Both
have domain tests; the HTTP tests are unchanged and green.

### Clean-checkout proof

`docker system df` before the build: images 17.99 GB (3.28 GB reclaimable),
containers 7.32 GB, volumes 25.13 GB, build cache 26.95 GB, with 63 GB free on
the host — no disk pressure. `make down` 0.4s, `make build` 2.1s (every layer
cached; the Dockerfile is unchanged), `make check` **exit 0** in 32.5s
(Pint 322 files, PHPStan `[OK] No errors`, Pest 733 passed / 1643 assertions).
`make smoke` 2 passed / 77 assertions in 4.6s. `make fresh` re-migrated and ran
all four seeders in 3.4s.

### Route walk on the seeded data

`docker compose up -d`, then:

| URL | Code |
| --- | --- |
| `/` | 200 |
| `/?q=harbour` | 200 |
| `/?medium=Ceramics` | 200 |
| `/art/low-tide-at-dusk` | 200 |
| `/art/not-a-listing` | 404 |
| `/cart` | 200 |
| `/favorites` | 200 |
| `/orders` | 200 |
| `/login` | 200 |
| `/checkout` | 302 (empty cart → `/cart`) |
| `/account` | 302 (no verified customer → `/login`) |
| `/seller` | 302 (signed out → `/seller/login`) |
| `/seller/login` | 200 |
| `/seller/listings`, `/seller/orders`, `/seller/earnings`, `/seller/notifications` | 302 (signed out) |

The home page renders the seeded titles ("Portrait of a Welder", "Salt Flats,
Noon", …) and `/seller/login` renders its sign-in form. Authenticated pages are
covered by the HTTP tests. `make down` afterwards.

### Docs

- `README.md`: test count and coverage, the analyzer paragraph (paths now
  include `tests`, no exclusions, what `src/phpstan/*.stub` is for), the
  repository layout (`layouts/`, `partials/` are gone; components, policies,
  events, listeners, notifications and `phpstan/` are named), and Known gaps.
- `docs/architecture.md`: the fixture convention, the arch `ignoring` list, the
  coverage figure, the gate paragraph plus a new paragraph on analysing the
  sidecars, and the skills table (Pest, not PHPUnit).
- `docs/review.md`: an **Engineering quality** table at the top, current
  numbers throughout, and Known gaps trimmed to the seven that remain.
- `docs/identity.md`: the `/seller` redirect rule now names the domain that
  holds it.
- All 14 Mermaid blocks under `docs/` render with `minlag/mermaid-cli`.

### Journal

Every ticket in `work/3-done/` has a `done` line in `work/journal.md` — 21
tickets, checked one by one. Nothing missing, nothing edited.

### Left out

- `make build` ran against a warm cache. A `--no-cache` rebuild was not run:
  the Dockerfile is untouched by this ticket and the host had 63 GB free, so
  the layers it would rebuild are the ones already proven by the run.
- `README.md`'s "Suggested next steps" still names the placeholder-SVG seeder;
  it is gap 7 in `docs/review.md`, unchanged.
