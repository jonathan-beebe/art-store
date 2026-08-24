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
