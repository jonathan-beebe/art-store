---
id: MAINT-003
type: maintenance
status: resolved
created: 2026-08-23
---

# MAINT-003: Final validation and docs refresh for the alignment branch

## Problem
After MAINT-002, FEAT-015..021, BUG-004/005, and IMPRV-003 land, `docs/` (architecture, data-model, ontology, orders, escrow, identity, messaging, review) describe the pre-alignment code (test count, schema version, merge lists were already stale), `README.md` lists old make targets and test counts, and nobody has run the whole thing from a clean tree with the hook installed.

## Goal
The branch ships with docs that match the code and a clean-tree run that proves the commit gate, the seeds, and every route.

## Outcome
`make check` passes from a clean tree; `make fresh` seeds; every GET route from `make routes` answers without a 5xx; every doc under `docs/` and the README state what the code does after alignment, `docs/admin.md` exists, `docs/review.md` lists the known gaps that remain; the pre-commit hook is shown refusing a commit with a failing test (recorded in the ticket's Working notes).

## Why it matters
The three prototypes are compared by reading their docs and running their make targets; stale docs lose the comparison for the wrong reason.

## Discovery notes
FEAT-008/FEAT-014 are the pattern: an independent audit agent reads `docs/` against `src/app` and lists mismatches before anyone rewrites.

## Related work
- FEAT-008, FEAT-014
- docs/alignment.md

## Working

### A1 — the session-fixation test, verified empirically

Reviewer's suspicion confirmed for **both** tests, not just the named one.
Traced `Rack::Session::Abstract::Persisted#commit_session`
(`rack-session-2.1.2/lib/rack/session/abstract/id.rb`): when `options[:renew]`
is set, `commit_session` calls `delete_session` (CookieStore's override mints
a *second*, fresh sid) and writes that sid into the outgoing `Set-Cookie` —
but never back into the request's own `SessionHash#@id`. `request.session.id`
read from the *same* request a sign-in happened in therefore always reflects
the id that request *loaded* from its incoming cookie, never the one `renew`
actually committed.

Empirical steps (removed `request.session_options[:renew] = true` from each
of the three `sign_in_<actor>` methods, one combination at a time, reran
`test/controllers/concerns/shared_session_test.rb` only, restored after each):

- Removed all three `renew` lines: `8 runs, 24 assertions, 2 failures`. Both
  "each sign-in rotates the session id" and "the session id rotates on
  sign-in without losing what it already held" failed — but only at their
  **second** assertion each time.
- Restored seller's and admin's `renew`, removed only customer's: `8 runs,
  25 assertions, 0 failures` — **both tests still passed** with customer's
  own rotation disabled. This is the vacuousness: a preceding sign-in's own
  `renew` produces a *different*, discarded pre-commit id that happens to
  differ from the following request's true loaded id, regardless of whether
  the following sign-in rotates anything itself.
- Restored customer's, removed only seller's (with admin's back): failed at
  the **first** assertion of "each sign-in rotates the session id" — the
  `before_seller`/`after_seller` comparison, confirming that half is also
  trivially true regardless of `renew` (going from "no session" to "session
  first created" always changes the id).

So the companion test ("...without losing what it already held"), which the
ticket said was "believed sound," is **also vacuous** as originally written —
it happened to pass without customer's renew for the same reason.

**Fix**: `test/controllers/concerns/shared_session_test.rb` — a new private
helper `settled_session_id` issues a follow-up `get root_path` and reads
`request.session.id` from *that* request, which genuinely reflects the
previous response's committed cookie. `"each sign-in rotates the session id"`
now first signs in as admin (establishing a real prior session) before
checking seller's and customer's hops in turn. Reran the same
remove-one/restore experiment against the rewritten tests:
- Customer's `renew` removed only: **both new tests fail** (second assertion
  each) — genuinely discriminates now.
- Seller's `renew` removed only: **fails at the first assertion** of "each
  sign-in rotates the session id" — discriminates seller's hop too.
- All three restored: **8 runs, 25 assertions, 0 failures.**

Also corrected the "Rack's session-fixation defense" comment in
`customer_identity.rb`, `seller_authentication.rb`, `admin_authentication.rb`,
and `docs/identity.md`'s "Three actors, one browser" section: with
`ActionDispatch::Session::CookieStore` the real defense is that the whole
session travels in a signed-and-encrypted, content-bound cookie an attacker
never receives (no server-side session record for a stolen id to point at);
`renew` rotating the id is defence in depth on top of that, not the primary
mechanism. Recorded as known gap 14 in `docs/review.md`.

### A2 — unique index on `refunds.fulfillment_id`

New migration `db/migrate/20260824000106_add_unique_index_to_refunds_fulfillment_id.rb`
(`remove_index` the plain one, `add_index ... unique: true`), matching PHP's
landed index. `db/schema.rb` regenerated via `make migrate` (now at version
`2026_08_24_000106`). New test in `test/models/refund_test.rb`, "the database
refuses a second refund row for the same fulfillment" — calls `Refund.issue`
directly a second time on the same fulfillment (bypassing
`Fulfillment#decline!`/`#refund!`'s own transition guard) and asserts
`ActiveRecord::RecordNotUnique`. Existing decline/refund paths re-verified
green in the full suite.

### A3 — `docs/review.md` gaps

Removed the stale gap 3 ("a merge can leave a customer holding two carts") —
closed by IMPRV-003 — and renumbered the list to one coherent 1–14 sequence.
Added:
- **Gap 13** — block evasion on merge: `customer_blocks` is deliberately left
  behind by a customer merge (IMPRV-003, matching FEAT-021 and the Node/PHP
  prototypes). Confirmed reproducible by reading the code path directly:
  `Customer.from_cookie` follows `customer_merges` forward to the surviving
  verified customer, and `blocked?` is computed from `active_block` on that
  specific row — a block sitting on the abandoned anonymous row never
  reaches it. Framed as a shared, cross-prototype product decision, not a
  Rails bug.
- **Gap 14** — the session-fixation comment correction (A1 above).

Also fixed a stale `COVERAGE_MIN=80` reference in the "Development workflow"
table (real gate is `COVERAGE_MIN=100` on `test`/`check`; `coverage` runs the
same suite with no minimum). "Suggested next steps" renumbered to match.

### B — validation

**`make check` from a clean tree**: 1247 runs, 4388 assertions, 0 failures,
0 errors, 100% line coverage (2218/2218), `make lint` clean (291 files, no
offenses before removing the throwaway hook-demo test; 290 after).

**`make fresh`**: ran clean — drop, create, migrate, seed. Output: "Seeded 2
admins, 4 sellers, 29 listings, 1 customers, 3 orders, 4 conversations, 9
messages, 1 published FAQ."

**Route walk** — every GET route from `make routes`, exercised through
`ActionDispatch::Integration::Session` dispatched directly against
`Rails.application` (chosen over a live server after CSRF/host-authorization
friction over raw HTTP; this drives the exact same middleware stack and
routing without a network hop), against a freshly seeded development
database, with `session.host = "localhost"` and
`ActionController::Base.allow_forgery_protection = false` for the walk script
only (never committed). One browser session signed in as seller, then
customer, then admin in turn (exercising IMPRV-003's shared-session feature
live over real routes as a side effect), plus a fully anonymous pass and a
wrong-prefix-id pass.

**Result: every route answered without a 5xx.** ~60 checks total:
- Anonymous: public pages 200 (`/`, `/art/:slug`, `/favorites`, `/cart`,
  `/messages`, both login pages, `/admin/login`); protected pages 302
  (`/seller`, `/seller/listings`, `/admin`, `/admin/sellers`, `/account`);
  ownership-scoped pages 404 for a stranger (`/orders/:id`,
  `/orders/:id/pay`, `/messages/:id`).
- Seller signed in: all 12 seller routes (root, listings index/new/show/edit,
  listing FAQs index, orders index/show, earnings, notifications,
  conversations index/show) — 200.
- Customer signed in, same browser (seller still signed in throughout): all
  storefront + account routes — 200; seller's own root still 200 after the
  customer signed in.
- Admin signed in, same browser (seller and customer still signed in
  throughout): all 17 admin routes (root, sellers, customers, listings,
  orders, fulfillments, messages index/show each, accounting, ledger,
  payouts, stats) — 200; seller's root and the customer's account both still
  200 after the admin signed in.
- Wrong-prefix / unknown ids: unknown slug, an admin seller path carrying a
  customer id, a seller listing path carrying a fulfillment id — all 404, not
  500.
- Framework routes (`/up`, the three Turbo-native navigation routes) — 200.
  Active Storage's blob/representation routes need a real signed blob id and
  are framework-owned infrastructure with no product meaning without one —
  out of scope for this walk, consistent with `docs/alignment.md` §1's
  "framework-owned tables keep the framework's keys."

No 5xx anywhere; no code changes were needed for the walk.

**Docs audit** — three independent read-only audits (one per doc group,
matching FEAT-008/FEAT-014's pattern) plus my own direct reads, against the
live code (`app/`, `db/schema.rb`, `config/routes.rb`, `Gemfile.lock`).
Mismatch list, by file, gathered *before* any edit:

- **README.md**: test count stale (748 → 1247 runs); "no order cancellation
  route" (FEAT-017 added one); "Run weekly payout now" pays every seller
  (removed by FEAT-021, moved to admin); `make sweep`'s description
  ("nothing to run yet") stale (FEAT-017 wired the real sweep); `STALE_ORDER_HOURS`
  env var undocumented; `docs/` file-tree line omits `admin.md`.
- **docs/architecture.md**: `ledger_entries.entry_type` list missing
  `refunded`; three writers named instead of four (`.refund` missing); ER
  diagram missing `refunds`, `listing_removals`, `customer_blocks`; moderation
  events wrongly listed as "Deferred" (FEAT-021 shipped them); seller-portal
  "debug payout button" line stale; `app/models/` method roster missing
  `Order#cancel!`, `Order.sweep_stale`, `Fulfillment#decline!`/`#refund!`,
  `Refund.issue`, `Listing#remove!`/`#lift_removal!`, `Customer#block!`/`#lift_block!`,
  and the `Refund`/`ListingRemoval`/`CustomerBlock`/`OrderPlacement`/`CustomerMergePlan`
  classes; helpers bullet missing `customer_standing`.
- **docs/data-model.md**: schema version stale (`…000105` → `…000106`);
  intro's framework-table list missing `solid_cache_entries`; `carts.customer_id`
  caveat attributed the "not unique" fact to a merge leaving two carts
  (IMPRV-003 fixed that; the column is still non-unique for other reasons);
  no caveat on `refunds.fulfillment_id`'s new unique index vs. the
  one-to-many ER line.
- **docs/ontology.md** (largest gap — predates FEAT-017/019/020/021/IMPRV-003
  entirely): no `Refund`, `Listing removal`, or `Customer block` entity
  sections at all; `Fulfillment` lifecycle/relates-to missing
  `declined`/`refunded`; `Ledger entry` lifecycle missing `refunded`;
  `Customer merge` relates-to described the pre-IMPRV-003 shape (five
  re-pointed associations, no fold, no left-behind); `Order`/`Notification`
  method rosters incomplete (`cancel!`, `sweep_stale`,
  `fulfillment_declined`/`_refunded`, `order_cancelled` missing); top
  flowchart had no refund/moderation nodes; `Cart` lifecycle attributed
  "not unique" the same way data-model.md did.
- **docs/orders.md**: one stale paragraph — "`removed` is implemented but
  unreachable... no admin removal exists" (FEAT-021 landed the removal
  path).
- **docs/messaging.md**: "this prototype has no moderation feature" (FEAT-021
  added the block-on-post guard).
- **docs/review.md**: gap 3 (two carts) stale/closed; `COVERAGE_MIN=80`
  stale; block-evasion and session-fixation gaps missing (A3).
- **docs/escrow.md**, **docs/identity.md**, **docs/admin.md**: no mismatches
  found — already current through FEAT-017/018/019/020/021/IMPRV-003's own
  doc updates. (identity.md's session-fixation wording was corrected as part
  of A1, not flagged as a pre-existing mismatch.)

All of the above fixed. `docs/admin.md` already existed (written in
FEAT-019/020/021, confirmed accurate) — nothing to create.

**Hook demonstration**: staged a throwaway `test/models/hook_demo_test.rb`
(`assert_equal 1, 2`) alongside every other staged change, ran
`git commit -m "test[rails]: hook demonstration commit that must be refused [MAINT-003]"`
from the repo root. Output:

```
pre-commit: make -C prototype/rails check
docker compose run --rm app bin/rubocop
... 291 files inspected, no offenses detected
docker compose run --rm app bin/rails tailwindcss:build
... (assets built)
docker compose run --rm ... bin/rails db:test:prepare && ... bin/rails test
...
Failure:
HookDemoTest#test_a_deliberately_failing_assertion_for_MAINT-003's_hook_demonstration [test/models/hook_demo_test.rb:5]:
Expected: 1
  Actual: 2
...
1248 runs, 4389 assertions, 1 failures, 0 errors, 0 skips
make: *** [test] Error 1
```

`git commit` exited nonzero; `HEAD` stayed at `2e0a61e` (no new commit
created); every staged change remained staged. Removed
`hook_demo_test.rb` (`git restore --staged` + `rm`) and reran `make check`
clean before the real commit.

### C — cross-prototype reconciliation notes

- **Unknown admin filter value**: Rails answers **400** (`ActionController::BadRequest`,
  raised by `Admin::BaseController#filter_from`/`#id_filter`, unrescued
  anywhere in the tree, falls through to Rails' static `public/400.html` —
  no site in this app renders its own 400 or 404). Routes: `/admin/listings?status=&seller=&removed=`,
  `/admin/customers?standing=`, `/admin/orders?status=&customer=`,
  `/admin/fulfillments?status=&seller=`. PHP's `CustomerController` reads
  `$request->enum('standing', StandingFilter::class) ?? StandingFilter::All`
  — Laravel's `enum()` accessor returns `null` for an unmatched value, so an
  unrecognized filter silently falls back to "All" rather than refusing the
  request. **Divergence confirmed**: Rails refuses the query string outright;
  PHP treats it as absent.
- **§4.2 ledger fold groups by fulfillment**: confirmed —
  `LedgerEntry.balance` groups by `(fulfillment_id, entry_type)`, not by
  entry type alone across a seller's whole history.
- **A payout ≤ 0 writes no row and carries the negative forward**: confirmed
  — `Balance#payable?` is `available > 0`; `Payout.run_weekly` skips any
  seller failing that check, and the next run's `occurred_by` read still
  includes the unsettled `refunded` entry.
- **The refund-after-release timing keys on "a `released` entry exists"**,
  not on fulfillment status: confirmed — `LedgerEntry::Balance`'s fold reads
  `still_held = released.zero?` directly off that fulfillment's entries, with
  no reference to `fulfillments.status`.
- **A removed listing leaves every storefront surface including favorites**:
  confirmed — `Shop::FavoritesController#index` chains `.on_storefront`
  ahead of the favorites filter (fixed in FEAT-021's own fix-up, which found
  favorites was the one surface missing the check `Listing.on_storefront`
  already gave browse, search, and the listing's own page).
- **Every rate-limited POST that came from a form re-renders that form**:
  confirmed, with one clarification — a route re-renders its form on a trip
  only where that route already had a template-based refusal convention to
  extend (`Auth::*SessionsController`, `Shop::CheckoutsController`, `Shop::OrderPaymentsController`,
  `Seller::ListingsController`, `MessagingSite`). `conversation_open`'s five
  guarded routes and `magic_link_consume` fall through to a shared plain
  page instead — consistent, not a divergence, because those routes'
  ordinary (non-rate-limited) refusal is already a redirect+flash rather
  than a re-rendered form; there is no form convention there to extend.
- **Every fulfillment/order transition is judged on a locked row inside the
  writing transaction**: confirmed — `Order#cancel!`, `Fulfillment#ship!`,
  `#deliver!`, `#decline!`, `#refund!` all `lock!` then validate then
  `update!` inside one `transaction do`, after FEAT-017's fix-up moved
  `ship!`/`deliver!`'s guard inside the transaction to match
  `decline!`/`refund!`'s existing shape.
- **The favorites merge is move-or-drop**: confirmed — `CustomerMergePlan.partition_favorites`
  destroys a colliding anonymous favorite and `update_all`s the rest onto the
  verified customer; no `Favorite.create!` runs during a merge (pinned by a
  `sql.active_record` subscription test in `CustomerMergeTest`).
- **Incoming `X-Request-Id` is anchored `\A…\z`**: confirmed —
  `RequestStory::TRUSTED_ID = /\A[A-Za-z0-9_-]{1,64}\z/`.

### Scope note

Non-negotiable core delivered in full: A1–A3, `make check` green from a
clean tree, the route walk with no 5xx, `docs/review.md` coherent, docs no
longer lying about test counts or make targets. Nothing was cut.

### Node-lane alignment

Six gaps the Node lane's own alignment pass surfaced, verified against
this codebase and fixed on `align/rails`. Final state: 1262 runs, 4506
assertions, 0 failures, 100% line coverage (2264/2264), `make lint` clean.

1. **No `#` sigil before a prefixed id (FEAT-015)** — `docs/alignment.md`
   §1 makes the order number the order id. Swept every view, model
   display method (`Customer#display_name`, `Conversation#topic`), and
   `Notification` body builder for a literal `#` immediately before a
   prefixed id and dropped it. Commit `c3b2b7d`.

2. **Admin cancel takes a reason (FEAT-017)** — `Order#cancel!` gained a
   `reason:` keyword, required and validated (present, 1–500 chars,
   against `Refund::REASON_LIMIT`) only when `by:` is an `Admin`; a
   customer's own cancel and the stale sweep still pass none. **Decision:
   the reason lives in a new `orders.cancellation_reason` column**, not a
   join table like `ListingRemoval`/`CustomerBlock`/`Refund` — a
   cancellation happens at most once per order and is a dead end in the
   state machine (`cancelled` has no outgoing transitions), so there is
   no multiplicity a separate table would earn its keep on; a nullable
   column is the smaller vanilla-Rails answer. `Admin::CancellationsController`
   now rescues `ActiveRecord::RecordInvalid` alongside `TransitionError`,
   matching `Admin::ListingRemovalsController`'s existing shape (the
   sibling check this ticket asked for: `Admin::RefundsController`
   already rescued only `RecordInvalid` — it has no `TransitionError`
   path of its own to catch, since `Fulfillment#reverse_to!` judges the
   transition through the same `validate!` that carries the reason
   check, not a raised `TransitionError`). Commit `7f82ab2`.

3. **FAQ uniqueness on `(listing_id, source_message_id)` (MAINT-003)** —
   added the unique index (SQLite does not treat NULL as equal to NULL in
   a unique index, so hand-written entries with no source message stay
   unrestricted) plus a model validation, so a second publish is a
   readable domain refusal. The thread now shows a published marker
   (`Message#listing_faq`) and drops the publish form for a message
   already published. Found and fixed a real bug while wiring the
   "already published" check: reading it as `draft.source_message.listing_faq`
   always found the draft itself, because `belongs_to :source_message,
   inverse_of: :listing_faq` on the still-unsaved draft wires the
   association's other end as a side effect of assignment — switched to a
   direct `ListingFaq.exists?` query. Folded in the CSRF status-code
   divergence note (item 6) since both touch `docs/review.md`. Commit
   `bb3b7e5`.

4. **Blocked cart line excluded from the subtotal (BUG-004)** —
   `Cart#subtotal`/`#subtotals_by_seller` now run over `available_items`,
   which reuses `OrderPlacement`'s own blocked-line classification. The
   cart page states what the total covers once a line is blocked.
   Regression found and fixed while implementing this: `Order.place` had
   been deriving its fulfillment split from `cart.subtotals_by_seller`
   *after* `snapshot` already took the cart's stock — with blocked-line
   filtering added to that method, a listing that sold out **because of
   this very order** read back as blocked on the fresh query the fix
   introduced, so the order lost its fulfillments. Moved the split onto
   `plan.items` instead (already locked, already confirmed unblocked, no
   fresh query racing the stock change) — this fix landed inside commit
   `7f82ab2` (FEAT-017) because `order.rb` was already staged there
   before the regression was found; it is `Order.place`-only and has no
   bearing on the admin cancel reason. Commit `20a2c64` (cart/view/tests).

5. **Two rate-limited forms fell to the plain 429 page (FEAT-018)** —
   `Shop::ListingQuestionsController`, `Shop::SupportsController`, and
   `Shop::FulfillmentConversationsController` all fell through, not just
   two: the "consistent, not a divergence" reasoning recorded in this
   ticket's own §C reconciliation notes above (all three routes just
   redirect+flash on an ordinary refusal, so there is no form convention
   to extend) turned out to be the shape of the bug, not a defense of it.
   `ListingQuestionsController` re-renders the listing page the question
   form sits on. `SupportsController` and `FulfillmentConversationsController`
   have no field to preserve — both are plain buttons with no body param
   — so each re-renders the page the button sits on (account page, order
   page) rather than forcing a form that does not exist. `magic_link_consume`
   is unchanged: its own guarded route (`GET /auth/magic/:token`) is a
   link click, not a form submit, so it keeps the plain page. Commit
   `65dc32f`.

6. **CSRF divergence recorded** — verified empirically
   (`ActionDispatch::ExceptionWrapper.rescue_responses["ActionController::InvalidAuthenticityToken"]`
   `=> :unprocessable_content`, i.e. 422): Rails answers 422, Node 403,
   Laravel 419. Folded into commit `bb3b7e5` (item 3) as a
   `docs/review.md` addition.
