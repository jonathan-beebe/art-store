---
id: IMPRV-023
type: improvement
status: resolved
created: 2026-09-01
---

# IMPRV-023: every owned row names its owner

## Problem

Ownership in the php prototype's schema is join-derived. A seller's rows are
reachable only through listing_id chains (listing_events, listing_removals,
listing_faqs, listing_attributes, listing_images, option_axes, option_values,
variants, variant_options, units, modifiers, modifier_options,
modifier_scopes, quantity_breaks, description_sections carry no seller_id —
up to three hops via variant → axis → listing). A customer's rows on the
order side are likewise join-derived: cart_items, order_items, payments,
fulfillments, and refunds carry no customer_id, and refunds carry no
seller_id despite being fulfillment-scoped. Scoping a query to an owner
requires joins, and a future Postgres row-level-security layer would need
per-table subquery policies instead of a uniform single-column predicate.

## Goal

Every row a single seller owns names that seller in its own table; every row
a single customer owns names that customer — so ownership scoping is a
one-column predicate everywhere.

## Outcome

For each table whose rows belong to exactly one seller, the table has a
seller_id column populated on every row; for each table whose rows belong to
exactly one customer, a customer_id column populated on every row. Admin
visibility is platform-wide, so no admin ownership column exists anywhere. A
test enforces the invariant across the models so a future owner-carrying
table cannot omit the column, and the full suite passes against a fresh
database.

## Why it matters

The prototype ships to 10 sellers on SQLite with a possible Postgres
migration later. A denormalized owner key on every owned row makes app-level
tenant scoping mechanical today and makes Postgres RLS a one-line policy per
table later; without it, the migration inherits schema surgery.

## Discovery notes

Migrations are editable in place — `make fresh` rebuilds; no
alter-migrations or backfills needed, and seeders repopulate. Already
correct: listings, payouts, ledger_entries (seller_id); carts, orders,
favorites, customer_blocks, customer_merges (customer_id); conversations
(both, nullable by design — a thread names its parties). Judgment calls for
the maker: orders span sellers (fulfillments are unique per order+seller),
so orders stay customer-owned and seller ownership lives on order_items /
fulfillments; payments are order-level and multi-seller, so customer_id
only; messages reach both parties in one hop via conversation_id — decide
whether the invariant covers them or the conversation's columns suffice;
notifications already carry their owner as the polymorphic
notifiable_type/notifiable_id pair. No owner: sessions, cache, jobs, admins,
magic_links (pre-auth, email keyed), page_view_counts (platform aggregate),
categories/properties/property_values/category_properties (shared catalog).
Index the new columns where seller- or customer-scoped queries will run.

## Related work

- FEAT-003 (commerce schema)
- FEAT-011 (messaging schema)
- FEAT-018 (prefixed ULIDs on every table — the prior all-tables sweep)
- FEAT-025 (configurator tables)
- RFCTR-002 (policies and seller base)

## Working

### Judgment calls

- **listing_events.customer_id stays, seller_id is added.** The existing
  nullable `customer_id` names the shopper who triggered the event (an actor
  reference for an anonymous-capable view/favorite/cart-add); it answers "who
  did this," not "who owns this row." Ownership answers "whose listing" —
  the new, always-populated `seller_id`. The two columns carry different
  facts and both stay.
- **messages are not in the invariant.** A conversation carries `seller_id`,
  `customer_id`, and `admin_id` as nullable party columns (docs/database.md
  §1 already exempts it — "a thread names its parties"), so a conversation
  can hold any combination of the three; a message inside it does not belong
  to exactly one seller or one customer the way the invariant requires. A
  seller's or customer's inbox already scopes off `conversations` directly,
  one hop from `messages.conversation_id` rather than the multi-hop chains
  this ticket targets, so messages need no owner column of their own.
- **New owner columns sit right after the row's primary parent FK**, mirroring
  `order_items`' existing `order_id, listing_id, seller_id` ordering: e.g.
  `option_values` reads `axis_id, seller_id, property_value_id, …`.
- **Every new FK cascades on delete**, matching each table's existing FK
  choices (a seller's or customer's own id column already cascades
  everywhere in this schema).
