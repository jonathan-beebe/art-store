---
id: FEAT-018
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-018: Prefixed ULID identifiers on every table

## Problem
Every table under `src/app/db/migrations/` uses `integer primaryKey autoIncrement`; URLs (`/orders/:id`, `/admin/customers/:id`, `/seller/listings/:id`), order numbers, and thread paths expose sequential integers. `docs/alignment.md` §1 fixes the shared shape: text primary keys of the form `<prefix>_<26-char ULID>`, the same prefix table across all three prototypes.

## Goal
Every row in the Node prototype is identified, stored, and addressed by a prefixed ULID.

## Outcome
Every domain table's primary key and every foreign key is a text prefixed ULID per the §1 prefix table; every route that takes an id refuses a wrong-prefix or malformed id with the site's 404 page; the order number shown on the storefront, seller portal, and admin site is the order id; `make fresh` rebuilds a seeded database; seeds on the fixed clock produce ids that sort in creation order; the schema-fidelity test (IMPRV-004) still passes; `docs/data-model.md` and the ER diagram show text ids.

## Why it matters
Sequential ids leak volume and let anyone enumerate orders; prefixed ULIDs make an id self-describing in a log line or a URL, and identical prefixes let a reader compare the three prototypes' logs and pages directly.

## Discovery notes
Platform-first: an owned generator over `node:crypto` `randomBytes` (48-bit ms timestamp + 80 random bits, Crockford base32) is ~30 lines and needs no dependency; a pure `parsePrefixedId(prefix, text)` returning a result union fits the existing `src/app/http/request-schema.ts` idiom (`idParams` becomes prefix-aware). The id is minted in the action from the clock it already receives. `customer_merges` and the identity cookie resolution paths, seeds, and fixtures are where the cost is. Existing create migrations may be rewritten in place — no data migration.

## Related work
- docs/alignment.md §1
- IMPRV-004 (schema fidelity test)

## Working

### What landed

- `src/app/core/ids/prefixed-id.ts` — the pure half. `PrefixedId<Prefix>`,
  `encodeUlid(milliseconds, randomness)`, `nextRandomness(randomness)`,
  `parsePrefixedId(prefix, value)` returning
  `{ outcome: 'id', id } | { outcome: 'refused', reason: 'malformed' | 'wrong_prefix' }`,
  and `isPrefixedId`. No clock, no randomness, no I/O.
- `src/app/core/ids/entity-ids.ts` — `ID_PREFIXES`, the `docs/alignment.md` §1
  table in TypeScript, plus `IdPrefix` and every named id type derived from it
  (`OrderId = PrefixedId<typeof ID_PREFIXES.orders>`), and `ActorId`.
- `src/app/ids.ts` — the shell half. `newId(prefix, at)` draws
  `randomBytes(10)` and hands them with `at.getTime()` to `encodeUlid`.
- `src/app/test/fixture-ids.ts` — `fixtureId('ord', 1)` builds
  `ord_00000000000000000000000001` for tests that name their rows.
- Every migration rewritten in place: `id` is `text primary key not null`,
  every foreign key is `text`. No data migration; `make fresh` rebuilds.
- Row types in `schema.ts` / `commerce-schema.ts` carry the named id types with
  no `Generated<>`, so `tsc` refuses an insert that does not mint an id.
- `idParams(prefix)` / `idValue(prefix)` in `http/request-schema.ts`, applied at
  every route and every `optionalFilter` seller/customer filter.
- `plugins/identity.ts` parses the cookie through `parsePrefixedId` keyed to the
  actor type's prefix; `parseActorId`, `identityId`, `signedInActorId`, and
  `reply.signIn` are generic over the actor type, so a `SellerId` cannot be
  written into the customer cookie.
- Ordering: every query that ordered by `id` now orders by the row's creation
  column with `id` as a secondary key.
- The `#` sigil is gone wherever a prefixed id renders: the id is the order
  number (contract §1).

### Decisions

1. **Template-literal types, not brands.** `type PrefixedId<Prefix extends
   string> = \`${Prefix}_${string}\``, and each table's id derived from
   `ID_PREFIXES`. It survived the whole sweep; nothing fell back to plain
   `string`.
2. **Three types became discriminated unions** rather than losing precision to
   `ActorId`: `ConversationParticipant`, `MessagingActor`, `SupportRequester`,
   and the new `NotificationRecipient` pair `{ recipientType, recipientId }`.
   TypeScript cannot correlate a free `ActorType` with a free `ActorId`, so the
   pair travels as one value and every construction site narrows. `ActorId`
   survives only where a value is compared, never assigned into a column:
   `messages.sender_id`, `ReadMarker`, `ParticipantNames`.
3. **The generator is monotonic within a millisecond** — the ULID spec's
   monotonic mode. Randomness is drawn fresh for each new millisecond and
   stepped by one for each id after the first inside it. Without it a fixed
   clock mints ids in no order at all, and every list a page renders in
   creation order comes back shuffled: the suite's travelling clock puts a
   whole transaction inside one millisecond. Fresh randomness per millisecond
   keeps "random bits stay random" true in the sense the contract means it.
4. **`cart_items`, `order_items`, and `fulfillments` gained `created_at`.**
   They were the three tables with no creation timestamp, and the contract says
   ordering by creation uses `created_at`, never the id. This is a schema
   change beyond the ticket and PHP and Rails have to match it.
5. **The `id` nullability skip in `schema-fidelity.test.ts` is gone.** With
   `text primary key not null` SQLite reports `notnull = 1` truthfully, so the
   column is asserted like any other; the skip existed only because an
   `integer primary key` is a rowid alias whose flag says nothing.
6. **Fixture ids are hand-written where a test names its rows** (contract §1
   blesses this) and minted where a test writes a row it does not name.
7. **`newId` is a module-level function, not a decorator or an `ActionContext`
   field.** It is the same shape as `systemClock`, and the monotonic sequence is
   an implementation detail of the shell's id source. Threading a minter
   through every action would have changed a signature every test constructs.

### Deliberately left out

- `session_id` / `txn_id` (contract §2) are IMPRV-009's. `newId` accepts `ses`
  and `txn` and `ID_PREFIXES` names them; `logging.ts` and the log payload are
  untouched.
- `refunds` and `rate_limit_windows` have no table here yet, so they have no
  prefix constant.
- Test-local `orderBy('id')` in a handful of test files stayed: with monotonic
  ids it reads insertion order, and the rule the contract sets is about what
  the product queries.

### Deviations from the contract

- Monotonic randomness within a millisecond (decision 3).
- `created_at` added to three tables (decision 4).

### Notes

- `make fresh` runs `docker compose start app` at the end, which fails with
  `service "app" has no container to start` when the stack was never up. The
  migrate and seed steps both complete first. Not fixed here — the Makefile
  belongs to the MAINT lane.
