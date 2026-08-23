# Work Journal

## Next ticket numbers

- RSRCH: 1
- DSGN: 1
- ARCH: 1
- FEAT: 9
- IMPRV: 1
- MAINT: 1
- A11Y: 1
- RFCTR: 14
- BUG: 1

## Log

- 2026-08-22:19:05:52 — RFCTR-003 — started
- 2026-08-22:19:03:43 — RFCTR-002 — done: four shared test cases collapsed into test_helper plus test/support (TestRecords, IntegrationHelpers), every test file requires test_helper and subclasses a stock Rails base, 205 call sites renamed to the create_* builders off the parsed AST, README and architecture.md updated, 645 runs at 100% line coverage (this commit)
- 2026-08-22:18:54:09 — RFCTR-002 — started
- 2026-08-22:18:48:05 — RFCTR-001 — done: 109 test files moved under test/ mirroring app/ (git mv), domain tests require test_helper and subclass ActiveSupport::TestCase, Zeitwerk ignore and SimpleCov skip for *_test.rb removed, bin/rails test with no arguments runs all 645 at 100% line coverage, Makefile/README/docs updated (d4044ad)
- 2026-08-22:18:42:00 — RFCTR-001 — started
- 2026-08-22:18:40:00 — RFCTR-013 — defined: Resourceful storefront controllers behind the existing URLs
- 2026-08-22:18:40:00 — RFCTR-012 — defined: Deliver magic links through Action Mailer
- 2026-08-22:18:40:00 — RFCTR-011 — defined: Dissolve the Domain namespace and the custom autoloader
- 2026-08-22:18:40:00 — RFCTR-010 — defined: Notifications address a polymorphic recipient
- 2026-08-22:18:40:00 — RFCTR-009 — defined: Escrow balance and weekly payout on LedgerEntry, Seller and Payout
- 2026-08-22:18:40:00 — RFCTR-008 — defined: Shipping and delivery on Fulfillment
- 2026-08-22:18:40:00 — RFCTR-007 — defined: Placing, paying and rolling up an order on Order
- 2026-08-22:18:40:00 — RFCTR-006 — defined: Cart and favorites behaviour on Cart and Customer
- 2026-08-22:18:40:00 — RFCTR-005 — defined: Listing validations, the listing form, and status transitions on Listing
- 2026-08-22:18:40:00 — RFCTR-004 — defined: Identity behaviour lives on MagicLink, Seller and Customer
- 2026-08-22:18:40:00 — RFCTR-003 — defined: Use the ActiveSupport built-in where the code reimplements one
- 2026-08-22:18:40:00 — RFCTR-002 — defined: One test base, one declaration style
- 2026-08-22:18:40:00 — RFCTR-001 — defined: Run the suite with bin/rails test from a conventional test/ tree
- 2026-08-22:17:21:34 — FEAT-008 — done: clean first run from an empty tree in 40s, 645 tests green at 100% line coverage, make smoke walking the whole product in one integration test, a curl walk over every page and a live guest checkout to Paid, ShipmentDetails extracted out of the controller, dead scaffolding deleted, docs/review.md mapping the brief to routes and tests, README completed
- 2026-08-22:22:14:59 — FEAT-007 — done: corrected architecture.md drift (storefront visibility, fee timing, missing payment_failed->cancelled edge, test runner scope, coverage thresholds, action namespaces, ledger_entries.entry_type); added identity.md, orders.md, escrow.md, data-model.md, ontology.md, README.md; 13/13 Mermaid diagrams validated
- 2026-08-22:22:02:42 — FEAT-007 — started
- 2026-08-22:17:01:59 — FEAT-008 — started
- 2026-08-22:16:59:20 — FEAT-004 — done: seller portal behind require_seller!, dashboard, listings CRUD with dollar prices and image upload, listing activity with a 14-day breakdown, orders grouped by fulfillment status with mark-shipped, earnings with LedgerBalance and the weekly payout control, notifications inbox
- 2026-08-22:16:55:27 — FEAT-005 — done: customer storefront — paged search and medium filter over for_sale listings, listing page with view events, favorites, cart, guest checkout that verifies by magic link before the card form on /orders/:id/pay, signed-in one-request checkout, declines with a retry form, orders with per-seller fulfillment and delivery confirmation, and account notifications
- 2026-08-22:16:39:45 — FEAT-006 — done: seed data ported from the PHP spike — 4 verified sellers, 29 listings (24 for_sale / 3 draft / 2 sold across six media), casey@example.com with 3 favorites and view history, and order history through the FEAT-003 actions (paid, shipped, delivered-and-paid-out); db/seeds_test.rb sidecar, README "Seeded accounts" section, db added to the test/coverage Makefile targets
- 2026-08-22:16:34:05 — FEAT-005 — started
- 2026-08-22:16:32:01 — FEAT-006 — started
- 2026-08-22:16:31:09 — FEAT-004 — started
- 2026-08-22:16:27:01 — FEAT-003 — done: commerce domain core (listings, payments, orders, cart, escrow, notifications), 13 migrations and thin models, cart/order/fulfillment/escrow/notification actions, payouts:run, end-to-end lifecycle and declined-then-retry tests
- 2026-08-22:16:25:18 — FEAT-002 — done: magic-link sign-in for sellers and customers, anonymous customer identity in a signed cookie with merge-on-verify, MagicLinkDelivery port, /account page and sign-out on both sites
- 2026-08-22:16:06:24 — FEAT-003 — started
- 2026-08-22:16:05:34 — FEAT-002 — started
- 2026-08-22:16:02:58 — FEAT-001 — done: dockerized Rails 8.1 app on ruby:3.3-slim, Domain-namespaced sidecar Minitest with SimpleCov, Tailwind, shop and seller layouts with placeholder pages and the debug alert partial, README
- 2026-08-22:15:44:45 — FEAT-001 — started
- 2026-08-22:15:41:08 — FEAT-008 — defined
- 2026-08-22:15:41:08 — FEAT-007 — defined
- 2026-08-22:15:41:08 — FEAT-006 — defined
- 2026-08-22:15:41:08 — FEAT-005 — defined
- 2026-08-22:15:41:08 — FEAT-004 — defined
- 2026-08-22:15:41:08 — FEAT-003 — defined
- 2026-08-22:15:41:08 — FEAT-002 — defined
- 2026-08-22:15:41:08 — FEAT-001 — defined
