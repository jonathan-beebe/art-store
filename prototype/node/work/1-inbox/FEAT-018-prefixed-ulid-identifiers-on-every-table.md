---
id: FEAT-018
type: feature
status: open
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
