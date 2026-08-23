---
id: RFCTR-004
type: refactor
status: resolved
created: 2026-08-23
---

# RFCTR-004: Data-layer idioms: sequential awaits, typed merge, parsed notifications

## Problem
The data layer carries a false-concurrency idiom, one large raw-SQL action
with runtime schema introspection, an unparsed database invariant, and two
smaller operational rough edges.

**`Promise.all` runs over a synchronous driver at 15 sites.**
`app/actions/messaging/conversation-topics.ts:20`,
`conversation-thread.ts:64`, `conversation-participants.ts:29`,
`conversation-inbox.ts:43`; `app/sites/admin/queries/seller-rows.ts:26`,
`customer-rows.ts:29`, `customer-detail.ts:55,:105`,
`seller-detail.ts:31`, `order-rows.ts:40`, `listing-detail.ts:49`;
`app/sites/seller/routes/home.ts:18`, `earnings.ts:15`,
`listings.ts:81,:196` — e.g.
`const [listings, fulfillments] = await Promise.all([...])`.
better-sqlite3 is synchronous and Kysely's `SqliteDriver` holds exactly
one connection behind a `ConnectionMutex`
(`SqliteAdapter.supportsMultipleConnections === false`), so every one of
these runs strictly one after another. The shape claims a concurrency the
runtime cannot provide and hides the real query count from the reader.

**`merge-anonymous-customer.ts` is raw SQL plus runtime schema
introspection.** `app/actions/customers/merge-anonymous-customer.ts`
holds 17 raw `sql` fragments (lines 75, 100, 121, 133, 137, 164, 171, 189,
213, 228, 229, 240, 247, 259, 265) against `favorites`, `carts`,
`cart_items`, `listings`, `orders`, `listing_events`, `notifications`, and
`conversations` — all fully typed in `app/db/commerce-schema.ts`. The
reason is `app/actions/customers/merged-table-columns.ts`, which queries
`sqlite_master` and then one `pragma_table_info` per table (a query per
table, on every merge) so that `hasColumns` can skip tables that "arrive
on their own schedule." All of those tables are created by checked-in
migrations (`20260823000001` through `20260823000008`); none can be
absent at runtime. The introspection guards a condition that cannot
occur, and the price is that the merge gets no compile-time schema
checking, no camelCase mapping through the builder, and result types that
are asserted (`sql<CartLine>` at line 171,
`sql<{ id: number; quantity: number }>` at line 189) rather than derived.

**Runtime schema introspection stands in for a compile-time guarantee.**
`app/actions/customers/merged-table-columns.ts:8-47`;
`app/core/customers/repointed-customer-tables.ts:9-14` — the same
introspection described above is a third place table and column names
are spelled, in snake_case string literals disconnected from both the
migrations and the camelCase `Database` keys.

**A notification's recipient is three nullable columns plus a SQL-only
invariant.** `app/db/commerce-schema.ts:156-166`;
`app/db/migrations/20260823000005-create-notifications.ts:20-23`;
`app/actions/notifications/notify.ts:29-32` — `NotificationsTable` has
`sellerId | customerId | adminId`, all `number | null`. The real rule —
exactly one is set — is written in SQL only:
`sql`(seller_id is not null) + (customer_id is not null) + (admin_id is not null) = 1``.
Nothing on the TypeScript side states it, so every reader must know it by
convention. `notify` writes via a computed key
(`[RECIPIENT_COLUMNS[input.recipientType]]: input.recipientId`), which
widens the object literal enough that Kysely's insert type stops
discriminating the three columns.

**Single-statement actions are wrapped in a transaction.**
`app/actions/messaging/publish-listing-faq.ts:23`,
`update-listing-faq.ts:16`, `unpublish-listing-faq.ts:9`,
`app/actions/carts/remove-from-cart.ts:14` — each wraps exactly one
statement in `runInTransaction`, a `begin`/`commit` pair around a
statement SQLite would run atomically on its own.

