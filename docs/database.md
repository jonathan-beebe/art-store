# Database

The app runs SQLite. It is one file to operate. The tradeoff is features
(no RLS) and speed (write-limited). The natural upgrade is to adopt Postgres
the day the app needs a second instance, managed backups, or row-level
security.

This document describes the principles by which we work with SQLite so that
we are using it to its full potential, and we leave the door open to migrating
to Postgres and making the transition as simple as possible.

Migrations go through the Laravel schema builder; queries go through
Eloquent or the query builder.

## 1. Ownership keys

1. Every row a single seller owns carries `seller_id`. 
2. Every row a single customer owns carries `customer_id`. 
3. Admin visibility is platform-wide; no table carries an admin ownership column.

- The column lives on the row itself, including tables where the owner is
  derivable through a join (a variant's seller, a cart item's customer).
- Orders span sellers — an order is customer-owned, and seller ownership
  lives on `order_items` and `fulfillments`.
- `tests/OwnershipTest.php` checks every model on its two lists. Add a new
  owned model to the list.

This gives a complete ownership model today, enforced in app code. And it
makes an eventual Postgres migration easy: the RLS policy per table is
one line — `USING (seller_id = current_setting('app.seller_id'))` using the
same predicates the app enforces.

## 2. Authorization lives in the application

SQLite has no users, roles, grants, or RLS, so authorization is modeled in
policies, middleware, and mandatory owner scopes in the app layer.

- No database users, no GRANTs, nothing security-shaped in the schema
  beyond the ownership columns.
- Roles (seller, customer, admin) are application concepts on their own
  tables.
- Postgres RLS, if adopted later, is defense-in-depth under the same
  predicates. The app-layer rules stay the source of truth.

## 3. Migration Gotchas

We want to use SQLite in such a way that migrating to Postgres will feel
natural and simple. If we follow these rules, we gain consistency across
how we use SQLite, and make an eventual migration simpler.

- **DDL through the migration DSL only.** The framework emits correct DDL
  for both engines. No raw `CREATE TABLE`, no engine pragmas in migrations.
  The one exception: `create_fulfillment_flows_table` runs a raw
  `DB::statement` for the partial unique index on the default flow, which
  Blueprint cannot express.
- **Queries through the builder/ORM.** Raw SQL, where a query needs it,
  lives behind a named scope or repository method so it is findable and
  swappable. Raw fragments never appear inline in controllers or views.
- **`LIKE` differs by engine.** SQLite's `LIKE` is case-insensitive for ASCII;
  Postgres's is case-sensitive. Any case-insensitive match normalizes both
  sides (`lower(column) like lower(pattern)`) or lives behind the search
  seam of §4. Product rule: every search ignores case. The one exception:
  `Analytics/Admin/AnalyticsJump.php` matches an id prefix with `like` and
  no `lower()`; ULIDs are upper case, so a lower-case paste misses on
  Postgres.
- **Types are declared and enforced in the app.** SQLite stores whatever it
  is handed; Postgres rejects what the column type forbids. Casts and
  request validation are the guard — bad data that SQLite tolerates
  surfaces as a failed import on migration day.
  - Datetimes are UTC. 
  - Booleans go through casts. 
  - JSON columns are queried through the builder's JSON operators.
    `units.specs_json` is declared `json`;
    `order_items.{configuration,answers,price_breakdown}_json` are declared
    `text`.
- **No engine-specific features in application code**: no `rowid`, no
  SQLite-flavored `INSERT OR REPLACE`. `strftime` appears in three
  `selectRaw` hour buckets, in `Analytics/Admin/EntityActivity.php` and
  `Analytics/Admin/ActorAggregates.php`. Upserts go through the builder's
  upsert API.

## 4. Search

Search and discovery is a core feature of this product. While we leverage
search at the database layer, search is also a product feature implemented in
app code and designed into the table schemas.

- SQLite offers `LIKE` and FTS5.
- Postgres offers `tsvector` / `pg_trgm`.

For now we keep search simple, and isolate search flows in app code that owns
the responsibility. 

- All matching and ranking flows through a single search entry point:
  `ListingSearch` and the `ofSearchTerm` scope. Controllers and views never
  compose a search predicate.
- Upgrading relevance (`LIKE` today, FTS5 or `tsvector` after) touches
  that seam and nothing else.

## 5. Operating SQLite

- **Writes**: one write transaction at a time; WAL mode keeps reads flowing
  during a write. Hundreds of writes per second fit this workload shape.
- **Reads**: effectively unlimited at this scale.
- **Topology is the limit.** SQLite pins the app to one instance with a
  persistent disk: single instance, deploys with downtime, and we own backup
  and point-in-time recovery. The need for a second instance or managed
  backups comes before the need for more throughput.
- The process is the security boundary. Anything that can open the file
  reads everything, which is why §2 puts authorization in the app and why
  enforced per-tenant isolation means Postgres RLS or a file per tenant.
