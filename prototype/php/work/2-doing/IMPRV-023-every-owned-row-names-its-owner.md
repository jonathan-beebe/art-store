---
id: IMPRV-023
type: improvement
status: open
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
