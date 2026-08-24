---
id: MAINT-002
type: maintenance
status: open
created: 2026-08-23
---

# MAINT-002: Final validation and docs refresh for the alignment branch

## Problem
After MAINT-001, FEAT-018..020, BUG-007, and IMPRV-009..012 land, `docs/` (architecture, data-model, orders, escrow, admin, identity, messaging, review) describe the pre-alignment code, `README.md` lists old make targets and test counts, and nobody has run the whole thing from a clean tree with the hook installed.

## Goal
The branch ships with docs that match the code and a clean-tree run that proves the commit gate, the seeds, and every route.

## Outcome
`make check` passes from a clean tree; `make fresh` seeds; every GET route from `make routes` answers without a 5xx; `make docs-check` renders every diagram; every doc under `docs/` and the README state what the code does after alignment, with `docs/review.md` listing the known gaps that remain; the pre-commit hook is shown refusing a commit with a failing test (recorded in the ticket's Working notes).

## Why it matters
The three prototypes are compared by reading their docs and running their make targets; stale docs lose the comparison for the wrong reason.

## Discovery notes
FEAT-017 is the pattern: an independent audit agent reads `docs/` against `src/app/` and lists mismatches before anyone rewrites.

## Related work
- FEAT-017
- docs/alignment.md

## Working

### Fix-up

Alignment cross-check against the PHP prototype found `GET /cart` rendering a
listing an admin removed after it was already in the customer's cart: title,
image, and price intact, a working Remove link, and a View link to a page
that now 404s. Every other storefront surface already filters removals
(`isBrowsable`, `findListingOnStorefront`, `findFavoriteListings`); this was
the one gap. Fixed by mirroring the concept `checkout.ts` already has rather
than dropping the line silently:

- `order-placement.ts` (core) exports its already-private `unavailableReason`
  and a new `noticeForUnavailableReason`, so a caller other than
  `planOrderPlacement` can ask the same question about a single line.
- `cart-contents.ts` (shell) reads each line's active removal the same way
  `place-order.ts`'s `withRemovals` does, and stamps `isUnavailable` /
  `unavailableNotice` on the `CartLineView`. All four reasons apply (removed,
  off sale, sold out, short stock), not just removal — the same set checkout
  already refuses on, so the cart and checkout agree on what "unavailable"
  means rather than the cart inventing a narrower one.
- The cart total: unavailable lines are excluded from `cartTotals`, computed
  over `lines.filter(l => !l.isUnavailable)` rather than every line. A
  subtotal that included a line checkout will refuse to sell would be a
  number the customer could never actually pay.
- `cart.ejs`: an unavailable line keeps its Remove form (already worked,
  since `POST /cart/:slug/remove` resolves by slug through
  `findListingBySlug`, not the storefront-filtered query) but loses its
  `/art/:slug` link and shows the notice text in place of quantity and price.

Order pages and message threads were not touched — both are historical
records and already show removed listings on purpose.

Tests added: `cart-contents.test.ts` (removed / off-sale / normal lines,
and the total excluding an unavailable one) and `carts.test.ts` (`GET /cart`
renders the marked row, Remove still works, no dead `/art/:slug` link, and
the subtotal excludes the removed line's price).

`make check` green: 1915 tests (1906 baseline + 9), coverage 99.43/95.92/99.50
lines/branches/functions (baseline 99.42/95.94/99.49).

### Final validation and docs refresh

**Audit method.** Six independent read-only agents, one per doc (or doc pair),
each given the specific ticket-by-ticket context of what changed and told to
read the doc in full against `src/app/` and list mismatches before anything
was edited, per FEAT-017's pattern. `docs/orders.md` + `docs/escrow.md` and
`docs/identity.md` (besides one already-fixed line) came back clean — FEAT-019
and IMPRV-011's own worker passes had already kept them current. **29
mismatches found and fixed**, all in doc text, none in behavior:

- `docs/architecture.md` — 15: an `unemitted` claim on `rate_limit.exceed`
  that FEAT-020 made false (and the event missing from the events table); the
  order-status Mermaid diagram missing `refunded` and its four transitions;
  the fulfillment-status section reduced to the pre-FEAT-019 three-state line
  with no mention of `declined`/`refunded`; a stale "twenty-four tables /
  ten of eleven migrations" count (now twenty-six / twelve of thirteen); the
  commerce ER diagram and its "left off this overview" list missing `refunds`
  and `rate_limit_windows`; the `conversations` column list missing
  `subject_key`; the find-or-open paragraph still describing the pre-IMPRV-010
  five-column scan instead of `subjectKey`/the unique index; three
  repository-layout listings (`core/`, `actions/`, `plugins/`) and one
  (`views/partials/`) missing directories/files FEAT-020/IMPRV-009/
  IMPRV-011/IMPRV-012 added (`logging/`, `rate-limit/`, `security/`,
  `refunds/`, `csrf.ts`, `rate-limit.ts`, `csrf-field.ejs`, `field-error.ejs`,
  `form-error.ejs`, `form-field.ejs`); the `cli/` list already had
  `sweep-stale-orders.ts`; the request-lifecycle Mermaid diagram with no
  `preValidation`/CSRF/403 step at all; `ActionContext`'s description missing
  `log?`; no mention anywhere of `STALE_ORDER_HOURS`; and one line saying a
  customer merge "re-points" a conversation where IMPRV-010 made it fold.
- `docs/data-model.md` — 5: the same stale table/migration count; the ER
  diagram missing a `rate_limit_windows` entity; the no-foreign-key callout
  not naming it; `ledger_entries.entry_type`'s enum string missing `refunded`
  even though the file's own caveat two paragraphs later already talks about
  it.
- `docs/admin.md` — 1: the route table missing `POST /admin/sellers/:id/messages`
  and `POST /admin/customers/:id/messages`, both live routes.
- `docs/messaging.md` — 1: "a non-numeric id answers 404" left over from
  before FEAT-018 — ids are prefixed ULID text now, not integers.
- `docs/ontology.md` — 1: the Conversation entity's "Relates to" line still
  said "Re-pointed by a Customer merge" where IMPRV-010 made it fold instead
  (the `favorites` entity two rows above already had the correct phrasing to
  match against).
- `README.md` — 6 (multi-part): the test count and coverage figures (1,536 /
  99.57/97.22/99.47 → 1,915 / 99.43/95.92/99.50); "eleven migrations" →
  "thirteen"; the ejs-template count (66 → 71, from the four new form
  partials); two `docs/alignment.md` cross-references pointing at a path that
  does not exist from `prototype/node/` (fixed to `../../docs/alignment.md`,
  the actual repo-root location); the same four repository-layout lists
  (`core/`, `actions/`, `plugins/`, `views/partials/`) as architecture.md;
  and no section on CSRF at all despite the security-headers section right
  above it documenting the adjacent feature — added one.
- `docs/README.md`, `docs/orders.md`, `docs/escrow.md` — 0 mismatches found.

**A non-doc finding, left unfixed.** The `orders.md`/`escrow.md` auditor
flagged `sites/admin/views/orders.ejs` and `views/fulfillments.ejs` rendering
their customer/seller filter inputs as `type="number"` against what are now
`cus_`/`sel_` prefixed ULID text ids, and `views/ledger.ejs` still rendering
`` `#${entry.fulfillmentId}` `` / `` `#${entry.payoutId}` `` — an integer-id
`#`-sigil display over string ids. This is a FEAT-018 regression in `src/app`,
not a documentation mismatch, and neither doc makes a claim these views
contradict, so it is outside this ticket's docs-and-validation scope. Left
as found; worth its own follow-up ticket.

**`docs/review.md`'s known-gaps list.** Checked against every closed ticket's
own "recorded as gap N" note (FEAT-020's cuts as gaps 11/12, IMPRV-012's cut
as gap 13, the IMPRV-012 fix-up's gap 14) — all four are present, worded to
match, and numbered as their own tickets named them. Gaps 4 and 11 are
correctly struck through as closed. The one real problem was "Suggested next
steps": step 4 still pointed at gap 4 after IMPRV-009 had already closed it,
and gaps 12 and 14 had no step at all, so the list's numbers never lined up
with the gaps they closed. Rewritten to drop the stale step-4, renumber 1-12
against the twelve open gaps (skipping closed gaps 4 and 11), and add the two
that were missing.

**`make fresh`'s papercut, fixed.** The final `docker compose start app` line
exited 1 with `service "app" has no container to start` whenever the stack had
never been brought up — `docker compose stop app` on a nonexistent container
is a no-op (confirmed: exit 0), but `start` is not (confirmed: exit 1 with
that exact message). Guarded with `if [ -n "$(docker compose ps -aq app)" ]`,
which is non-empty both when a container the `stop` line just stopped exists
and when the stack was never up at all (empty, so the guard skips `start`
silently). Verified directly against this worktree's own compose project
(`align-node-node`, which had never been brought up in this session before
the fix): `make fresh` completed with exit 0, no error, both before the fix
reproduced the failure and after the fix ran clean. The "stack IS up, must
stop and restart a live server" branch could not be exercised end-to-end
through `make up`/`make fresh` together — port 4000 is held by the main
checkout's own stack for the whole session (rule 2), and a `docker-compose.override.yml`
remap of the published port did not take effect (Compose concatenates
`ports:` lists across an override rather than replacing them, so the original
`4000:4000` mapping still fired and still collided). Verified the guard logic
directly instead: `docker compose ps -aq app` returns a container id whenever
one exists in any state (including freshly `stop`ped), confirmed by bringing
the project's `app` service up, stopping it, and reading `ps -aq` non-empty
both times.

**Route walk.** `docker compose run --rm -p 4100:4000 -e PORT=4000
-e RATE_LIMIT_MAGIC_LINK_REQUEST=off -e RATE_LIMIT_MAGIC_LINK_CONSUME=off app
node app/server.ts`, backgrounded on `http://localhost:4100`. Rate limiting
disabled for this run only, after the first attempt (with defaults on)
correctly tripped `magic_link_request`'s ip-keyed counter — the limit is
shared by name across all three sites' sign-in POSTs (per `docs/alignment.md`
§3), and signing in as customer + seller + admin from one script, twice, hit
its 5/15m default. Left as evidence the limiter works; redone with it off so
the walk itself could finish. Signed in for real (magic link requested,
extracted from the flash-delivered debug alert, consumed) as a customer
(`casey@example.com`, seeded, has real orders/conversations), a seller
(`maya@example.com`, seeded), and an admin (`jonathan-beebe@outlook.com`,
seeded) — each POST carrying the CSRF token scraped from its own login page,
proving CSRF is live end to end, not just unit-tested. **74 GET routes
walked** (18 anonymous, 13 as the signed-in customer, 13 as seller, 30 as
admin) covering every GET `make routes` prints, with real prefixed-ULID ids
substituted for every `:id`/`:slug`/`:fulfillmentId` segment. **Zero
non-2xx/3xx responses except the three deliberate 404 probes** (`/nope`,
`/seller/nope`, `/admin/nope`, each answering that site's own 404 page as
intended). Cross-checked the server's own JSON log for the run: zero
`"level":"error"` lines and zero `"status":5xx` values anywhere. `/events`,
`/seller/events`, `/admin/events` (SSE, never close on their own) were capped
at a 3-second connection and counted by their initial response, not by
waiting them out.

**`make smoke`**: 8/8 green. **`make docs-check`**: 21 diagrams, 0 failed
(reran after every doc edit above; still 21/21 after all of them).

**Seeded ids sort in creation order.** Queried the freshly-seeded database
directly: for every table checked (`sellers`, `customers`, `listings`,
`orders`, `order_items`, `fulfillments`, `messages`, `conversations`), sorting
rows by id lexicographically produces the same order as sorting by the row's
own creation column (`created_at`/`placed_at`/`sent_at`) — the monotonic-ULID
property FEAT-018 built for exactly this, confirmed live rather than only
through `fixture-ids.ts`-driven unit tests.

**`make sweep`, `make payouts`, `make outbox`** — all ran clean against the
freshly-seeded database: sweep found nothing stale (`count: 0`), payouts found
no released balance to pay for the settled week (`count: 0, total_cents: 0`),
outbox drained 16 queued messages to `.eml` files under `storage/outbox/`.

**Commit-gate proof.** Staged a one-line change to `src/app/core/money.test.ts`
(`addCents(cents(1000), cents(500))` expected changed from `1500` to `999999`)
and ran `git commit`. Refused, exit 1, no commit created (`git log` unchanged
at `9823457`). Hook output, verbatim from the tail of the run:

```
✖ failing tests:

test at app/core/money.test.ts:18:1
✖ addCents sums two amounts (2.21025ms)
  AssertionError [ERR_ASSERTION]: Expected values to be strictly equal:

  1500 !== 999999

      at TestContext.<anonymous> (file:///var/www/src/app/core/money.test.ts:19:10)
      at Test.runInAsyncScope (node:async_hooks:227:14)
      at Test.run (node:internal/test_runner/test:1382:25)
      at Test.start (node:internal/test_runner/test:1242:17)
      at startSubtestAfterBootstrap (node:internal/test_runner/harness:387:17) {
    generatedMessage: true,
    code: 'ERR_ASSERTION',
    actual: 1500,
    expected: 999999,
    operator: 'strictEqual',
    diff: 'simple'
  }

make: *** [check] Error 1
```

Reverted (`git reset HEAD -- src/app/core/money.test.ts && git checkout --
src/app/core/money.test.ts`); tree confirmed clean, no breakage left.

**Left deliberately as found:** the code-level `#`/`type="number"` regression
noted above (out of scope); §6.1's "HTML/LCOV report" wording for `make
coverage` (LCOV only, no HTML — MAINT-001's own accepted gap, restated here
because README.md's Coverage section already says so accurately and needed no
change).

**Commits:** `docs[node]: refresh docs/ and README to match the alignment
branch [MAINT-002]` (docs/*.md + README.md, 7 files, no `make check` — both
are exempt from the commit gate); `fix[node]: tolerate a stack that was never
up in make fresh [MAINT-002]` (Makefile, ran the gate: 1915 tests green,
99.43/95.92/99.50).

`make check` at the end of this ticket: unchanged from the top of this
section — 1915 tests, 99.43/95.92/99.50 lines/branches/functions.
