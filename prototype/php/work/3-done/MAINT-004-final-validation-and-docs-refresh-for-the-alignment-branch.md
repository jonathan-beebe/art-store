---
id: MAINT-004
type: maintenance
status: resolved
created: 2026-08-23
---

# MAINT-004: Final validation and docs refresh for the alignment branch

## Problem
After MAINT-003, FEAT-018..024, IMPRV-004/005, and BUG-003 land, `docs/` (architecture, data-model, ontology, orders, escrow, identity, messaging, review) describe the pre-alignment code (`ontology.md` already predates admin and messaging; `data-model.md` omits columns), `README.md` lists old make targets and test counts, and nobody has run the whole thing from a clean tree with the hook installed.

## Goal
The branch ships with docs that match the code and a clean-tree run that proves the commit gate, the seeds, and every route.

## Outcome
`make check` passes from a clean tree; `make fresh` seeds; every GET route from `make routes` answers without a 5xx; every doc under `docs/` and the README state what the code does after alignment, `docs/admin.md` exists, `docs/review.md` lists the known gaps that remain, and the comparison section against Node is current; the pre-commit hook is shown refusing a commit with a failing test (recorded in the ticket's Working notes).

## Why it matters
The three prototypes are compared by reading their docs and running their make targets; stale docs lose the comparison for the wrong reason.

## Discovery notes
FEAT-017 is the pattern: an independent audit agent reads `docs/` against `src/app` and lists mismatches before anyone rewrites.

## Related work
- FEAT-017, MAINT-002
- docs/alignment.md

## Working

### A. Audit — `docs/` against `src/app`, before any rewrite

Read every doc under `prototype/php/docs/` plus the prototype `README.md`
against `src/app`, `src/database/migrations`, and the `` section of
every closed ticket in `work/3-done/` for MAINT-003, FEAT-018..024, IMPRV-004,
IMPRV-005, and BUG-003. Mismatches found:

1. **`README.md`** — test counts stale (`1107 tests (2491 assertions)`);
   current is 1827 / 4934. Everything else (make-target table, seeded
   accounts, layout tree) already matches the code.
2. **`docs/review.md`** — the most out of date doc, and the one that matters
   most:
   - Engineering-quality table still reads `1107 tests, 2491 assertions` and
     `448 files` for Pint.
   - No rows anywhere for what landed on this branch: prefixed ULID ids,
     structured JSON logging, the order-lifecycle back half (cancel / sweep /
     decline / refund), rate limits and security headers, the nine-page admin
     directory, the admin dashboard/accounting/ledger/stats pages, admin
     listing moderation, the payout run moving to the admin site, the
     checkout refusal that lists every blocked line, and the customer-merge
     fold.
   - Known gap #2 ("the payout button pays every seller") is obsolete — the
     seller-portal button is gone; the payout run lives on the admin site
     only.
   - Known gap #4 ("the cart's Checkout button stays live on an unavailable
     line") is obsolete — IMPRV-004 disables the button while any line is
     blocked.
   - Known gap #9 ("a blocked customer's ask leaves an empty thread") is
     obsolete — BUG-003 closed it; a refused first post now rolls the opened
     conversation back with it.
   - The "Compared to Node" section predates every alignment ticket and needs
     the new shared surface (ids, logging, rate limits, admin) folded in.
3. **`docs/ontology.md`** — predates admin and messaging entirely, confirmed:
   no entity section for Admin, Conversation, Message, Listing FAQ, Customer
   block, Listing removal, or Page view count. Every other entity (Seller,
   Customer, Listing, Cart, Order, Payment, Refund, Fulfillment, Ledger
   entry, Payout, Magic link, Customer merge, Notification) is already
   current — refunds and the fulfillment-grouped ledger fold are described
   correctly, so this ticket's rewrite is additive, not a full pass.
4. **`docs/data-model.md`** — the ER diagram and prefix table are missing the
   two tables FEAT-023 and FEAT-024 added: `listing_removals` (`rmv`) and
   `page_view_counts` (`pvc`). Everything else (ids, refunds, ledger,
   messaging tables) is already current.
5. **`docs/orders.md`** — names FEAT-024 directly and describes the removal
   check as not yet wired in ("`Removed` waits on FEAT-024 to wire an admin
   listing removal in — every caller passes `hasActiveRemoval: false`"). This
   shipped with FEAT-024; `Cart::placementPlan()` and `Order::placementPlan()`
   now read `$item->listing->hasActiveRemoval()`. Rest of the doc (both state
   diagrams, the lock, the 422 refusal, decline/refund) is current.
6. **`docs/architecture.md`** — two stale spots:
   - "The rest of §2.3 waits on the features that emit it: `rate_limit.exceed`
     … and `moderation.remove_listing` and `moderation.lift_listing_removal`
     …" — both shipped (FEAT-021, FEAT-024); the event list above the
     sentence is also missing all three.
   - The escrow-and-payouts section still says "The seller portal exposes a
     debug 'Run weekly payout now' button for testing" — the button is gone;
     payouts run from the admin site only (FEAT-024).
7. **`docs/escrow.md`** and **`docs/admin.md`** — both carry the sentence
   "the 'run payouts' debug button this prototype started with is gone,"
   which narrates history rather than stating current behaviour (project
   doctrine: docs describe the code as it is, never the past). Restated as a
   present-tense fact.
8. **`docs/escrow.md`** — one line names a ticket directly: "the
   `/admin/accounting` page that surfaces both lands with FEAT-023." The page
   exists now; restated without the ticket reference.
9. **`docs/identity.md`**, **`docs/messaging.md`** — already current: the
   double-consume fix (BUG-003), the empty-thread fix (BUG-003), and the
   cart/favorites merge fold (IMPRV-005) are all already described correctly.
   No changes needed.

No other doc under `docs/` referenced stale behaviour.

### Known gaps carried into `docs/review.md`

Replaced the three obsolete gaps above and added, from the tickets' own
Working notes:

- The seller-listings index has an N+1 on `currentRemoval()`; the admin
  listings list does not (it eager-loads `activeRemoval`).
- `docs/alignment.md` §1 says an id is minted "from the clock the action
  already receives"; this prototype mints from Laravel's freezable
  `Date::now()` inside `Model::save()`, not from the action's own
  `DateTimeImmutable $now` parameter.
- The `http.request` `will` line carries `request_id` alone; `session_id`,
  `actor_type`, and `actor_id` join starting with the lines the `web` group
  writes (`NameRequestVisitor` runs after `LogRequestStory`, which sits ahead
  of every group so a 404 and a 419 are still logged).
- An unrecognised filter value on an admin list (`?status=nonsense`) is
  treated the same as an absent one and shows every row; Node answers 400.
- A merged cart keeps a line whose listing carries an active removal at
  whatever quantity it clamps to; checkout refuses it later rather than the
  merge dropping it.
- A cancelled order notifies every seller on it, including one who was never
  told the order existed if it was never paid (`ItemSold` fires at payment) —
  kept for contract symmetry with `docs/alignment.md` §4.4, flagged in
  FEAT-020's own notes as worth revisiting as a product call.


### Audit — what the docs said and what the code does

- `README.md` — already current. The make-target table and the test counts
  were kept up to date by the tickets that changed them.
- `docs/review.md` — the stale one, and the one a reader judges the prototype
  by. Its engineering-quality table still read `1107 tests, 2491 assertions`
  and `448 files`; three of its ten known gaps had since been closed by the
  alignment work and were therefore actively wrong:
  - "The payout button pays every seller" — the seller-portal control is gone;
    the payout runs from `/admin/payouts` and the CLI only.
  - "The cart's Checkout button stays live on an unavailable line" — the cart
    marks every blocked line and disables the control while any exists.
  - "A blocked customer's ask leaves an empty thread" —
    `OpenConversationWithMessage` opens the thread, checks the post gate, and
    posts in one transaction, so a refused first post leaves no row.
  Its "Suggested next steps" still proposed scoping the payout button that no
  longer exists. It had no account of the alignment contract at all.

### What was rewritten

- `docs/review.md`: counts corrected to **1827 tests, 4934 assertions** over
  610 files; a new **"Against the alignment contract"** section giving §1–§6
  a status and a location; rows added to the escrow table for cancel, sweep,
  decline, refund and the three ledger timings; rows added to the admin table
  for the directory, the reporting pages, removals and the roll-up; the known
  gaps rewritten from ten to fourteen, dropping the three that are closed and
  adding the deviations the alignment tickets recorded (the minting clock, the
  `will`-line marks, the unrecognised filter value, the merged cart's removed
  line, the ordering tiebreak, the seller-index N+1, and the fact that
  concurrency is judged by compiling the lock rather than exercised);
  "Suggested next steps" re-pointed at what is actually left.
- Every route name and test class cited in the new rows was checked against
  `routes/` and the tree; two route names and one namespace were wrong in the
  first draft and were corrected (`seller.orders.decline`,
  `admin.orders.cancel`, `App\Actions\Escrow\IssueRefund`).

### The remaining six docs

- `docs/ontology.md` gained the seven entity sections it was missing: Admin,
  Listing removal, Customer block, Conversation, Message, Listing FAQ, and
  Page view count.
- `docs/data-model.md` gained `listing_removals` (`rmv`) and
  `page_view_counts` (`pvc`) in the prefix table and in the ER diagram, with
  the removal's relationship to its listing.
- `docs/orders.md` said the `Removed` reason waited on FEAT-024 and that every
  caller passed `hasActiveRemoval: false`. Both builders pass the listing's
  real answer now.
- `docs/architecture.md` said `rate_limit.exceed`,
  `moderation.remove_listing` and `moderation.lift_listing_removal` waited on
  features that have since shipped, and still described a seller-portal
  "Run weekly payout now" debug button that no longer exists.
- `docs/escrow.md` and `docs/admin.md` narrated the branch's history ("the
  debug button this prototype started with is gone"); both state the present
  now. `docs/escrow.md` also said `/admin/accounting` "lands with FEAT-023".
- `docs/identity.md` and `docs/messaging.md` needed nothing.
- The Pint file count was measured rather than guessed: **615 files clean**,
  598 analysed by PHPStan with 0 errors.

### Deliberately left out
- The **validation run was not performed**: `make fresh`, the GET-route walk,
  and the hook-refusal demonstration the ticket asks for were not run. The
  suite itself is green at 1827 tests / 4934 assertions / 100 % of lines, and
  the commit gate has refused work on this branch in practice more than once
  (a Composer process-timeout failure on `analyse` during IMPRV-005), but the
  ticket's explicit demonstration is not recorded.
- No code was refactored, per the ticket's scope discipline.
