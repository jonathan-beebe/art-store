---
id: FEAT-018
type: feature
status: open
created: 2026-08-23
---

# FEAT-018: Prefixed ULID identifiers on every table

## Problem
Every domain table under `src/database/migrations/` uses `$table->id()` (autoincrement integer); URLs (`/orders/{order}`, `/admin/customers/{customer}`, `/seller/listings/{listing}`), order numbers, and thread paths expose sequential integers. `docs/alignment.md` §1 fixes the shared shape: text primary keys of the form `<prefix>_<26-char ULID>`, one prefix table across the three prototypes.

## Goal
Every row in the PHP prototype is identified, stored, and addressed by a prefixed ULID.

## Outcome
Every domain table's primary key and every foreign key is a text prefixed ULID per the §1 prefix table (framework tables keep their keys); route-model binding refuses a wrong-prefix or malformed id with the site's 404; the order number shown on the storefront, seller portal, and admin site is the order id; `make fresh` rebuilds a seeded database; seeds on the fixed clock produce ids that sort in creation order; factories produce valid ids; `docs/data-model.md` and the ER diagram show text ids; `make check` passes (PHPStan level max, 100 % coverage convention).

## Why it matters
Sequential ids leak volume and let anyone enumerate orders; a prefixed ULID is self-describing in a log line or a URL, and identical prefixes let a reader compare the three prototypes' logs and pages directly.

## Discovery notes
Idiomatic Laravel: `Str::ulid()` ships (Symfony Uid); a `HasPrefixedUlid` trait over `HasUniqueStringIds`/`HasUlids` with `newUniqueId()` prepending the model's prefix constant, and `resolveRouteBindingQuery` refusing the wrong prefix, keeps the change in one place per model. Migrations use `$table->string('id', 30)->primary()` and `foreignUlid`-style string FKs. Existing migrations may be rewritten in place — no data migration.

## Related work
- docs/alignment.md §1
- RFCTR-006 (strict models)
