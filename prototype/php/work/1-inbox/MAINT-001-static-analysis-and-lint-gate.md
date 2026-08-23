---
id: MAINT-001
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-001: Static analysis and lint gate with strict types and typed models

## Problem
`src/` has no static analysis and no lint config. `composer.json` scripts are `test` and `test:coverage` only; there is no `pint.json`; zero of 259 PHP files declare `strict_types=1`. Larastan 3 and Pest 4 were added to `composer.json` (uncommitted) with a first `src/phpstan.neon` at level 9 that reports 155 errors. 131 of those come from four causes the analyzer cannot see past: `casts()` is invisible without `parseModelCastsMethod: true` (30 errors, e.g. `app/Actions/Listings/ChangeListingStatus.php:12` "Cannot call method transitionTo() on string"); 34 relation methods across 15 models carry no generics (58 errors; only `app/Models/CustomerMerge.php:12` has `@return BelongsTo<Customer, $this>`); 7 models with NOT NULL foreign keys have no `@property-read` for the parent (17 errors, e.g. `app/Models/CartItem.php:33`); 5 `#[Scope]` methods take an ungeneric `Builder` (5 errors). `app/Console/Commands/RunWeeklyPayouts.php:13,15` hold the only untyped properties.

## Goal
One command proves the tree is formatted, type-clean at the analyzer's strictest level, and green.

## Outcome
- `make check` (and `composer check`) runs lint, static analysis, and the test suite in sequence and exits non-zero on any failure; `make analyse` and `make lint` exist alongside.
- Every PHP file under `src/app`, `src/database`, `src/routes`, `src/tests`, `src/config`, `src/bootstrap` declares `strict_types=1`, and `vendor/bin/pint --test` enforces that declaration and passes.
- PHPStan runs at `level: max` on `app`, `database`, `routes` with model casts and config types understood, and the error count is at or below 45 with every remaining error being about code rather than about analyzer configuration (no "return type with generic class ... does not specify its types", no "Cannot call method ... on string" for a cast column, no "Access to an undefined property Model::$x").
- `tests/Pest.php` exists and binds the existing base classes (`Tests\CommerceTestCase`, `Tests\StorefrontTestCase`, `Tests\TestCase` + `RefreshDatabase`) to the sidecar directories they serve, and `vendor/bin/pest` runs the full existing suite green; `composer test` runs Pest.
- 471 tests still pass; `docs/architecture.md` Testing and `README.md` Commands describe the new targets and the sidecar-Pest arrangement.

## Why it matters
Every later refactor ticket on this branch is judged by "analyzer clean, tests green". Without the gate those are opinions.

## Discovery notes
- The level-9 raw output is at `/private/tmp/claude-501/-Users-jonathan-beebe-source-personal-art-store/842f882b-ae6c-4850-95bd-26c870133e7a/scratchpad/phpstan-baseline.txt`.
- Measured: adding `declare(strict_types=1)` to all 259 files keeps 471 tests green; `pint` with `declare_strict_types: true` on the `laravel` preset does it in one run. Candidate rules that fire nowhere today and can be enabled for free: `strict_comparison`, `global_namespace_import`, `no_superfluous_phpdoc_tags`, `void_return`, `nullable_type_declaration_for_default_null_value`. Do not enable `final_class` (would finalize models and seeders) or `ordered_class_elements` (reorders the anonymous migration classes).
- Measured `phpstan.neon` deltas: `parseModelCastsMethod: true` 155→125; `checkConfigTypes: true` + `configDirectories: [config]` −2; relation generics −58; `@property-read` −17; scope `Builder<$this>` −5. Leave `tests` out of `paths` for now (a later ticket adds them) and keep `excludePaths` for `**/*Test.php`.
- `parseModelCastsMethod` surfaces `app/Actions/Orders/FinalizeOrder.php:48` as a non-exhaustive `match`; a `default => null` arm holds it until BUG-002 fixes it properly.
- Pest: `uses()->in('../app/Domain')` resolves relative to `tests/Pest.php` and matches files by directory prefix, so sidecars outside `tests/` work (verified in `vendor/pestphp/pest/src/PendingCalls/UsesCall.php:83-113` and `src/Repositories/TestRepository.php:181-186`). `app/Domain/**` and `app/Support/**` want no `uses()` at all (Pest's default base is `PHPUnit\Framework\TestCase`). Base-class distribution: 41 files on plain PHPUnit, 28 on `CommerceTestCase` (`app/Actions`, `app/Console/Commands`, `app/Http/Controllers/Seller`, `app/Models/ListingTest`), 10 on `StorefrontTestCase` (`app/Http/Controllers/Shop`, `tests/SmokeTest`), 13 on `Tests\TestCase` + inline `RefreshDatabase` (`app/Http/Controllers/Auth`, `app/Http/Middleware`, `database/seeders`, and three outliers under `app/Actions` and `app/Models/MagicLinkTest`).
- `composer test` currently runs `vendor/bin/phpunit`; `make smoke` passes `--testsuite Smoke`, which Pest accepts.
- Type the two command properties (`protected string $signature`).
- Use `--no-deps` on the analyse/lint make targets so a static run does not start the web server.

## Related work
- FEAT-001 (Dockerized foundation, sidecar PHPUnit)
