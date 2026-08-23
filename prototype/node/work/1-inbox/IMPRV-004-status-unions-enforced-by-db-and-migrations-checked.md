---
id: IMPRV-004
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-004: Status unions enforced by the database and row types checked against migrations

## Problem
`app/db/schema.ts:1-8`'s header comment states the contract as a convention: "A migration and its row type land together: whoever creates a table adds its type here." Nothing generates the types from the schema and nothing compares them. There is no `pragma_table_info`-vs-`Database` test — `app/db/*.test.ts` covers only the migrator, the connection, and the seeds. `tsc` passes whatever the hand-written type says.

Eleven columns are declared to Kysely as literal unions (`ListingStatus`, `OrderStatus`, `PaymentStatus`, `DeclineReason`, `FulfillmentStatus`, `LedgerEntryType`, `RemovalKind`, `ConversationKind`, `ActorType`, `ListingEventType`) — `app/db/commerce-schema.ts:27` (`status: ListingStatus`), and at `:85`, `:114`, `:125`, `:150`, `:183` — while the migrations create every one of those columns as bare `text` with no CHECK constraint. The only `addCheckConstraint` in the entire migration set is `app/db/migrations/20260823000005-create-notifications.ts:20-23`. Rows crossing back are never parsed — a `selectAll()` hands `listing.status` straight into `transitionListing` as a trusted `ListingStatus`.

`app/sites/admin/queries/platform-tallies.ts` widens a typed column back to `string` and then casts it back: `type CountedStatus = { status: string; count: number }` (`:23`) discards the literal union Kysely already gives the `status` column, and `asTallies<Status extends string>` recovers it with `key: row.status as Status` (`:70`) — an unchecked cast to an unconstrained type parameter, so `tallyOver(ORDER_STATUSES, asTallies(listingRows))` typechecks despite mismatched arrays.

`page_view_counts.site` is `string` (`app/db/commerce-schema.ts:170`) while `PageViewSite` exists and is used correctly on the write side (`app/core/analytics/page-view-site.ts:1-2`, `app/actions/analytics/record-page-view.ts:7`). `app/sites/admin/queries/page-view-report.ts:8` redeclares `PatternCount = { site: string; … }` rather than reusing the narrow type. The same file also types `DayCount.day: string` where `Day` is declared in `commerce-schema.ts:15` and never applied.

Four columns carry a DB default but the row types declare them as plain, not `Generated<…>`, so every insert supplies the same literal the migration already defaults: `createListing` writes `status: 'draft'` (`create-listing.ts:40`) against the migration's default (`20260823000001-create-listings.ts:26`); `placeOrder` writes `status: 'awaiting_shipment' as const` (`place-order.ts:99`) against the migration's default (`20260823000003-create-orders.ts:75`); `recordPageView` writes `count: 1` (`record-page-view.ts:23`) against the migration's default (`20260823000007-create-page-view-counts.ts:15`); `quantity`'s default of 1 (`20260823000001-create-listings.ts:24`) is the fourth.

Every one of the ten migration files ships a `down()`, including `20260822000001`'s `PRAGMA journal_mode = DELETE`, which would silently take the database out of WAL if it ever ran. `migrateToLatest` is the only exported migrator entry point and the only thing `app/db/migrate.ts` calls; there is no `migrateDown`/`migrateTo` anywhere in `app/` (verified by grep). The `down()` bodies are untested and unrunnable in practice, and 07-showcase.md's opportunity #10 proposes exercising them as a migration cycle test rather than deleting them.

## Goal
A status column cannot hold a value outside its TypeScript union, and the row types are checked against what the migrations actually produce.

## Outcome
- A forward-only migration adds CHECK constraints generated from the same `as const` arrays the unions come from.
- A test migrates a scratch DB and asserts `pragma_table_info` column names and nullability match every table type.
- A test cycles all migrations down to zero and back.
- `page_view_counts.site` and the day columns carry their narrow types (`PageViewSite`, `Day`).

## Why it matters
"Parse, don't validate at every boundary" and "DB rows crossing back are parsed once in the shell into narrow types" both apply here, and today a status column is a TypeScript claim unbacked by any runtime enforcement — a renamed column in a migration typechecks clean and fails only at runtime, and a stale nullability claim produces silently wrong narrowing. The `notifications` table already shows the pattern done right with a real `addCheckConstraint`; the other ten status-bearing columns do not follow it. Generating the CHECK constraints from the same arrays the TypeScript unions come from is cheaper than parsing every row back through zod and gives one source of truth for both.

## Discovery notes
Add `check(sql\`status in (...)\`)` per column in a new forward-only migration, generated from the same `as const` arrays the types come from (`LISTING_STATUSES`, `ORDER_STATUSES`, and so on for the other nine unions), so the constraint and the union share one source.

For the row-type/migration fidelity gap: either generate `Database` with `kysely-codegen` against a freshly migrated temp DB, or add one ~40-line test that migrates an in-memory DB and asserts `pragma_table_info` column names/nullability match the keys of each table type. The second needs no new dependency.

For `platform-tallies.ts`: delete `CountedStatus` and `asTallies`; let `tallyOver` take the narrow rows the three queries already return and map `count` with `Number()` inline. The cast disappears and a mismatched-array bug becomes a type error instead of a silent pass.

For `page_view_counts.site` and `day` columns: type the column `PageViewSite` and `day: Day`; the query rows then carry the union with no cast, and `PatternCount` can drop its own declaration.

For the four unreachable defaults: pick one side per column. Either mark them `Generated<T>` in the schema and drop the literal from the insert, or drop `defaultTo` from the migration and let the required insert column be the only statement.

For the `down()` migrations: 07-showcase.md's opportunity #10 frames this as a coverage and confidence move — a single sidecar test that applies all migrations forward, then `migrateDown()` to zero, then forward again on a scratch DB proves the forward-only migrator is honest, lifts the migration files' coverage, and guards this ticket's new CHECK-constraint migration in the same stroke. This needs a `migrateDown` export added to `app/db/migrator.ts` if one does not already exist for the test to call.

Files expected to touch: a new migration file under `app/db/migrations/`, `app/db/migrator.ts` (possible `migrateDown`/`pendingMigrations` export), `app/db/migrator.test.ts`, a new schema-fidelity test file, `app/db/commerce-schema.ts` (`PageViewSite`, `Day`, `Generated<T>` on the four defaulted columns), `app/sites/admin/queries/platform-tallies.ts`, `app/sites/admin/queries/page-view-report.ts`, `app/core/listings/create-listing.ts`, `app/actions/orders/place-order.ts`, `app/actions/analytics/record-page-view.ts`.

## Related work
- 02-types-boundaries.md: "The `Database` row types are never checked against the migrations", "Status unions live only in TypeScript; the columns are unconstrained `text`", "`platform-tallies` widens a narrow row type and casts it back", "`page_view_counts.site` is `string` while `PageViewSite` exists"
- 04-data-layer.md: "Status columns typed as literal unions with nothing enforcing them", "`platform-tallies.ts` widens a typed column back to `string`, then casts it", "Column defaults exist in the migrations and are unreachable through the types", "`down()` in all ten migrations, never called", "`Day` type declared but never applied at the producers"
- 07-showcase.md: showcase opportunity #10 ("Migration `down()` cycle test")
