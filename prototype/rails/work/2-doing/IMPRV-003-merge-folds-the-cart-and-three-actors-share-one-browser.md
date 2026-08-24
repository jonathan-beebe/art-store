---
id: IMPRV-003
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-003: Merge folds the cart and de-duplicates favorites, and three actors share one browser

## Problem
`Customer#merge_from` (or the equivalent) re-points `carts.customer_id`, so a verified customer who shopped anonymously ends up with two carts; favorites rely on the unique index during `update_all`. Every sign-in calls `reset_session`, so signing in on the seller site drops the customer and admin sessions — a reviewer cannot demo seller + customer + admin side by side in one browser, which Node and PHP allow. The Node merge (`planCustomerMerge`) folds cart quantities (sum, clamp to stock, drop zero lines) and de-dupes favorites as a pure plan.

## Goal
Merging leaves exactly one cart and one favorites set with nothing lost, and one browser can hold all three actors at once.

## Outcome
After a merge the owner has one cart whose lines are the sum of both clamped to stock, favorites are the union, conversations are folded (already), sent messages are re-pointed (already), blocks are re-pointed, and a test asserts every `customer_id` column is either in `MERGED_ASSOCIATIONS` or in an explicit left-behind list; signing in as any actor keeps the other two signed in (one session key per actor, rotated on that actor's sign-in only) and the smoke walk signs in all three in one session; `docs/identity.md` states both.

## Why it matters
Retro item 4 asked for the merge as a fold; the three-actors demo is how the prototypes get compared side by side.

## Discovery notes
A `CustomerMerge` PORO (or a class method) over both customers' cart lines and favorites, applied inside the existing transaction; per-actor session keys with `session.delete(:customer_id)`-style sign-out instead of `reset_session`, keeping CSRF and session-fixation protection by rotating the session id via `request.session_options[:renew] = true` on sign-in.

## Related work
- FEAT-002 (identity), BUG-001 (merge threads)
- prototype/node RFCTR-004 (planCustomerMerge)

## Working

### What landed

**Part 1 — the merge is a fold.** `app/models/customer_merge_plan.rb` is a
new PORO, `CustomerMergePlan`, with two class methods and no database access:
`fold_cart_lines(verified, anonymous, stock)` sums a `Hash(listing_id =>
quantity)` from each side, clamps each summed line to the stock cap (a
listing absent from the stock hash gets no cap), and drops a line that
clamps to zero; `partition_favorites(verified_ids, anonymous_ids)` returns
`{move:, drop:}` — anonymous favorites the verified customer does not
already hold move, ones that duplicate a favorite the verified customer
already holds drop. Both are covered directly in
`test/models/customer_merge_plan_test.rb` with no AR records at all.

`Customer#fold` (private, called from `#absorb` inside the existing
transaction) now:
1. `fold_cart(anonymous)` — reads both customers' cart items (`current_cart`
   for the verified side, `anonymous.carts` for the other — carts is a
   `has_many`, so this is defensive against more than one row on either
   side, not just the common single-cart case), calls
   `CustomerMergePlan.fold_cart_lines`, writes the result onto the verified
   customer's cart with `find_or_initialize_by` + `update!` for a kept line
   and `destroy_all` for a dropped one, then destroys every one of the
   anonymous customer's cart rows outright (not re-pointed — a plain
   `carts.create!` for the verified customer if they had none is cheaper to
   reason about than branching on whose row survives).
2. `fold_favorites(anonymous)` — calls `CustomerMergePlan.partition_favorites`,
   destroys the anonymous customer's favorites that would collide, then
   `update_all`s the rest to the verified customer's id. The duplicate is
   gone before the `UPDATE` runs, so nothing ever leans on the unique index
   on `(customer_id, listing_id)` to reject a row.
3. Re-points `Customer::REPOINTED_ASSOCIATIONS` (`orders`, `listing_events`,
   `notifications`, `sent_messages`) with the existing `update_all` loop,
   unchanged.
4. Folds conversations via `Conversation#move_to` (unchanged) and inserts the
   `customer_merges` row (unchanged).

