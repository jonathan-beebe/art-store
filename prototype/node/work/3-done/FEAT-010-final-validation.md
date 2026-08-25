---
id: FEAT-010
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-010: Final validation — clean first run, smoke test, coverage, review, README

## Problem
Nine tickets landed from several agents in parallel. Nobody has yet run the product from an empty tree, walked every page, or checked the brief line by line against what exists.

## Goal
The prototype is demonstrably complete against the brief and a reviewer can run and judge it from the README.

## Outcome
- From an empty tree (no `node_modules`, no sqlite, no `public/app.css`), `make up` alone serves every site; every page answers 200 with the stylesheet linked.
- `make test` green: typecheck, lint, full suite; `make coverage` at or above thresholds.
- `app/test/smoke.test.ts` walks the whole product as described in `docs/architecture.md` → Testing, and `make smoke` runs it.
- Dead scaffolding deleted; any domain `if` found in a route extracted to the core.
- `docs/review.md` maps every requirement in the brief to the route and test that prove it, lists known gaps and next steps.
- `README.md` complete: run, serve, test, coverage, smoke, seeded accounts, magic links, paying, admin, messaging, known gaps.

## Why it matters
This is the deliverable the team reviews in the showdown.

## Discovery notes
Reference `prototype/rails/docs/review.md` and `prototype/rails/work/3-done/FEAT-008-final-validation.md`. A curl walk over the running server (seller, shop, admin pages; cross-actor 404s; a live guest checkout) is the acceptance.

## Related work
- FEAT-001 … FEAT-009

## Working

### Smoke test

`app/test/smoke.test.ts` grew from three tests to six, and between them they walk
everything `docs/architecture.md` → Testing lists.

| Test                                                                | What it proves                                                               |
| ------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| every site serves its own page and they all share one stylesheet    | The three layouts render and `/app.css` answers `text/css`.                  |
| a listing travels from the seller signing in to their weekly payout | The whole product in one walk.                                               |
| a question on a listing becomes an answer and then a published FAQ  | Anonymous ask → seller reply → publish → the answer is on the page for the   |
|                                                                     | next visitor.                                                                |
| admin removes a listing and it leaves the storefront                | Removal takes it to 404 and off the grid; the lift brings it back.           |
| admin blocks the customer and checkout refuses                      | A blocked customer with a full cart is sent back to `/cart` with the reason; |
|                                                                     | lifting the block lets checkout through.                                     |
| an admin messages a seller and the seller reads it                  | The unread badge counts up and clears.                                       |

The centrepiece follows the Rails spike's `smoke_test.rb`: named step functions in
the order a person does them, each asserting both the rendered page and the rows
written. Two cookie jars — the seller keeps a portal session while the shopper
arrives with no cookie at all, which is what proves the storefront hands a fresh
visitor an anonymous row. The clock is fixed at Wednesday 2026-08-19 and the
payout runs `as_of=2026-08-24`, so the period the run settles is the week holding
the delivery whatever day the suite runs. The magic links are read out of the
flash the debug alert prints, not out of the database.

The steps: seller signs in through the real link flow → lists a piece over
multipart at $480.00 → marks it for sale → a fresh visitor sees it on `/` →
views it (one `listing_events` row) → favorites it → carts it → checks out as a
guest with no card field on the page → verifies the address from the emailed
link → is declined by `4000 0000 0000 0002` and the stock comes back → pays with
`4242 4242 4242 4242`, which stores only `4242` and holds $432.00 → the seller
reads "Item sold" and ships with a carrier and tracking number → the customer
confirms delivery, releasing the $432.00 → an admin runs the weekly payout → the
seller's earnings page reads `data-stat="paid_out"` $432.00 and the payout is in
the history table.

### Found and fixed: the payout run answered 500

`POST /admin/payouts` threw `UNIQUE constraint failed: payouts.seller_id,
payouts.period_start` and answered 500 whenever escrow was released into a period
that already had a payout row. Reachable from the admin form: settle a week with
an `as_of` that has not arrived yet, let another delivery land inside that week,
then run it again.

`runWeeklyPayout` now reads which sellers already hold a payout for the period
and skips them. The money is not lost — the next period's run reads the whole
ledger up to its own later window and pays it then, which
`app/actions/escrow/run-weekly-payout.test.ts` proves
(`money released into a period that was already settled waits for the next run`).

### Code pass

**One domain rule moved into the core.** `changeStatus` in
`app/sites/seller/routes/listings.ts` compared a status to `'for_sale'` to
decide whether a removal blocked the move, and
`app/sites/seller/listing-transitions.ts` wrote the same rule a second time for
the status buttons. Both are now
`isBlockedByRemoval(to, hasActiveRemoval)` in
`app/core/listings/listing-status.ts`, with
`availableListingTransitions(status, hasActiveRemoval)` defined as the
transition table filtered through it. `listing-transitions.ts` and its test are
deleted; no site file holds a status literal any more (`grep` over
`app/sites/**` for a comparison against any listing, order, fulfillment,
ledger, or removal state finds none).

