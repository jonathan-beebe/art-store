# Work Journal

## Next ticket numbers

- RSRCH: 1
- DSGN: 1
- ARCH: 1
- FEAT: 10
- IMPRV: 1
- MAINT: 1
- A11Y: 1
- RFCTR: 1
- BUG: 1

## Log

- 2026-08-22:15:09:29 — FEAT-009 — done: `docs/ontology.md` — 26 entities (roles, catalog, buying, money, identity/messaging, decisions), one concept-level Mermaid diagram (10 boxes, 3 subgraphs), and Vocabulary notes covering the seller portal's "Orders" = Fulfillments
- 2026-08-22:15:07:59 — FEAT-008 — done: Clean first run from an empty checkout, 471 tests green at 98.20% lines (100% on app/Domain), a `make smoke` end-to-end walk from seller sign-in to weekly payout, gd added so listing uploads are content-verified, and docs/review.md mapping the brief to routes and tests
- 2026-08-22:15:06:29 — FEAT-009 — started
- 2026-08-22:15:05:01 — FEAT-009 — defined: Domain ontology doc
- 2026-08-22:14:58:18 — FEAT-007 — done: Corrected `architecture.md` drift (order status, listing status, ER diagram, notifications columns, phpunit scan paths, coverage targets) and added `docs/identity.md`, `docs/orders.md`, `docs/escrow.md`, `docs/data-model.md`, `docs/README.md` with sequence, state, flowchart, and ER diagrams
- 2026-08-22:14:52:08 — FEAT-007 — started
- 2026-08-22:14:51:31 — FEAT-008 — started
- 2026-08-22:14:49:05 — FEAT-005 — done: Customer storefront — browse and search, favorites, cart, guest checkout that verifies by magic link before the card, order pages with retry and delivery confirmation, and account notifications
- 2026-08-22:14:47:21 — FEAT-004 — done: Seller portal — dashboard, listings with create/edit/status/image upload, per-listing activity, fulfillment and mark-shipped, earnings with balances, payouts, and a payout-run button, and notifications
- 2026-08-22:14:41:02 — FEAT-006 — done: Four demo sellers, 29 listings across six media, a verified customer with favorites, and order history through cart/place/finalize/ship/deliver/payout actions ending in one completed weekly payout
- 2026-08-22:14:33:29 — FEAT-006 — started
- 2026-08-22:14:31:20 — FEAT-005 — started
- 2026-08-22:14:29:07 — FEAT-004 — started
- 2026-08-22:14:52:10 — FEAT-003 — done: Commerce schema, pure domain core for listings, cart, payments, orders, escrow, and payouts, and the order lifecycle actions from cart to weekly payout
- 2026-08-22:14:20:23 — FEAT-002 — done: Passwordless magic-link sign-in for both sites, anonymous customer identity in an encrypted cookie, and claim-or-merge on verification
- 2026-08-22:14:02:37 — FEAT-002 — started
- 2026-08-22:14:00:52 — FEAT-003 — started
- 2026-08-22:13:57:38 — FEAT-001 — done: Dockerized Laravel 13 on PHP 8.3 with sidecar PHPUnit, pcov coverage, Tailwind v4 build, and the two site layouts
- 2026-08-22:13:47:39 — FEAT-001 — started
- 2026-08-22:13:46:56 — FEAT-008 — defined: Final validation, review, and end-to-end smoke
- 2026-08-22:13:46:56 — FEAT-007 — defined: Docs folder with sequence, flow, state, and ER diagrams
- 2026-08-22:13:46:56 — FEAT-006 — defined: Seed data and demo reset command
- 2026-08-22:13:46:56 — FEAT-005 — defined: Customer storefront with cart, checkout, and guest verification
- 2026-08-22:13:46:56 — FEAT-004 — defined: Seller portal for listings, activity, fulfillment, and earnings
- 2026-08-22:13:46:56 — FEAT-003 — defined: Commerce domain core, schema, and order lifecycle actions
- 2026-08-22:13:46:56 — FEAT-002 — defined: Magic-link identity for sellers and customers with anonymous merge
- 2026-08-22:13:46:56 — FEAT-001 — defined: Dockerized Laravel foundation with sidecar PHPUnit and Tailwind
