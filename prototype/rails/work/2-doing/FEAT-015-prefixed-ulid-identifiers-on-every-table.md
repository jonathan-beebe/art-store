---
id: FEAT-015
type: feature
status: open
created: 2026-08-23
---

# FEAT-015: Prefixed ULID identifiers on every table

## Problem
Every domain table in `src/db/schema.rb` uses the default integer primary key; URLs (`/orders/:id`, `/admin/customers/:id`, `/seller/listings/:id`), order numbers, and thread paths expose sequential integers. `docs/alignment.md` §1 fixes the shared shape: text primary keys of the form `<prefix>_<26-char ULID>`, one prefix table across the three prototypes.

## Goal
Every row in the Rails prototype is identified, stored, and addressed by a prefixed ULID.

## Outcome
Every domain table's primary key and every foreign key is a string prefixed ULID per the §1 prefix table (Active Storage and Solid Cable tables keep their keys); a wrong-prefix or malformed id in a route answers the site's 404; the order number shown on the storefront, seller portal, and admin site is the order id; `make fresh` rebuilds a seeded database; seeds on the fixed clock produce ids that sort in creation order; fixtures carry valid ids; `docs/data-model.md` and the ER diagram show string ids; `make check` passes at 100 % line coverage.

## Why it matters
Sequential ids leak volume and let anyone enumerate orders; a prefixed ULID is self-describing in a log line or a URL, and identical prefixes let a reader compare the three prototypes' logs and pages directly.

## Discovery notes
Vanilla Rails: `create_table ..., id: :string` with `t.references ..., type: :string`, a `PrefixedId` concern that sets `attribute :id, default: -> { PrefixedUlid.generate(prefix) }` and a `find_by_public_id`-free path (the id is the public id); an owned generator in `lib/prefixed_ulid.rb` (~25 lines over `SecureRandom.random_bytes`) or the `ulid` gem — the maker decides. Fixtures need explicit `id:` values, which is where the cost is. `schema.rb` and migrations may be rewritten in place — no data migration.

## Related work
- docs/alignment.md §1
- FEAT-003 (commerce core)

## Working

### What was built

- `src/lib/prefixed_ulid.rb` — the owned generator, autoloaded from `lib/`
  (`config/application.rb` already carries `autoload_lib(ignore: %w[assets
  tasks])`). No gem: 48-bit millisecond timestamp from the clock the caller
  passes, 80 random bits from `SecureRandom.random_bytes(10)`, Crockford
  base32 over 26 uppercase digits. Ids minted on one clock reading count up
  from that reading's random draw, so a frozen clock still yields ids that
  sort in the order they were minted. `parse` refuses a wrong prefix, a
  missing prefix and a malformed ULID; `constraints` hands the same rule to
  the router as segment regexps.
- `src/app/models/concerns/prefixed_id.rb` — `prefixed_id :ord` on a model
  sets `attribute :id, default: -> { PrefixedUlid.generate(prefix) }`.
  `ApplicationRecord` includes it; all 20 domain models declare a prefix.
- Migrations rewritten in place: every domain `create_table` is `id: :string`
  and every `t.references` is `type: :string`. `db/schema.rb` regenerated in
  the container from an empty database.
- `config/routes.rb` constrains every id segment to its table's prefix, so a
  path carrying another table's id matches no route and answers the same 404
  as an id nothing holds. `/art/:slug` keeps slugs.
- `Customer.from_cookie` parses the identity cookie through
  `PrefixedUlid.parse(value, :cus)` instead of `Integer()`.

### Decisions on ambiguities

- **"Fixtures carry valid ids"** — there are no fixture files in this tree
  (`test/fixtures/` holds only `files/`); `fixtures :all` loads nothing.
  Read as: the `TestRecords` builders and the seeds mint valid ids, and the
  tests that hard-coded an id (`id: 0`, `id: 1`, `"not-a-number"`) now ask
  `unused_id(:sel)` for an id of the right shape that no row carries. A path
  helper refuses an id its route's constraint rejects, so the wrong-prefix
  tests drive raw paths rather than helpers.
- **`active_storage_attachments.record_id`** — made `type: :string` in the
  Active Storage migration. It is a foreign key onto a domain row, and that
  row's key is now text; the attachment table's own primary key and
  `blob_id` stay as the framework made them, as do `active_storage_blobs`,
  `active_storage_variant_records` and `solid_cable_messages`.
  `test/models/listing_test.rb` asserts the attachment holds the listing's
  prefixed id and that the blob still serves.
- **The parse function at the route boundary** — Rails' router takes segment
  requirements as regexps, not as callables, so `PrefixedUlid.constraints`
  hands routes the same rule `parse` applies rather than the method itself.
  One definition (`BODY` plus the prefix) backs both.
- **`order(:id)`** — every scope and query that meant "in creation order" now
  reads `order(:created_at, :id)`: `Admin.on_duty`, `Conversation.open`,
  `Conversation#move_to`, the cart, checkout, order and admin dashboard
  lists. The id stays as the tie-breaker between two rows written in the same
  millisecond, which is exactly the order they were written in.
- **The conversations shape index** — its `COALESCE(seller_id, 0)` terms
  became `COALESCE(seller_id, '')` now that the columns are text.
- **The order number** — every surface already rendered `order.id`
  (`Order ##{order.id}`, `Order ##{fulfillment.order_id}`,
  `Conversation#topic`), so making the id a prefixed ULID was the whole
  change; nothing else names an order.

### Deliberately left out

- No `implicit_order_column`. `.first`/`.last` order by the primary key,
  which is creation order under a ULID, so setting it would change no
  behaviour.
- Tables §1 lists that this prototype does not have (`customer_blocks`,
  `listing_removals`, `refunds`, `page_view_counts`, `rate_limit_windows`,
  `outbox_messages`) are untouched — later tickets add them.
- `sid` / `txn_id` (§2) are the logging ticket's, not this one's.

### Deviations from the contract

None.
