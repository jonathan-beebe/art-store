---
id: FEAT-022
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-022: Admin directory — sellers, customers, listings, orders, fulfillments

## Problem
The admin site (`FEAT-010`, `FEAT-014`) has a sellers list with counts only, a customers list, a customer detail with orders, block/lift, and messaging. It has no seller detail with listings/fulfillments/payouts/balance, no customer standing filter or merge history, no favorites/cart on the customer detail, no cross-seller listings list or detail, no orders or fulfillments lists or details. `docs/alignment.md` §5 lists the pages and filters the Node admin site answers and the other two adopt.

## Goal
An operator can find and read any seller, customer, listing, order, or fulfillment on the platform from the admin site.

## Outcome
`/admin/sellers/{seller}` shows listings, fulfillments, payouts, and the folded balance; `/admin/customers?standing=all|verified|anonymous|blocked` and the detail with orders, favorites, cart, block and merge history; `/admin/listings?status=&seller=&removed=` and `/admin/listings/{listing}`; `/admin/orders?status=&customer=` and `/admin/orders/{order}` with items, payments, fulfillments, refunds; `/admin/fulfillments?status=&seller=` and `/admin/fulfillments/{fulfillment}`; every filter optional with empty meaning all; balances folded once per page; every page 404s on an unknown id; tests per page and filter; `docs/admin.md` written.

## Why it matters
The alignment brief says the three prototypes support the same CX; the admin site is where PHP and Rails are furthest behind Node.

## Discovery notes
Node's `docs/admin.md` (on `main`) is the reference for pages, filters, and the folded-balance rule. Laravel idiom: resource controllers under `Admin\`, query scopes for filters, Blade components for the tables, policies already gate the admin guard.

## Related work
- docs/alignment.md §5
- FEAT-010, FEAT-014

## Working

Built, one page at a time, each with its route, filter scopes, Blade view and
sidecar test:

| Path | What it shows |
| --- | --- |
| `GET /admin/sellers` | counts as before, plus held / available / paid out folded from one read of the ledger |
| `GET /admin/sellers/{seller}` | listings, fulfillments, payouts, escrow balance |
| `GET /admin/customers?standing=all\|verified\|anonymous\|blocked` | anonymous rows included; order, favorite and cart-line counts |
| `GET /admin/customers/{customer}` | orders, favorites, cart, block history, merge history |
| `GET /admin/listings?status=&seller=` | every listing across every seller |
| `GET /admin/listings/{listing}` | detail with view / favorite / cart-add counts and every order line it sold on |
| `GET /admin/orders?status=&customer=` | every order with customer, item count, total |
| `GET /admin/orders/{order}` | items, payment attempts, fulfillments |
| `GET /admin/fulfillments?status=&seller=` | every fulfillment with order and seller |
| `GET /admin/fulfillments/{fulfillment}` | lines, money, ledger entries |

`docs/admin.md` is written and linked from `docs/README.md`; it carries the
page table, the one-guard / one-404 diagram, and the folded-balance sequence.

Rules honoured:

- **Balances folded once.** `LedgerEntry::balancesBySeller()` reads the summed
  `(seller, type)` rows once and hands them to the new
  `App\Domain\Escrow\LedgerBalances`, which answers per seller and returns a
  zero balance for a seller with no entries. `SellerControllerTest` counts the
  queries that touch `ledger_entries` on `/admin/sellers` and holds it to one.
- **One guard.** The `auth.admin` middleware already sits on the whole group in
  `routes/admin.php`; the six new routes joined the group and added no check of
  their own.
- **404 for every miss.** Every detail route is route-model bound, so a wrong
  prefix, a bare ULID, nonsense, and an unknown id are one page. Each new
  controller test carries the four-case dataset; `SellerControllerTest` gained
  the one it was missing.
- **Aggregation in the database.** `withCount` for listing / fulfillment /
  order / favorite / cart-line / item counts, `group by` for the ledger fold
  and the listing status tally.

Filters are query scopes on the models — `Listing::ofStatus` / `ofSeller`,
`Order::ofStatus` / `ofCustomer`, `Fulfillment::ofStatus` / `ofSeller`,
`Customer::inStanding` — each taking a nullable value and adding no clause when
it is null. Controllers read `$request->enum(...)` for a status and
`$request->filled(...)` for an id, so an absent, empty or unrecognised value is
one answer: all rows. Repeated tables became anonymous Blade components under
`resources/views/components/admin/`.

### Deliberately left out

- `removed=any|removed|visible` on `/admin/listings`, the removal reason on a
  listing detail, and the removal / lift actions — **FEAT-024**, which creates
  `listing_removals`. The listings table and detail page have room for the
  column and the panel; nothing is stubbed.
- The refunds section on `/admin/orders/{order}`, the `refunded` status values,
  and the Cancel / Refund actions on the order and fulfillment detail pages —
  **FEAT-020**. Both detail pages end with their last section, so a refunds
  section and an actions form slot in without moving anything.
- `/admin/payouts` and the platform payout run — **FEAT-024**. The seller
  detail page shows payout history read-only.
- `/admin` tallies and platform money, `/admin/accounting`, `/admin/ledger`,
  `/admin/stats`, the page-view roll-up — **FEAT-023**. `/admin` stays the
  console's front door and now links to all five directory pages.

### Deviations from §5

- An unrecognised filter value (`?status=nonsense`) shows every row rather than
  answering 400 the way Node's `optionalFilter` does. Laravel's
  `$request->enum()` reads absent, empty and unrecognised alike, and the
  console only ever submits a real value; the behaviour is written down in
  `docs/admin.md`.
- The customers list shows a `Verified` / `Unverified` / `Anonymous` standing
  where it used to show `OK`, so the column and the filter say the same words.
- `PaymentStatus::label()` was added beside the other status enums' labels, for
  the payments table on the order detail page.

### Review fix-ups

A review of `1970839` found no blocking defects and two small gaps, closed here:

- The customer detail page's order table was rewired to `<x-admin.orders-table>`
  but nothing asserted an order renders there. `CustomerControllerTest`'s
  order-fixture case now asserts the order's id on the page, so all five
  sections the ticket names — orders, favorites, cart, block history, merge
  history — are covered on their positive path.
- `PaymentStatus::label()` was `ucfirst($this->value)` where every sibling
  status enum reads `ucfirst(str_replace('_', ' ', $this->value))`. It matches
  its siblings now.
