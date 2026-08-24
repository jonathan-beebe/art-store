---
id: FEAT-021
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-021: Admin moderation of listings and customers, and the payout run moves to admin

## Problem
Admins can neither remove a listing from sale (temporarily for review or permanently) nor block a customer; there is no `customer_blocks` table; the weekly payout is run by a seller-portal debug button that settles every seller from inside one seller's portal, with no admin payout page. `docs/alignment.md` §5 fixes all three: `listing_removals` with `temporary | permanent` kinds and a lift, `customer_blocks` with a lift, and payouts as a platform action from `/admin/payouts`.

## Goal
Platform actions — moderating listings and customers and paying sellers — live on the admin site and nowhere else.

## Outcome
`POST /admin/listings/:id/removals` (kind, reason) takes a listing off the storefront whatever its status (browse, search, and `/art/:slug` all stop showing it), the seller reads the reason on their own listing page and cannot put it back on sale, `…/removals/lift` works for temporary and is refused for permanent, at most one active removal per listing; `POST /admin/customers/:id/blocks` (reason) removes cart add, checkout, pay, and message post while browsing, favorites, and reading threads stay open, `…/blocks/lift` restores them, at most one active block per customer; `/admin/payouts?seller=` lists payouts and `POST /admin/payouts` (optional `as_of`) runs the same weekly payout the rake task runs, idempotent per period; the seller-portal payout button is gone and the seller's earnings page keeps balances and history; tests cover each refusal; `docs/admin.md` and `docs/escrow.md` updated.

## Why it matters
Retro item 6: payouts, refunds, and seller suspension are platform actions; Rails is the only prototype where an admin cannot block a customer.

## Discovery notes
Node's `docs/admin.md` "What a removal or a block actually does" diagram is the spec, including `isOnStorefront(status, hasActiveRemoval)` and the listing transitions dropping `for_sale` while a removal stands, and `canShop` as the predicate a block turns off. PHP's `BlockCustomer`/`LiftCustomerBlock` and `ConversationPolicy` (`post` = view + `canShop`) are the closest Active-Record-style shape.

## Related work
- docs/alignment.md §5
- FEAT-009 (admin actor)
- prototype/php FEAT-010

## Working

### What landed

Two tables, `listing_removals` (`rmv`) and `customer_blocks` (`blk`), each
`id: :string` via `PrefixedId`, a required `admin_id` (`belongs_to :admin`),
a `reason` (1–500 chars), a nullable `lifted_at`, and a **partial unique
index** (`WHERE lifted_at IS NULL`) on the subject column — that index is
what "at most one active removal / block" means at the schema level, not an
application-level check alone. `ListingRemoval` adds `kind` (`temporary` |
`permanent`, Rails `enum`) and `liftable?` (`temporary?`).

Rich-model actions, matching `Order#cancel!` / `Fulfillment#decline!`'s
existing shape — no service layer:

- `Listing#remove!(kind:, reason:, by:)`, `#lift_removal!`, `#active_removal`,
  `#actively_removed?` (already correct per FEAT-019's note — only the data
  source moved off the stub).
- `Customer#block!(reason:, by:)`, `#lift_block!`, `#active_block`,
  `#blocked?`, `#blocked_reason`, and `#can_shop?` — the one predicate a
  block turns off, matching Node's `canShop` and PHP's `canShop()`.

Both actions wrap `Story.tell` with the exact event names from alignment §2.3
(`moderation.remove_listing`, `moderation.lift_listing_removal`,
`moderation.block_customer`, `moderation.lift_customer_block`) and raise
`TransitionError` for the four refusals — the tree's existing domain error
for a transition-shaped rule (`Listing.transition`, `Order.transition`), not
the `ActiveRecord::RecordInvalid` shape `Fulfillment` uses for its richer
validations. `TransitionError` is already in `Story::REFUSALS`, so `refused`
at `info` needed no extra wiring.

Storefront visibility — the three stand-ins FEAT-019 named:

- `Listing.removed` / `Listing.visible` now read
  `where(id: ListingRemoval.active.select(:listing_id))` and its negation,
  replacing `none` / `-> { all }`.
- `Listing.on_storefront` (the scope every cart-add / favorite / listing-
  question / storefront-show lookup goes through) is
  `where(status: ON_STOREFRONT).visible`, so a removal takes a listing off
  everywhere in one change.
- `Listing.search` and `Listing.media_for_sale` gained `.visible` on their
  `for_sale` base, so browse, search, and the medium filter all drop a
  removed listing too.
