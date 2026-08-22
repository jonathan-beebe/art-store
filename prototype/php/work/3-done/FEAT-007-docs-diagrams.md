---
id: FEAT-007
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-007: Docs folder with sequence, flow, state, and ER diagrams

## Problem
`docs/architecture.md` captures the intended shape. Once FEAT-002 through FEAT-006 land, the implemented flows (magic link, anonymous merge, guest checkout, fulfillment, escrow and payout) have no diagrams a reviewer can read, and the architecture doc may have drifted from the code.

## Goal
A reviewer can understand every end-to-end flow from `docs/` without reading code.

## Outcome
- `docs/architecture.md` matches the code (table names, route names, statuses, folder layout); stale statements are corrected.
- `docs/identity.md`: sequence diagrams for seller magic-link sign-in and for customer guest verification with anonymous merge; a flowchart of `ResolveCustomerIdentity`.
- `docs/orders.md`: sequence diagram of checkout → finalize → seller notification; state diagrams for `OrderStatus` and `FulfillmentStatus` matching the enums' transition tables.
- `docs/escrow.md`: flowchart of ledger entry types through hold → release → payout; a sequence diagram of `payouts:run`; a worked example with numbers.
- `docs/data-model.md`: ER diagram generated from the actual migrations.
- `docs/README.md` indexes the docs.
- Every diagram is Mermaid, preceded by the question it answers, and uses names that appear in the code.

## Why it matters
The prototype's purpose is end-to-end evaluation by the team; diagrams are how the team reads the flows.

## Discovery notes
Follow the `diagramming` skill. Derive transitions from the enum sidecar tests rather than from memory. Verify route names with `php artisan route:list` in the container.

## Working

### Drift corrected in `architecture.md`

- Order status: the old doc had `[*] --> paid` for a verified customer placing
  an order with an approved card. No such edge exists in
  `OrderStatus::transitions()` — every order starts at `pending_verification`
  or `awaiting_payment` (added by FEAT-003) and only reaches `paid` from one
  of those. Replaced the inline diagram with a pointer to `docs/orders.md`
  (full state diagrams live there) plus a corrected one-paragraph summary.
- Listing status: added the `sold -> for_sale` transition (FEAT-003: a
  declined card restores stock).
- ER diagram: `orders ||--|| payments : paid_by` was one-to-one; `payments`
  is one row per charge attempt, so changed to `orders ||--o{ payments`.
  Removed the `magic_links }o--|| customers : verifies` edge — `magic_links`
  has no FK to `customers` or `sellers`, only an `email` string; the edge was
  never backed by a column. Noted both directions of the `customer_merges`
  self-reference on `customers` (collapsed to one labeled edge in the
  system-level diagram; both FKs are in `data-model.md`).
- Notifications: doc said "recipient type + id"; the migration is nullable
  `seller_id` + nullable `customer_id` (FEAT-003 decision — keeps both FKs
  real for the anonymous-merge re-point). Fixed the prose.
- Testing: `phpunit.xml` also scans `database/` (FEAT-006, for the seeder
  tests) — doc said `app/` and `routes/` only. Coverage numbers reworded as
  targets; actual numbers are FEAT-008's to report.
- Identity: added that the card is collected on `/orders/{order}/pay` after
  guest verification, not at initial checkout (FEAT-005 decision) — the old
  doc implied a single checkout step for everyone.

### Route names

Verified against `docker compose run --rm app php artisan route:list`
(41 routes). No mismatches against what the done tickets' Working sections
already documented — used as the source for route tables referenced from
the new docs rather than re-listing all 41 in `docs/`.

### Everything else derived from source, not memory

- `ListingStatus`, `OrderStatus`, `FulfillmentStatus` transition tables:
  read directly off `transitions()` in each enum plus its sidecar test.
- ER diagram in `data-model.md`: read off every migration in
  `src/database/migrations/` (Laravel's own tables — sessions, cache, jobs —
  omitted, nothing in the domain touches them).
- Sequence diagrams: read the actions and controllers named in each
  (`SendMagicLink`, `MagicLinkVerificationController`, `SignInSeller`,
  `SignInCustomer`, `ClaimCustomerIdentity`, `MergeAnonymousCustomer`,
  `PlaceOrder`, `FinalizeOrder`, `Notify`, `RunWeeklyPayout`,
  `RunWeeklyPayouts` console command) rather than the tickets' Working notes,
  which describe decisions but not the call sequence.
- Escrow worked example ($100.00 item -> $10.00 fee -> $90.00 net) matches
  `Fee::PLATFORM_PERCENT = 10` and `LedgerBalance::from()`'s fold exactly —
  traced through `held +9000 -> released +9000 -> paid_out -9000`.

### Contradiction for a reviewer to know about

`OrderStatus::Cancelled` is a real, tested transition target
(`pending_verification`/`awaiting_payment`/`payment_failed -> cancelled`,
verified by `OrderStatusTest`), but no controller or action in
`app/Http/Controllers/**` or `app/Actions/**` ever calls
`transitionTo(OrderStatus::Cancelled)`. Noted in `docs/orders.md` rather than
silently dropped from the state diagram — the domain supports cancellation,
the UI does not expose it yet.

### Deliverables

`docs/architecture.md` (corrected), `docs/identity.md`, `docs/orders.md`,
`docs/escrow.md`, `docs/data-model.md`, `docs/README.md`.
