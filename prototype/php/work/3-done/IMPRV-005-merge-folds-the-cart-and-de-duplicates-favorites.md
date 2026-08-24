---
id: IMPRV-005
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-005: Merge folds the cart and de-duplicates favorites

## Problem
`MergeAnonymousCustomer` re-points `carts.customer_id`, so a verified customer who shopped anonymously ends up with two carts and a `currentCart` heuristic picks one; favorites are re-pointed with `update_all` and rely on the unique index to drop duplicates. Node's `planCustomerMerge` folds cart quantities (sum, clamp to stock, drop zero lines) and de-dupes favorites as a pure plan with its own tests.

## Goal
Merging an anonymous customer into a verified one leaves exactly one cart and one favorites set, with nothing lost.

## Outcome
After a merge the owner has one cart whose lines are the sum of both clamped to stock, favorites are the union, conversations are folded (already), blocks and sent messages are re-pointed (already), and a test asserts every `customer_id` column is either in `CustomerOwnedTables::all()` or in an explicit left-behind list; the `currentCart` heuristic is gone; `docs/identity.md` states the fold.

## Why it matters
Retro item 4 asked for the merge as a fold; PHP has the manifest and the conversation fold but not the cart.

## Discovery notes
A `CustomerMergePlan` value object over both customers' cart lines and favorites, in the prototype's value-object idiom, applied inside the existing transaction.

## Related work
- FEAT-002 (identity), RFCTR-004
- prototype/node RFCTR-004 (planCustomerMerge)

## Working
`App\Domain\Customers\CustomerMergePlan::for()` is the pure plan — no Eloquent, no `Illuminate`, no clock — mirroring Node's `planCustomerMerge` line for line: cart quantities sum per listing (verified lines first, then anonymous-only listings, in first-seen order), clamp to `stockByListing`, drop anything that lands at zero; favorites split into `favoritesToMove` (nothing named that listing yet) and `favoritesToDrop` (the verified customer already has it), de-duplicating the anonymous side first. `CustomerCartLine` is the plan's own `(listingId, quantity)` pair — distinct from `App\Domain\Cart\CartLine`, which also carries a seller and a price for totalling a cart already settled on one owner. Both get a Pest dataset sidecar (`CustomerMergePlanTest`) covering sum, clamp, drop-zero, the missing-from-stock-map case, both-empty, one-side-empty, same-listing-on-both-sides, and a line already at stock, plus a cart-ordering case and the favorites union/dedupe/drop cases.

`MergeAnonymousCustomer` reads both customers' cart items (across every cart either one holds, not just one), their favorites, and the stock behind whatever listings either cart names; builds the plan; then applies it inside the transaction it already opens. The cart half: the verified customer's own cart survives if they had one, otherwise the anonymous customer's cart is re-pointed, otherwise a new one is created; every other cart either customer held is deleted (cascading its items); the survivor's items are wiped and rewritten from `plan->cartLines`. The favorites half: `favoritesToMove` rows get `UPDATE customer_id`, `favoritesToDrop` rows get deleted — updates and deletes only, never an insert, the same discipline `docs/identity.md` already documents for Node.

**`currentCart()` is gone.** Replaced by `Customer::cart()` — `$this->carts()->first() ?? $this->carts()->create()`, no item-count/created-at ordering, because a customer never holds more than one cart once the fold replaces blind re-pointing. Every reader (`CheckoutController`, `CartController`, `ShopLayoutComposer`, and their tests) moved onto `cart()`. `favorites` and `carts` came out of `CustomerOwnedTables::all()` (which now holds only `orders`, `listing_events`, `customer_blocks`) and into a new `CustomerOwnedTables::leftBehind()`, naming why each left-behind table (`favorites`, `carts`, `conversations`, `customer_merges`) is not blindly re-pointed. The manifest test lives in `App\Actions\Customers\CustomerOwnedTablesManifestTest` rather than beside `CustomerOwnedTables` itself — `App\Domain` tests run with no database bound (Pest.php gives `App\Domain` no `TestCase`), and the manifest has to read the schema (`Schema::getTables()` / `Schema::getColumns()`), so it sits next to the action instead, the same way `OrderLifecycleTest` and `RateLimitsConfigTest` are loose test files bound by directory rather than by a matching class.