**`make fresh` deletes the database file under a running server.**
`app/db/database.ts:26-32`; `app/db/migrate.ts:7-10`; the `fresh` target
in `prototype/node/Makefile` — `removeDatabaseFile` unlinks the file and
its `-wal`/`-shm` siblings. The Makefile comment states the hazard
plainly ("a running server holds the deleted database file open, so it
restarts onto the rebuilt one") and works around it by restarting the app
container afterwards. Between the unlink and the restart, the server is
writing to an unlinked inode; those writes are lost, and the requests
that made them got 200s.

## Goal
Database access reflects the driver's real synchronous behavior, the
customer merge is checked against the typed schema like every other
action, and a notification's recipient is a fact the type system states.

## Outcome
- No `Promise.all` wraps a set of database calls; independent reads are
  either sequential `await`s or, where the motive was round-trip count, a
  single grouped query.
- The customer merge is written against the typed Kysely builder, with
  `REPOINTED_CUSTOMER_TABLES` as the only table list and no runtime
  schema introspection.
- Notification rows are parsed once into
  `{ recipientType, recipientId }`, and the insert is an explicit switch
  over recipient type rather than a computed key.
- The four single-statement actions call their write directly rather than
  opening a transaction, while still accepting `ActionContext` so a
  caller that already holds a transaction is unaffected.
- `make fresh` stops the app container before deleting the database file
  and starts it again after, rather than restarting after the fact.

## Why it matters
better-sqlite3 is synchronous and Kysely serializes on its one connection
— an `await` here is not concurrency, and code that implies otherwise
misleads the next reader about both performance and ordering. The typed
query builder is meant to compile 1:1 to SQL and have its types checked
against the schema; a 269-line action built from raw SQL and a
per-request table scan is the one place in the codebase that opts out of
both, for a condition (a missing commerce table) that cannot happen given
the checked-in migrations. Illegal states unrepresentable applies to the
notification recipient: three nullable columns state "at most one," while
the actual rule ("exactly one") lives only in a SQL CHECK constraint no
TypeScript reader can see. "Explicitness over cleverness" is the
standard the transaction wrapper fails on four single-statement actions —
the wrapper communicates "these statements belong together," and there
is only one statement.

## Discovery notes
Replace each `Promise.all` with sequential `await`s; where the original
motive was round-trip count rather than style, a join or a single grouped
query is the real fix, and most of these sites are already batch loads
that only need the `Promise.all` unwrapped rather than restructured.

Delete `merged-table-columns.ts` and the `hasColumns` guards, and rewrite
`merge-anonymous-customer.ts`'s body with the Kysely builder against the
typed `commerce-schema.ts` tables. `REPOINTED_CUSTOMER_TABLES` can stay as
a typed `readonly (keyof Database)[]` so the repoint loop still compiles
against the schema.

Parse the notification row once on the way out into
`{ recipientType: RecipientType; recipientId: number; … }` — the shape
`notify` and `DeliverableNotification` already use — and let readers
consume that instead of three nullable columns. Replace the computed-key
insert in `notify` with an explicit `switch` over `recipientType` so the
insert stays type-checked per branch.

Drop the `runInTransaction` wrapper on the four single-statement actions
listed above; keep their `ActionContext` parameter so a caller already
inside a transaction still writes into it correctly.

Have the `fresh` Makefile target stop the app container, run the delete
and remigrate, then start the container again — rather than deleting the
file first and restarting afterward.

Files this ticket is expected to touch:
`app/actions/messaging/conversation-topics.ts`,
`conversation-thread.ts`, `conversation-participants.ts`,
`conversation-inbox.ts`, `app/sites/admin/queries/seller-rows.ts`,
`customer-rows.ts`, `customer-detail.ts`, `seller-detail.ts`,
`order-rows.ts`, `listing-detail.ts`, `app/sites/seller/routes/home.ts`,
`earnings.ts`, `listings.ts`,
`app/actions/customers/merge-anonymous-customer.ts`,
`app/actions/customers/merged-table-columns.ts` (deleted),
`app/core/customers/repointed-customer-tables.ts`,
`app/db/commerce-schema.ts`, `app/actions/notifications/notify.ts`,
`app/actions/messaging/publish-listing-faq.ts`, `update-listing-faq.ts`,
`unpublish-listing-faq.ts`, `app/actions/carts/remove-from-cart.ts`,
`app/db/database.ts`, `app/db/migrate.ts`, `Makefile`.

No ordering dependency on the other refactor tickets is required —
RFCTR-004's scope (data-layer mechanics) is independent of RFCTR-001/002/003's
scope (business rules moving into core).

## Related work
- 04-data-layer.md — "`Promise.all` over a synchronous driver, 15 sites"
- 04-data-layer.md — "`merge-anonymous-customer.ts` is raw SQL plus runtime schema introspection"
- 02-types-boundaries.md — "Runtime schema introspection stands in for a compile-time guarantee"
- 02-types-boundaries.md — "A notification's recipient is three nullable columns plus a SQL-only invariant"
- 04-data-layer.md — "Single-statement actions wrapped in a transaction"
- 04-data-layer.md — "`make fresh` deletes the database file under a running server"

## Working

### Verified against the code first

Every part of the problem still held, with two corrections to the report:

- The driver is `node:sqlite` through `app/db/node-sqlite-dialect.ts`, not
  better-sqlite3. The conclusion is the same: the dialect reports
  `supportsMultipleConnections === false`, holds one `DatabaseSync`, and
  Kysely serializes every query through it, so a `Promise.all` was never
  concurrency.
- 15 `Promise.all` sites were named; 13 wrap database calls. The other two are
  `app/plugins/health.ts:25` (FEAT-011 owns that file) and `app/db/database.ts:24`,
  which unlinks the database file and its `-wal`/`-shm` siblings — file removal,
  not database calls. Both left alone.

### Changed

- **Sequential awaits, 13 sites.** `app/actions/messaging/conversation-topics.ts`,
  `conversation-thread.ts`, `conversation-participants.ts`, `conversation-inbox.ts`;
  `app/sites/admin/queries/seller-rows.ts`, `customer-rows.ts`, `customer-detail.ts`
  (both), `seller-detail.ts`, `order-rows.ts`, `listing-detail.ts`;
  `app/sites/seller/routes/home.ts`, `earnings.ts`, `listings.ts` (both). Each was
  already a batch load, so unwrapping the array was the whole change — no query
  was restructured and the round-trip count per site is unchanged.
- **The merge is written against the typed builder.**
  `app/actions/customers/merge-anonymous-customer.ts` holds no `sql` fragment;
  every statement is `selectFrom`/`updateTable`/`deleteFrom`/`insertInto` with
  camelCase names checked against `Database`. `merged-table-columns.ts` and its
  test are deleted. The repoint loop is generic over the table
  (`db.updateTable(table).set({ customerId })`) and typechecks as written, so
  the four updates stayed one loop.
- **`REPOINTED_CUSTOMER_TABLES` moved from `app/core/customers/` to
  `app/actions/customers/`** and is now
  `['orders', 'listingEvents', 'notifications', 'conversations'] as const satisfies readonly (keyof Database)[]`.
  Assumption recorded: the ticket asked for `keyof Database` typing, and core
  imports nothing from `app/db` anywhere else in the tree — a list of database
  tables is shell vocabulary, so the file moved rather than pulling a `Database`
  import into core. Its test moved with it; the `column: 'customer_id'`
  assertion is gone because the column is no longer spelled as a string.
- **Notification recipients are parsed once.**
  `app/actions/notifications/notification-recipient.ts` (new, with tests) turns a
  row's three nullable columns into `{ recipientType, recipientId }` and throws
  `TypeError` for a row naming nobody — the table's check constraint makes that a
  broken database rather than a case a caller answers.
  `app/sites/seller/queries/notifications.ts` and
  `app/sites/shop/queries/find-customer-notifications.ts` return
  `ParsedNotification`. There is no admin notification query to convert.
  `notify` writes through an explicit `switch` (`recipientColumns`) that returns
  all three columns per branch, so the insert is discriminated again.
- **`notify` no longer opens a transaction.** It is one insert, and the delivery
  port now runs after the row is written rather than inside a transaction `notify`
  itself opened. `ActionContext.notificationDelivery` is unchanged, so a caller
  that already holds a transaction (`mark-shipped`) still delivers inside its
  own unit of work — the outbox ticket (FEAT-015) is what makes delivery a row
  write and closes that. New test: a delivery that throws leaves the notification
  on file.
- **Four single-statement actions call their write directly.**
  `publish-listing-faq.ts`, `update-listing-faq.ts`, `unpublish-listing-faq.ts`,
  `remove-from-cart.ts` destructure `ActionContext` and drop `runInTransaction`;
  the parameter type is unchanged, so a caller inside a transaction still writes
  into it.
- **`make fresh` stops the app first.** `docker compose stop app`, then fresh +
  seed, then `docker compose start app`. The comment now names the hazard
  (writes to an unlinked inode) rather than the workaround.
- **Storefront visibility (added scope from RFCTR-002).**
  `app/core/listings/listing-availability.ts` exports `STOREFRONT_STATUSES` and
  `BROWSABLE_STATUSES`; `find-storefront-listings.ts`'s `isBrowsable` and
  `find-favorite-listings.ts` build their `where` from them, and each keeps its
  `NOT EXISTS` removal clause under a comment naming `isOnStorefront` as the
  predicate it mirrors. Core tests pin that `isOnStorefront` admits exactly
  `STOREFRONT_STATUSES` and that everything browsable is on the storefront.
  `app/sites/shop/queries/find-storefront-listings.test.ts` did not exist at the
  time of the change — the whole suite is green without it.

### Left alone deliberately

- `app/plugins/health.ts` and `app/db/database.ts` — FEAT-011's file, and a
  `Promise.all` over `unlink`, not over queries.
- `app/db/commerce-schema.ts` — the ticket listed it, but the recipient parse
  lives at the read boundary and the three nullable columns are what the table
  holds. Nothing in the schema file needed to change.
- The merge test `it skips a table the schema does not have and still writes its
  trail` is deleted along with the introspection it exercised: dropping
  `conversations` at runtime is not a state the checked-in migrations can produce.
- `docs/identity.md` had two sentences describing `readMergedTableColumns`;
  those were replaced with what the merge does now. No other doc mentioned the
  deleted module.

### Tests

`npm test`: 1318 → 1338 passing, 0 failing (the tree is shared with other
tickets in flight, so that delta is not all mine). This ticket removes 7 tests
(5 in `merged-table-columns.test.ts`, 1 schema-skip case in the merge test, 1
`customer_id` column assertion) and adds 9 (5 `notification-recipient.test.ts`,
1 `notify.test.ts`, 3 `listing-availability.test.ts`).

`npm run typecheck` and `npm run lint` are clean for every file this ticket
touched. `npm run check` cannot be run to completion right now: another worker's
in-flight `app/config.ts` fails the `complexity` lint rule and their
`app/test/build-test-app.ts` is mid-edit against a widened `AppConfig`. Both are
outside this ticket's territory.