- **Factories default the new column via an independent `Seller::factory()` /
  `Customer::factory()`**, the same pattern `OrderItemFactory`,
  `FulfillmentFactory`, and `RefundFactory` already used for `seller_id` /
  `issued_by_id` before this ticket — not derived from the row's own parent
  factory. A caller that needs the owner to match its parent (every real
  write path, and any test asserting on it) passes both explicitly, the way
  `PlaceOrder` already did for `order_items.seller_id`.
- **`CustomerOwnedTables` (the customer-merge manifest) needed updating.**
  `order_items`, `fulfillments`, `payments`, and `refunds` are 1:1 with a row
  already blindly re-pointed by `MergeAnonymousCustomer` (their parent
  `orders` row), so they join `CustomerOwnedTables::all()` for the same blind
  `UPDATE … WHERE customer_id = ?`. `cart_items` cannot: carts are folded
  (deduplicated and quantity-summed), not re-pointed, so `cart_items` joins
  `leftBehind()` and `MergeAnonymousCustomer::foldCart()` was fixed to set
  `customer_id` on the lines it recreates — a second instance of the same
  gap this ticket closes everywhere else, caught by the pre-existing
  `CustomerOwnedTablesManifestTest`.

### Files touched

- 20 migrations under `database/migrations/` — one `seller_id` or
  `customer_id` foreignUlid (30, cascade) per owned table, plus an index
  (paired with each table's existing status/timestamp column where one
  exists, e.g. `units` → `[seller_id, state]`, `listing_events` →
  `[seller_id, occurred_at]`).
- 20 models under `app/Models/` — `#[Fillable]` gains the column, a
  `seller()`/`customer()` `BelongsTo` relation added.
- 20 factories under `database/factories/` — the column defaults to an
  independent `Seller::factory()` / `Customer::factory()`.
- Actions: `RecordListingEvent`, `RemoveListing`, `PublishListingFaq`,
  `SetListingAttributes`, `AddListingImage`, `CreateListing`,
  `CreateOptionAxis`, `AddOptionValue`, `CreateVariant`, `GenerateVariants`,
  `AddUnit`, `CreateModifier`, `AddModifierOption`, `SetModifierScope`,
  `ScopeModifier`, `AddQuantityBreak`, `AddDescriptionSection`, `AddToCart`,
  `PlaceOrder`, `FinalizeOrder`, `IssueRefund`, `MergeAnonymousCustomer`
  (the `foldCart()` fix).
- `App\Domain\Customers\CustomerOwnedTables` (`all()`/`leftBehind()`) and its
  sidecar test's exact-array assertion.
- Seeders with a direct `::create()` call: `ListingSeeder`,
  `ConfiguratorArchetypeSeeder`, `WizardingSellerSeeder`,
  `ListingImageSeeder`.
- `tests/CommerceTestCase.php`'s `attribute()` helper.
- `tests/OwnershipTest.php` — new, the invariant test (see below).
- Six pre-existing test files that built an owned row directly through a
  relation's `->create()`/`->firstOrCreate()` rather than through an action
  or a model factory, and so needed the new column added by hand:
  `app/Models/CartItemTest.php`,
  `app/Support/Configurator/ScopedListingPreviewTest.php`,
  `app/Support/Configurator/ListingConfiguratorSummariesTest.php`,
  `app/Http/Controllers/Seller/ModifierControllerTest.php`,
  `app/Http/Controllers/Seller/ListingControllerTest.php`,
  `app/View/Components/Seller/BuyerViewTest.php`.

### Tests

- `tests/OwnershipTest.php` — the invariant test. Walks an explicit list of
  every seller-owned and customer-owned model and asserts, per model: the
  schema carries the owner column, the model's `Fillable` includes it, and a
  model built through its own factory leaves the column populated. Two
  further tests exercise the real action-level write paths end to end
  (listing-side and order-side) and assert the owner id matches the parent's.
- `App\Domain\Customers\CustomerOwnedTablesTest` — updated exact-array
  assertion for `CustomerOwnedTables::all()`.
- `App\Actions\Customers\CustomerOwnedTablesManifestTest` (pre-existing,
  unchanged) — the schema-vs-manifest check that caught the missing
  `cart_items` entry and the `foldCart()` gap.

### Verification

- `make fresh`: migrations and every seeder green; spot-check query against
  the fresh database shows zero `NULL` owner columns across all 20 tables.
- `make test`: 3227 passed.
- `make precommit`: green (Pint, PHPStan, full ungated suite).