`Customer::MERGED_ASSOCIATIONS` gained `:conversations` (it was always
folded, just not named in the list) and now documents which of its members
fold versus re-point. `Customer::REPOINTED_ASSOCIATIONS` is the new,
smaller constant the `update_all` loop actually iterates —
`MERGED_ASSOCIATIONS - %i[carts favorites conversations]`.
`Customer::LEFT_BEHIND_ASSOCIATIONS` is new: a `{table_name => reason}` hash
for the tables a `customer_id` column that a merge does not touch. See the
blocks decision below.

**The exhaustiveness test**: `test/models/customer_merged_associations_test.rb`
(`CustomerMergedAssociationsTest`). It reads `ActiveRecord::Base.connection.tables`
and `.columns` directly — no hand-copied table list — collects every table
with a column literally named `customer_id`, and asserts that set is covered
by `MERGED_ASSOCIATIONS` (mapped to table names through
`Customer.reflect_on_association(...).table_name`, since an association's
Ruby name need not match its table — `Customer#blocks` reads
`customer_blocks`) union `LEFT_BEHIND_ASSOCIATIONS.keys`. Two more tests
guard the guard: `LEFT_BEHIND_ASSOCIATIONS` names no table that has since
lost its `customer_id` column (a stale entry hiding a real gap), and no
table is claimed by both lists. Add a `customer_id` column to a new table
without deciding which list it belongs in, and this suite fails until
someone does.

`current_cart`'s comment used to explain the max-item tiebreak by naming the
merge bug this ticket fixes ("a merge hands the verified customer whatever
cart the anonymous visitor was filling, so one customer can own two"). Since
a merge no longer produces two carts, the comment was rewritten to describe
only what the method does now; the tiebreak logic itself is untouched — a
customer can still end up with more than one cart by a path outside merge
(a pre-existing test builds that scenario directly with `Cart.create!`
twice), so it stays defensive.

### The blocks decision

**`customer_blocks` is not re-pointed** — `LEFT_BEHIND_ASSOCIATIONS`, not
`MERGED_ASSOCIATIONS` — overriding this ticket's own Outcome text ("blocks
are re-pointed"), which conflicts with FEAT-021's decision (already merged,
`customer_blocks` deliberately excluded from `MERGED_ASSOCIATIONS`) and with
the Node reference (`REPOINTED_CUSTOMER_TABLES` excludes `customer_blocks`
too, and Node's `currentCustomerStanding` reads a block by the *current*
resolved customer id — the survivor after `customer_merges` is followed —
never the anonymous id, so a block on the anonymous side already does not
reach the verified account in the reference implementation, re-pointed or
not).

Weighed:
- **For re-pointing:** a blocked anonymous customer who verifies into a
  different (unblocked) account starts shopping again — the block is
  evaded, and the abandoned anonymous row is left holding a punishment that
  no longer restrains anyone real.
- **Against:** an admin blocked a specific row, and the reference leaves it
  behind. Re-pointing raises a question moderation has no policy for today:
  `CustomerBlock` carries a partial unique index enforcing at most one
  *active* block per customer (`FEAT-021`, `WHERE lifted_at IS NULL`); if
  the verified customer is *also* currently blocked, re-pointing the
  anonymous customer's active block would violate that index, and there is
  no product decision about which block should win, or whether both should
  stand.

Decision: match FEAT-021 and the reference — leave blocks behind. This is a
prototype-scope call, not a claim that the evasion is acceptable in a real
system; it is recorded here so the next person changing this has the
tradeoff in front of them rather than re-deriving it. `CustomerMergeTest`
pins it (`"a block on the anonymous row is not re-pointed by a merge"`):
the block stays on the anonymous row, `verified.blocked?` is false after the
merge.

Because the decision is "leave behind" rather than "re-point", the
at-most-one-active-block invariant across a merge where both customers are
blocked never comes up — nothing about a merge ever writes to
`customer_blocks`, so the partial unique index is never in the fold's path.

**Part 2 — three actors share one browser.** `CustomerIdentity#sign_in_customer`,
`SellerAuthentication#sign_in_seller`, and `AdminAuthentication#sign_in_admin`
each replaced `reset_session` with `request.session_options[:renew] = true`
before writing their own session key
(`session[:customer_id]` / `session[:seller_id]` / `session[:admin_id]`).
`renew` is Rack's session-fixation defense: the session id the cookie
carries changes on the response, without discarding the hash — the other
two actors' keys survive. The matching `sign_out_<actor>` methods replaced
`reset_session` with `session.delete(:<actor>_id)`, so signing out of one
site no longer drops the other two.