**`dollarsInputValue` moved to `app/core/money.ts`.** It divides cents by 100,
which is money math, and it is the inverse of `parseDollars` sitting beside it.
It was in `app/sites/seller/format.ts` with the date formatters. `grep` over
`app/sites/**` now finds no arithmetic on cents at all.

**One `statusLabel` for three sites.** `app/core/shop/status-label.ts`,
`app/core/reports/status-label.ts`, and `formatLabel` in
`app/sites/admin/page.ts` were three identical implementations of
`for_sale` → `For sale`, one per site. They are now
`app/core/status-label.ts`, imported by the storefront's `shopPage`, the four
seller route files, and `adminPage`, which hands templates `statusLabel` — 24
call sites across nine admin views renamed.

**One `parseIdParam` for three sites.** The `:id`-as-a-positive-integer parse
was written eight times (`app/sites/seller/params.ts`, four admin route files,
and four storefront files). It is now `app/plugins/id-param.ts`, beside
`form-body.ts` from BUG-001, for the same reason: a request-level helper that
is not a site, an adapter, or a view. The parsers keyed on something other than
`id` — `{ id, faqId }` on the seller's FAQ routes, `fulfillmentId` on two
storefront routes — are left alone; substituting there would change what they
read.

**Nine dead exports deleted**, each confirmed by grep to have no caller
anywhere in `app/**` outside its own sidecar test, and none reached from inside
its own module: `payoutPeriodCovers`, `isConversationKind`, `admitsActor`,
`inboxPath`, `tallyTotal`, `totalActivity`, `totalListings`, `isActorType`,
`resultingCustomerId`, with their test cases.

An audit of every comment in `app/**` against `write-comment` found no
violation: no ticket numbers, no commit SHAs, no `file.ts:NN` references, no
change history, no commented-out code, no restatements. Nothing to fix.

Not deleted, though a cross-file grep calls them unused: `ORDER_STATUS_TRANSITIONS`,
`canTransitionListing`, `placeholderImageSvg`, `MESSAGE_BODY_MAX_LENGTH` and
about twenty more. Each is consumed inside its own module by a sibling export
that callers do use, so the only thing dead about them is the `export` keyword,
and dropping that would delete the test that covers the rule.

### Clean first run

`make down`, then `src/node_modules`, both SQLite files with their `-wal` and
`-shm` siblings, `src/public/app.css`, and everything under
`src/public/uploads/` removed (`.gitkeep` kept), then `make build` and `make up`
alone.

| Step                                                       | Time |
| ---------------------------------------------------------- | ---- |
| `make build` (cold image cache, base image already pulled) | 14s  |
| `make up` → `/` answering 200                              | 13s  |

Inside the 13s: `npm ci` installed 230 packages in 9s, ten migrations applied
from nothing, `seed.ts` wrote `seeded 2 admins` then `seeded 4 sellers, 29
listings, 5 customers, 3 orders, 98 page-view rows, 4 conversations, 11
messages, 1 listing FAQ.`, and Tailwind built a 25,574-byte `public/app.css`.
Nothing in the README or `docker/entrypoint.sh` needed correcting.

### Curl walk

116 checks, 0 failures, no 500 anywhere, against the freshly seeded stack on
<http://localhost:4000>. Recorded in full in `docs/review.md` → Verified on
FEAT-010. In outline: sign-in through the debug alert's magic link for all
three actor types; every GET page on all three sites 200 with `/app.css`
linked (9 storefront, 12 seller, 23 admin, including every filtered table);
cross-actor and non-numeric ids 404 on reads and writes; a listing created
through the portal with a real multipart PNG served back from `/uploads/`; a
live guest checkout declined by `4000 0000 0000 0002` then paid by `4242 4242
4242 4242`; ship, confirm delivery, and an admin payout run moving $432.00 from
available to paid out; an ask that became a published FAQ; a removal that took
the piece to 404 and a lift that brought it back; and the seeded blocked
customer refused at add-to-cart and at checkout.

`node --watch` in the container does not see every bind-mount write, so
`docker compose restart app` is what picks a change up before a walk.

### Numbers

| Gate            | Result                                                                           |
| --------------- | -------------------------------------------------------------------------------- |
| `make test`     | 1,161 tests, 1,161 pass, 0 fail; `tsc --noEmit` and eslint clean                 |
| `make coverage` | 99.42% lines, 95.23% branches, 98.85% functions, exit 0 against the 90 / 80 gate |
| `make smoke`    | 6 tests, 0 fail, 0.78s                                                           |

248 source `.ts` files (12,710 lines), 190 sidecar test files (18,866 lines),
57 EJS templates (2,821 lines).

### Deliverables

- `docs/review.md` — a table per section of the brief with Requirement | Status
  | Evidence, the numbers above, seven numbered known gaps with a next step
  each, and a Stack notes section for the showdown.
- `README.md` — rewritten. The stale "What exists today" section, which claimed
  messaging did not exist, is gone; first run now says `make build` then
  `make up` with the measured times; the seeded-accounts table carries all four
  sellers, both admins, the blocked customer and the three anonymous browsers;
  the layout tree is generated from the real tree.

### Left for the orchestrator

`work/journal.md` carries FEAT-009's lines as well as mine, so it is not
committed here.
