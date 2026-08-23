---
id: RFCTR-001
type: refactor
status: resolved
created: 2026-08-23
---

# RFCTR-001: Pest-style sidecar suite with datasets and architecture rules

## Problem
All 92 sidecar test files are PHPUnit classes with `test_` methods. Repeated shapes are copy-pasted instead of tabulated: the seller-guard redirect is ten byte-equivalent tests across `app/Http/Controllers/Seller/*Test.php` (e.g. `DashboardControllerTest.php:15-18`, `ListingControllerTest.php:15-18`, `ShipmentControllerTest.php:15-21`) and `Shop/AccountControllerTest.php:33-38`; `app/Domain/Orders/OrderStatusTest.php:14-60` and `:118-164` are 14 one-row methods over the transition table; `app/Domain/Payments/FakeCardTest.php:9-46` restates the four-row card table from `docs/architecture.md` as four methods plus three asserts in one method; `app/Domain/Auth/LocalRedirectTest.php:13-61` is ten `assertSame($expected, resolve($input))` methods; `app/Domain/Money/MoneyTest.php:65-122`, `app/Domain/Escrow/PayoutPeriodTest.php:10-32`, `ListingControllerTest.php:103-157` and `ListingStatusControllerTest.php:41-74` follow the same pattern. Multi-property checks are bare `assertSame` chains (`CheckoutControllerTest.php:48-53`, `RunWeeklyPayoutTest.php:23-27`). Nothing encodes the layer rules in `docs/architecture.md` as executable checks; `app/Domain/Listings/ListingSlug.php:5` imports `Illuminate\Support\Str` into the core unnoticed. Four near-identical "build a delivered fulfillment" helpers exist (`RunWeeklyPayoutTest.php:94-108`, `PayoutControllerTest.php:80-89`, `DeliveryConfirmationControllerTest.php:38-46`, `ConfirmDeliveredTest.php:61-70`).

## Goal
The test suite reads as a specification: one `it()` per behavior, tables as datasets, expectation chains, and the architecture rules enforced by the suite itself.

## Outcome
- Every sidecar under `app/`, `database/`, `routes/`, and `tests/SmokeTest.php` is a Pest file (`it()`/`test()` functions, `beforeEach` instead of `setUp`); no `extends TestCase` classes remain outside `tests/*TestCase.php`.
- Repeated input/output shapes listed above are datasets; the set of seller-guarded routes is asserted from one dataset derived from the route table so a new guarded route is covered without a new test.
- Multi-field assertions on models and value objects use expectation chains; custom expectations exist where the same chain repeats (money in cents; a model's fresh status).
- `tests/Arch.php` enforces: `App\Domain` uses nothing from `App\Models`, `App\Http`, `App\Actions`, `App\Console`, `Illuminate\Database`, or facades, and calls no clock/random functions; every class under `App\Actions` is final and invokable; controllers do not use the `DB` facade; no debug functions anywhere; `env()` only in `config/`; every file declares strict types; plus Pest's `laravel` and `security` presets. The suite is green with those rules, which means `ListingSlug` no longer depends on `Illuminate\Support\Str` (or the rule names that one allowed exception explicitly).
- A test asserts every non-abstract class under `app/` has a sidecar test file, and it passes.
- The four delivered-fulfillment helpers collapse into one shared helper.
- Test count and assertions are at or above today's 471 / 1101; coverage is not lower than 98% lines.

## Why it matters
The brief scores the prototype on test quality in Pest style; the suite is the most visible artifact a reviewer opens after the README.

## Discovery notes
- Named datasets declared in `tests/Pest.php` do not resolve from sidecars under `app/` (scope is `tests/`; see `vendor/pestphp/pest/src/Repositories/DatasetsRepository.php:182-192`). Inline `->with([...])` arrays and file-local `dataset()` calls at the top of a sidecar both work. Do not build a shared dataset library.
- No data providers, mocks, or attributes exist in the suite; the only `setUp`/`tearDown` pair is `PayoutControllerTest.php:16-28`.
- Keep `tests/CommerceTestCase.php` and `tests/StorefrontTestCase.php` as classes bound via `pest()->extend()` (their helpers need `$this`); `moment()` is the one static helper suited to `tests/Helpers.php`.
- Suggested conversion order: `app/Domain/**` first (no `uses()`), then the `Auth` controllers (verifies the `in()` binding), then actions and the rest; `SmokeTest` stays one `it()` with file-local step closures.
- Work can be split by directory among parallel agents: (a) `app/Domain` + `app/Support`; (b) `app/Actions` + `app/Console` + `app/Models` + `database`; (c) `app/Http` + `tests/`. `tests/Pest.php` is written by MAINT-001 and must not be edited concurrently.
- `ListingSlug` can stay pure by inlining the transliteration (`Str::slug` is pure; the issue is the dependency).

## Related work
- MAINT-001

## Working
- Fixture bindings: a general fixture used across files lives as a protected
  method on `Tests\CommerceTestCase` (`cartWithOneListing()`,
  `paidOrderWithTwoSellers()`, and the pre-existing `shippedFulfillmentFor()` /
  `deliveredFulfillmentFor()`). A file-specific helper is bound as a property
  in `beforeEach` (`$this->paidOrder = function (...) {...};`, called as
  `($this->paidOrder)(...)`), which needs no `use (&...)`. No file carries a
  top-level `$var = null;` plus by-reference closure any more.
- Dataset scoping: named datasets declared in `tests/Pest.php` do not resolve
  from sidecars under `app/` (dataset scope is `tests/`). Inline `->with([...])`
  arrays and file-local `dataset()` calls at the top of a sidecar both work and
  are what the suite uses.
- Guarded-routes tests (`app/Http/Controllers/Seller/GuardedRoutesTest.php`,
  `app/Http/Controllers/Shop/GuardedRoutesTest.php`) loop over
  `Route::getRoutes()` inside the `it()` body rather than feeding a Pest
  dataset, because datasets are built before the app boots — the route table
  does not exist yet at dataset-evaluation time.
- `tests/Arch.php`'s `arch()->preset()->laravel()` carries three `ignoring`
  entries: `App\Http\Controllers` (action verbs like `place`/`pay`/`toggle`
  don't fit the preset's REST-only method vocabulary), `App\Domain` (domain
  enums live beside the concept they model, not centralized under
  `App\Enums`), and `App\Console\Commands\RunWeeklyPayouts` (named for the
  artisan command signature it registers, not suffixed `Command`).
- `tests/SidecarsTest.php` carries 23 sidecar exceptions, 4 with a "covered by"
  pointer to the file that exercises them; a second assertion now fails if any
  exception's sidecar exists on disk, so the list can only shrink.
- Test counts: 471 tests / 1101 assertions before this ticket, 485 tests /
  1123 assertions after.
- Consolidated helpers: `CommerceTestCase::shippedFulfillmentFor()` and
  `deliveredFulfillmentFor()` (pre-existing, now also used by
  `RunWeeklyPayoutTest` and `ConfirmDeliveredTest`), plus the two added in
  this pass, `cartWithOneListing()` and `paidOrderWithTwoSellers()`.
- `App\Domain\Listings\ListingSlug` inlines transliteration with
  `iconv`/`mb_strtolower`/`preg_replace` and no longer imports
  `Illuminate\Support\Str`.