- `Listing#purchasable?` gained `&& !actively_removed?`, matching Node's
  `isPurchasable`; it was missing the check entirely before (a gap the ticket
  didn't name but the reference spec did).
- `Listing#next_statuses` drops `for_sale` while `actively_removed?`, feeding
  `seller/listings/_status_buttons`; `#transition_to!` raises `TransitionError`
  for the same case, so a direct POST is refused too, not just hidden.
  `seller/listings/show.html.erb` gained a `data-field="removal"` notice with
  the reason.

The block predicate, wired at its four sites:

- Cart add, checkout, pay: one guard,
  `Shop::BaseController#refuse_blocked_customer(to:)`, called as a
  `before_action -> { ... }` on each controller's `create` (`CartItemsController`,
  `CheckoutsController`, `OrderPaymentsController`), each naming its own
  redirect target — mirrors Node's `refuseBlockedCustomer(destination)`
  factory reused at the same three routes.
- Message post: one seam, `Conversation#post!`, refuses when
  `sender.is_a?(Customer) && !sender.can_shop?` — covers a reply
  (`MessagingSite#create`, shared by all three sites) and a listing question's
  opening message (`ListingQuestionsController#ask`, which also calls `post!`)
  with no second check. Both controllers gained a `rescue TransitionError`
  beside their existing `rescue ActiveRecord::RecordInvalid`.
- Browsing, favorites, and reading threads: untouched, no guard added.

Admin site: `Admin::ListingRemovalsController` (`create`, `lift`),
`Admin::CustomerBlocksController` (`create`, `lift`), nested under
`resources :listings` / `:customers` as `resources :removals` / `:blocks`
with `post :lift, on: :collection` — `POST /admin/listings/:id/removals`,
`…/removals/lift`, `POST /admin/customers/:id/blocks`, `…/blocks/lift`.
`Admin::ListingsController#show` / `Admin::CustomersController#show` gained a
removal/block history table and a remove-or-lift / block-or-lift form.
`Admin::PayoutsController` (`index`, `create`) at `GET|POST /admin/payouts`
(`?seller=`, optional `as_of`), reusing `Payout.run_weekly` and
`Payout.for_seller` (new scope, matching `Order.for_customer` /
`Fulfillment.for_seller`) — the same class method the rake task calls, so
`payout.run` / `payout.pay` needed no new logging.

Seller portal: `Seller::PayoutsController` and its route
(`POST /seller/earnings/payout`) deleted; `seller/earnings/show.html.erb`
lost the "Run weekly payout now" button and its debug-control caption; the
balances and payout-history sections are untouched.

### Fix-up: two N+1s the count_queries guard caught

`Admin::CustomersControllerTest` and `Admin::ListingsControllerTest`'s
"costs the same however many rows" tests caught two N+1s the first pass
introduced:

1. `admin/customers/_row.html.erb`'s `customer_standing(row)` called
   `row.blocked?`, delegated straight to `Customer#blocked?` — one query per
   row. Fixed by folding block status into `Customer.directory` the same way
   order/favorite/cart-line counts already are: one
   `CustomerBlock.active.distinct.pluck(:customer_id).to_set` query, and
   `Customer::Row` gained a `blocked` field (`alias_method :blocked?,
   :blocked`) instead of delegating to the customer.
2. `admin/listings/_listing.html.erb`'s `listing.on_storefront?` called
   `Listing#actively_removed?` → `active_removal` → `removals.active.first` —
   a `where` scope call, which queries even when `removals` is preloaded.
   Fixed by changing `active_removal` to `removals.detect(&:active?)` (a
   caller with the association loaded pays nothing extra) and adding
   `.includes(:removals)` to `Admin::ListingsController#index`.

Both are the same shape as FEAT-019's own fix-up (the `.includes` a directory
row needs is invisible in the code that reads a single field, and only a
`count_queries` test with distinct parents per row catches it).

### Decisions

1. **All four refusals are `TransitionError`**, not `ActiveRecord::RecordInvalid`.
   The ticket says "each the tree's existing domain error"; the tree has two
   shapes (`Listing`/`Order` raise `TransitionError` directly, `Fulfillment`
   validates with a context and lets `RecordInvalid` carry it). A removal/lift/
   block/lift refusal is a single active-or-not gate with one message, the
   `Listing.transition` shape, not `Fulfillment`'s multi-rule validation —
   `TransitionError` fits without inventing a validation context for one field.
2. **`purchasable?` gained the removal check.** Not named in the ticket's
   outcome text, but Node's `isPurchasable(status, quantity, hasActiveRemoval)`
   is the spec and the method was silently wrong without it (defense in depth
   only today, since every caller already reads a removal-filtered listing —
   worth fixing since the next caller might not).