`session[:customer_id]` (the verified sign-in flag `CustomerIdentity`
tracks) and the `customer_id` *cookie* (`CustomerIdentity::COOKIE`, the
year-long anonymous-browsing-history identity) share a name but are
unrelated storages; `sign_out_customer` still deletes the cookie too — that
behavior (a clean anonymous identity on the next storefront request) was
correct before and is untouched.

Spiked `request.session.id` against the actual session store
(`ActionDispatch::Integration::Session#request.session.id`, CookieStore,
`_art_store_session`) to confirm `renew` actually changes the id and that
the other two actors' keys survive the rotation, before writing it into a
test — see `test/controllers/concerns/shared_session_test.rb`
(`SharedSessionTest`), 8 new tests: all three signed in together and each
site's home page reachable; signing out of each one individually leaves the
other two; the session id rotates on each sign-in; a sign-in's rotation
specifically does not lose what the session already held; the `sid` cookie
(`RequestStory`, unrelated to the Rails session store — it is a plain cookie
written straight onto the response) is unchanged by all three signing in,
and by all three signing out.

CSRF needed no separate handling: Rails' synchronizer token is a value
carried in the session hash, not derived from the session id, so `renew`
does not touch it.

`test/smoke_test.rb` gained `sign_in_all_three_actors_in_one_browser`,
called at the end of the existing walk in a fresh `open_session` browser
(the existing walk's two browsers are for the anonymous-to-verified merge
scenario and stay as they are) — a seller, a customer, and an admin sign in
one after another and all three home pages answer `:success` afterward.

### Log payload

`customer.merge`'s `Story.tell` call was already correct (`customer_id`,
`anonymous_customer_id` in `data`, on both `will` and `did`) — nothing
changed there. `CustomerMergeTest#"the customer.merge log line names both
customers"` pins it with `captured_log_lines`, the first time that event had
a direct test rather than only appearing incidentally in `logging_test.rb`'s
end-to-end checkout traces.

### Docs

`docs/identity.md` gained two sections — "The merge is a fold, not a
re-point" (right after the existing merge sequence diagram, since the fold
is what that diagram's `absorb` step now does) and "Three actors, one
browser" (after the storefront identity-resolution section) — plus the
blocks decision, stated the same way as here. Both are Mermaid: a flowchart
for the fold's steps, a sequence diagram for the three sign-ins and what
survives each `renew`.

### Deviations from the ticket

- **Blocks are left behind, not re-pointed** — the Outcome text's own words
  say "re-pointed"; see the decision above.
- Everything else matches the Outcome text: one cart after a merge (lines
  summed, clamped to stock), favorites as a union, conversations folded
  (already, untouched), sent messages re-pointed (already, untouched), the
  exhaustiveness test, per-actor session keys rotated only on that actor's
  sign-in, the smoke walk signing in all three in one session, `docs/identity.md`
  stating both halves.

### Numbers

Before: 1211 runs, 4287 assertions, 0 failures, 100% line coverage
(2183/2183). After: 1246 runs, 4387 assertions, 0 failures, 0 errors, 100%
line coverage (2218/2218). `make lint` clean.

### Fix-up (review from another session)

A parallel review of the working tree flagged the favorites fold: it already
moved-or-dropped rather than inserting (`update_all` for the move,
`destroy_all` for the drop — no `create!` anywhere in `fold_favorites`), so
no code changed, but the tests did not yet pin the "never inserts" shape
directly. Added to `CustomerMergeTest`: the surviving favorite rows after a
merge are asserted to carry the exact same primary keys they had before (an
insert would mint fresh ones), the total `Favorite` row count only drops (by
the duplicates), and a dedicated test subscribes to `sql.active_record` and
asserts no `"Favorite Create"` query happens during a merge. The same
review's other flag — do not stage `prototype/rails/Makefile` or
`prototype/rails/docker-compose.yml` (a concurrent, unrelated host-port
change) — is why this commit is by explicit pathspec.

### Open questions

- The at-most-one-active-block-per-customer invariant under a merge where
  both customers are blocked was never exercised, because blocks are left
  behind — if a future ticket re-opens the re-pointing question, that
  invariant (and which block should survive) needs an actual product
  decision, not just a schema check.
