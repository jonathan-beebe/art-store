---
id: BUG-008
type: bug
status: open
created: 2026-08-27
---

# BUG-008: Removing an option a variant depends on has no path that succeeds

## Problem
On the Choices screen, removing an option value a variant selects refuses with "This option value is selected by a variant; remove that variant first." (`App\Domain\Configurator\ConfiguratorDeletionGuard::forOptionValue`, app/Domain/Configurator/ConfiguratorDeletionGuard.php:31). Removing the axis itself refuses with "This axis has a variant built from one of its values; remove or reassign that variant first." (`::forAxis`, same file, line 24). The reporter also hit "This option value is selected by a variant; remove that variant first." when attempting to remove what they describe as the blocking variant — the same option-value message, on an action they took to be removing the variant rather than the option.

## Goal
A seller can get from "an option or axis a variant depends on" to "removed," by some order of operations that actually succeeds.

## Outcome
The seller can remove an option value or axis a variant depends on, following a path the UI itself makes available, without landing back on the same refusal.

## Why it matters
As reported this reads as a dead end: the seller cannot remove the option, the axis, or the variant standing in the way, with the second error message not clearly describing the action that produced it.

## Discovery notes
Reported live against listing lst_01M128EBWPGB42G0ARSYMB999A. `routes/seller.php` registers `Route::resource('listings.variants', VariantController::class)->only(['index', 'store', 'update'])->scoped();` — there is no destroy route for a variant, and the Combinations & stock screen (resources/views/seller/listings/variants/index.blade.php) exposes no delete action for a variant row, only Save and the "Offered" checkbox. The reported "removing a child variant" attempt could not have reached a variant-delete action through the current UI; it is worth confirming exactly what control was used before deciding whether this is one bug (no way to remove the blocking variant at all) or two (that, plus a mismatched error message).

## Related work
- none

## Working

**One bug, not two.** Before this fix, `routes/seller.php` registered
`listings.variants` with only `index`, `store`, `update` — no `destroy` route
existed, and `resources/views/seller/listings/variants/index.blade.php` had
no delete control on a variant row (Save and the "Offered" checkbox only).
There was no code path, UI or route, through which the reporter's "remove the
blocking variant" attempt could have reached a variant-delete action. The
second error they saw — the same "This option value is selected by a
variant; remove that variant first." text — is `ConfiguratorDeletionGuard::forOptionValue`
firing again, most likely from a repeated attempt at the option-value Remove
control (the only Remove control near that combination), not a mismatched
message from some other action. No message text needed a fix; none changed.

**What changed:**
- `src/app/Domain/Configurator/ConfiguratorDeletionGuard.php` — added
  `forVariant(bool $referencedByCartOrOrder)`, refusing with `This
  combination is in a cart or an order; turn off "Offered" instead of
  removing it.` Cart and order rows hold a nullable, `nullOnDelete` foreign
  key to `variant_id` rather than a restricting one: deleting a referenced
  variant would silently null that column instead of failing, which flips
  `CartItem`/`OrderItem::isConfigured()` to `false` and makes
  `CartItem::currentBreakdown()`/`currentAvailability()` and
  `StockMovement::claim()`/`release()` treat a configured line as an
  unconfigured one — mispricing a live cart line and restocking the wrong
  row on a cancel/decline. The guard closes that gap the same way the axis
  and option-value guards close theirs.
- `src/app/Actions/Configurator/DeleteVariant.php` — new action: checks the
  guard against `CartItem`/`OrderItem` rows naming the variant, then deletes
  it (mirrors `DeleteOptionAxis`).
- `src/routes/seller.php` — added `destroy` to the `listings.variants`
  resource's `->only([...])` list.
- `src/app/Http/Controllers/Seller/VariantController.php` — added `destroy`,
  mirroring `OptionAxisController::destroy` (authorize, rate-limit, delete,
  redirect with a status message).
- `src/resources/views/seller/listings/variants/index.blade.php` — added a
  "Remove" column and per-row delete form, mirroring the option-axis and
  option-value rows' own Remove controls.

**Tests added:**
- `App\Domain\Configurator\ConfiguratorDeletionGuardTest`: `it allows
  deleting a variant no cart or order references`, `it refuses to delete a
  variant a cart or order references`.
- `App\Actions\Configurator\DeleteVariantTest` (new file): `it deletes a
  variant no cart or order references`, `it refuses to delete a variant a
  cart still holds`, `it refuses to delete a variant an order still holds`.
- `App\Http\Controllers\Seller\VariantControllerTest`: `it removes a variant
  nothing depends on`, `it refuses to remove a variant a cart still holds`,
  `it refuses to remove a variant an order still holds`, `it refuses to
  remove another sellers variant`, `it trips the listing-write limit
  removing a variant`, `it BUG-008 unblocks removing the option value and
  axis a deleted variant no longer selects` (the ticket's end-to-end path:
  option-value removal blocked → variant removed → option-value removal
  succeeds → axis removal succeeds).

Confirmed all new tests failed for the right reason (no `destroy` route —
`RouteNotFoundException`/405) before implementing, then green after. Full
suite: `make check` (lint → assets → coverage) passes, 100% line coverage;
`composer test` reports 2717 passed, 0 failed.

**Refactor suggestions (not done):** none beyond what's noted above — the fix
follows the existing axis/option-value delete pattern exactly.