**Decision — a removed listing in a folded cart**: not special-cased. Its row still carries the stock it held before removal, so a line for it survives the fold at that quantity, the same as it would sitting untouched in a single cart across a removal; `OrderPlacementPlan` is what blocks it when checkout is attempted, not the fold. Keeps the merge consistent with how a solo cart already treats a post-add removal, and keeps the plan simple (it does not need `hasActiveRemoval` as an input).

**Decision — no lock on the listing rows read for the clamp**: `AddToCart` reads `$listing->quantity` unlocked to bound a cart quantity (`CartQuantity::withinStock`), and only `PlaceOrder` locks (`lockedForPlacement`) because that is the point that persists a stock decrement. The merge's clamp is a bound like `AddToCart`'s, not a decrementing write — nothing here is truth until checkout re-validates, and the cart page recomputes `placementPlan()` fresh on every read, so a clamp a race left briefly over stock self-heals at the next render, the same as a listing that sells out while already sitting in a cart. Locking would only protect a number that gets re-checked anyway.

Deviation from the ticket's literal Discovery note: the plan does not carry favorites as one flat "union" list — it splits `favoritesToMove` / `favoritesToDrop`, matching Node's actual `planCustomerMerge` shape and its "updates and deletes only, never an insert" discipline (`docs/identity.md`'s existing Node caveat). The applied result is still the union, de-duplicated.

Left out: checked `docs/messaging.md` and `docs/data-model.md` for other mentions of the old `favorites`/`carts` blind re-point — neither names them (both only mention `customer_blocks`, notifications, and conversations, all unaffected by this ticket), so nothing there needed touching. Per the ticket's docs rule (F), only `docs/identity.md` was updated; MAINT-004 is the full docs refresh.

`make check`: Pint clean, PHPStan level max 0 errors, `composer test` green at 100.0% lines.

## Working

`App\Domain\Customers\CustomerMergePlan` folds both customers into one
outcome, over `CustomerCartLine` values: quantities for the same listing sum,
the sum is clamped to the listing's stock, and a line that clamps to nothing
is dropped. Favorites are the de-duplicated union, answered as
`favoritesToMove` and `favoritesToDrop` so the caller re-points only what the
verified customer does not already hold. Both are pure — no Eloquent, no
clock — and covered by Pest datasets.

`MergeAnonymousCustomer` builds the plan inside the transaction it already
opened and applies it there, so the merge stays one act.

The `currentCart` heuristic is gone. A merge now leaves the verified customer
with exactly one cart holding the folded lines, so nothing has to guess which
of two carts is current. Its three readers — `Shop\CartController`,
`Shop\CheckoutController`, and `ShopLayoutComposer` — read that one cart.

`CustomerOwnedTablesManifestTest` introspects the schema and asserts every
table carrying a `customer_id` column is named in either
`CustomerOwnedTables::all()` or `CustomerOwnedTables::leftBehind()`. A table
added later cannot be silently missed by the merge.

`docs/identity.md` states the fold.

### Deliberately left out

- **Listing rows are not locked for the clamp.** The stock a clamp is judged
  against is read without `lockedForPlacement()`. A merge does not take
  stock — it only decides what a cart may still hold — and checkout re-judges
  every line against locked rows at placement, which is where taking stock
  actually happens. Locking here would hold rows across a sign-in for no
  decision that survives to the till.
- **A removed listing survives the fold** as an ordinary line, clamped to its
  stock. The cart is not the storefront: `Listing::onStorefront()` hides it
  from browsing, and `OrderPlacementPlan` refuses it at checkout with the
  `removed` reason. Dropping it during a merge would delete a customer's line
  for a removal that may be lifted, which is the same call the favorites fold
  makes in keeping the row and hiding the card.

### Gate

PHPStan level max, 0 errors. Pint clean. The gate's remaining
run is the pre-commit hook's.