3. **Blocks do not move on a customer merge.** `Customer::MERGED_ASSOCIATIONS`
   is unchanged. Neither the ticket, the alignment contract, nor Node's
   moderation docs say a block follows an anonymous identity into the account
   it merges into; a block stays with the row an admin named.
4. **No demo data.** `db/seeds.rb` writes no removal or block row — recorded
   as new gap 13 in `docs/review.md` (with a matching "Suggested next steps"
   item), the same way FEAT-020 left the decline/refund seed gap open rather
   than growing the seed script inside an unrelated ticket.
5. **Removal/block reason length capped at 500**, matching `Refund::REASON_LIMIT`'s
   precedent for an admin free-text reason field; the ticket names no limit.

### Numbers

Before: 1102 runs, 3924 assertions, 0 failures, 100% line coverage (2046/2046).
After: 1174 runs, 4175 assertions, 0 failures, 0 errors, 100% line coverage
(2148/2148). `make lint` clean.

### Deviations from the contract

None on §5's paths, filter names, filter values, or the event vocabulary.
`docs/alignment.md` §5's payout row says `/admin/payouts?seller=` and
`POST /admin/payouts` — built exactly as named, alongside the two moderation
POST pairs.

### Fix-up

A review of `325a4c9` found one blocker and two should-fixes.

1. **`Admin::ListingRemovalsController#create` and
   `Admin::CustomerBlocksController#create` 500'd on an invalid reason.**
   `ListingRemoval`/`CustomerBlock` validate `reason` (`presence`, `maximum:
   500`), and `removals.create!`/`blocks.create!` raise
   `ActiveRecord::RecordInvalid` on a blank or over-long one. `Story` logs the
   raise as `refused` and re-raises; the controllers only rescued
   `TransitionError`, so the raise reached Rails as a 500. Both `create`
   actions now also rescue `ActiveRecord::RecordInvalid`, matching
   `Admin::RefundsController#create`'s precedent — `refusal.record.errors
   .full_messages.first` as the flash alert. Same fix on `remove!`'s `kind`:
   assigning an enum value outside `ListingRemoval::kind`'s two values raised
   `ArgumentError` on assignment, which the same `RecordInvalid` rescue
   cannot catch. `Conversation#kind` carries the same "enum on an admin- or
   caller-supplied string" shape and already opts into `enum ..., validate:
   true`, which turns an unrecognised value into a validation failure instead
   of an assignment-time raise; `ListingRemoval#kind` now takes the same
   option, so a bad `kind` fails validation and reaches the same
   `RecordInvalid` rescue as a bad `reason`. Added controller tests for a
   blank reason, a 501-character reason, and an unrecognised `kind` on both
   controllers (`kind` only applies to listings — `CustomerBlock` has no
   `kind` column).
2. **`Shop::FavoritesController#index` showed a removed listing.** It queried
   `Listing.where(id: ...)` with no storefront filter, unlike every other
   slug/id → listing path in the app. Matched Node's
   `find-favorite-listings.ts`: it filters `listings.status IN
   STOREFRONT_STATUSES` (`['for_sale', 'sold']`) and excludes a listing with
   an active (unlifted) removal via `NOT EXISTS`. That is exactly Rails'
   `Listing.on_storefront` scope (`where(status: ON_STOREFRONT).visible`,
   `ON_STOREFRONT = %w[for_sale sold]`), so `#index` now chains `.on_storefront`
   ahead of the favorites filter. Added tests: a favourited listing that is
   later removed drops off `/favorites`; one that is archived drops off too
   (archived is outside both Node's `STOREFRONT_STATUSES` and Rails'
   `ON_STOREFRONT`); one that sells out stays (`sold` is in both lists).
3. **`Customer#active_block` re-queried where `Listing#active_removal`
   does not.** Changed `blocks.active.first` (a scope call, always a fresh
   query) to `blocks.detect(&:active?)`, mirroring `active_removal`'s
   `removals.detect(&:active?)`. `Admin::CustomersController#show` already
   assigns `@blocks = @customer.blocks` before the view calls
   `@customer.blocked?` twice, so both calls now share the one association
   load instead of issuing a query each. Every other caller of `blocked?`/
   `active_block` (`block!`, `lift_block!`, `Shop::BaseController`,
   `Conversation`'s `can_shop?` check on the sender) runs without a preloaded
   association already, the same as `active_removal`'s other callers
   (`purchasable?`, `on_storefront?`) — `detect` on an unloaded association
   loads it once and returns the same row `.active.first` would, just without
   the `LIMIT 1`.

Numbers before the fix-up: 1174 runs, 4175 assertions, 0 failures, 100% line
coverage (2148/2148). After: 1182 runs, 4214 assertions, 0 failures, 0 errors,
100% line coverage (2150/2150). `make lint` clean.
