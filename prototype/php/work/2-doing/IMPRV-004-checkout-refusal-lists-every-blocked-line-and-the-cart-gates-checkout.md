---
id: IMPRV-004
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-004: Checkout refusal lists every blocked line and the cart gates checkout

## Problem
`PlaceOrder` stops at the first `DomainRuleViolation` and the controller redirects to `/cart` with one message, so a customer with two stale lines fixes one and is refused again; the cart page keeps the checkout button live on a line that is sold out or off sale. Node's `planOrderPlacement` returns every blocked line with a reason (`removed | off_sale | sold_out | short_stock`) and the cart page disables checkout while any line is blocked.

## Goal
A customer learns everything wrong with their cart at once, before and at checkout.

## Outcome
Checkout re-renders (422) with every blocked line and its reason; the cart page marks each blocked line and disables the checkout control while any exists; the pay page refuses an order that went stale between placement and payment with the same shape; tests cover each reason and the multi-line case.

## Why it matters
"Fix one, get refused again" is the CX the alignment brief rules out; Node has the finished shape.

## Discovery notes
A value object (`OrderPlacementPlan`) that folds the cart into `placeable | blocked(lines)` keeps the decision testable without the database, in the PHP prototype's value-object idiom.

## Related work
- BUG-001 (first-violation redirect)
- prototype/node BUG-003 (planOrderPlacement)

## Working

### The plan
`App\Domain\Orders\OrderPlacementPlan` (`src/app/Domain/Orders/OrderPlacementPlan.php`),
a pure value object matching Node's `planOrderPlacement`. `OrderPlacementPlan::for(list<PlaceableLine>)`
folds every line into `isPlaceable(): bool` plus a `blocked` list of
`BlockedLine`s, each carrying an `UnavailableReason` (`Removed`, `OffSale`,
`SoldOut`, `ShortStock` — the same four values and precedence order as
Node's `unavailableReason()`: a removal outranks the listing status; nothing
left to sell reads `SoldOut` before `ShortStock`). `blockedReasonFor(string $listingId)`
answers the cart page's per-line lookup. No Eloquent, no `Illuminate`, no
clock — `OrderPlacementPlanTest.php`'s dataset covers each reason plus the
two-lines-two-reasons case with no database.

`Removed` is modelled now but **unreachable**: every `PlaceableLine` a caller
builds today passes `hasActiveRemoval: false`, because `listing_removals`
does not exist yet. FEAT-024 wires the admin removal lookup into
`Cart::placementPlan()` and `Order::placementPlan()` (the only two builders)
and nothing else needs to change — the reason, its notice text, and the
precedence rule are already in place.

`App\Models\Cart::placementPlan()` and `App\Models\Order::placementPlan()`
build the lines from each model's own `items.listing` relation (loaded by
the caller first, same precondition as `Cart::lines()`). One builder per
source type, not a shared generic: `CartItem` and `OrderItem` differ in
where the line's title comes from (the listing's live title vs. the order
item's snapshot), and the duplication is eight lines.

### Carrying the refusal through `Story::tell()`
`App\Domain\Orders\OrderPlacementRefused extends DomainRuleViolation`, and
`DomainRuleViolation` lost its `final` for exactly this subclass. It carries
`list<BlockedLine> $blocked`, a one-sentence `getMessage()`, and implements
a new `App\Domain\CarriesRefusalData` interface (`refusalData(): array`).
`Story::tell()`'s catch block now merges `$violation instanceof CarriesRefusalData
? $violation->refusalData() : []` into the `refused` line's `data`, after
the unit of work's own facts — generic to any future violation that wants to
carry structured data, not aware `Orders` exists. This is the "typed
refusal result" option from the ticket's choice, chosen over a bespoke
return type because it keeps `tell()`'s automatic `will`/`refused` handling
working with no change at either call site.

`PlaceOrder` builds the plan **inside** `DB::transaction()` now — the cart's
`items.listing` load moved in from before the transaction opened, closing
the race the ticket named: two shoppers reading the same stale row outside a
transaction could both pass the check and both call `sell()`. `FinalizeOrder`
asks the same question (`assertStillAvailable`) before a retry retakes stock,
reached only from `payment_failed` (`OrderStatus::retakesStockOnRetry()`) —
the one path where a decline already handed stock back and a retry could
find it gone again.

### Where the shopper lands
`CheckoutController::place` catches `OrderPlacementRefused` ahead of the
general `DomainRuleViolation` catch: it re-renders `shop.checkout` itself at
422 with `$blocked` and the flashed request (`$request->flash()`, so `old()`
already used by the Blade form needs no template change to preserve what was
typed). A refusal that is not about a specific line — a blocked customer —
still redirects to the cart, unchanged. `OrderPaymentController::pay` answers
a stale retry the same way, at 422 on `/orders/{order}/pay`.

No same-request 422-Blade-render existed anywhere in this tree before this
ticket (checked); every other refusal here is a 302 back with `$errors`
flashed. This one has to be different because there's more than one line to
show, `old()`-flashing achieves the "no template rewrite" part cheaply.

`CartController::show` passes `Cart::placementPlan()` to `shop/cart.blade.php`,
which now marks a blocked line with `ucfirst($reason->notice())` (replacing
the old generic "No longer available") and swaps the Checkout `<a>` for a
real `<button type="button" disabled aria-disabled="true">` while any line
is blocked — non-interactive, not merely styled or hidden.

### Deviations
- `ListingStock::afterSale`'s own `DomainRuleViolation` throws (off-sale,
  insufficient quantity) are now unreachable from `PlaceOrder`/`FinalizeOrder`
  in practice — the plan check already refused before `sell()` runs on the
  same in-memory rows. Left untouched: it is still exercised directly by
  `ListingStockTest.php`, and Node's `stockAfterSale` keeps the identical
  shape of defensive check ("`planOrderPlacement` settles the expected cases
  ... before a sale reaches here") rather than removing it.
- The refusal message text changed for the two existing scenarios BUG-001
  covered (`"X" is no longer for sale.` → `"X" is no longer available to
  buy.`, from `ListingStock`'s message to `OrderPlacementRefused`'s). Both
  `PlaceOrderTest.php` assertions were updated; no other surface asserted the
  old wording.

### Numbers
Baseline (HEAD before this ticket): 1247 tests, 3395 assertions, 100.0%
line coverage. After: 1275 tests, 3502 assertions, 100.0% line coverage.
`make check` (Pint → PHPStan level max → assets → Pest `--min=100`) green.

### Files a concurrent agent might also hold
`src/app/Domain/DomainRuleViolation.php` (dropped `final`) and
`src/app/Support/Story.php` (the `CarriesRefusalData` merge in `tell()`) are
shared infrastructure — a narrow, additive change to both, but worth
checking for conflicts against any other ticket touching refusal handling.
