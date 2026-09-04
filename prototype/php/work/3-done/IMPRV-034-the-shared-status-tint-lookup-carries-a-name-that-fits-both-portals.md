---
id: IMPRV-034
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-034: The shared status-tint lookup carries a name that fits both portals

## Problem
`App\Domain\Orders\FulfillmentStatus::sellerBadgeTint()` is read by the seller order/customer/earnings views and by the admin fulfillment views (`resources/views/admin/fulfillments/show.blade.php`, `resources/views/components/admin/fulfillments-cells.blade.php`), but its name still says "seller" (audit `__local__/design/seller-portal/AUDIT.md` §6, FEAT-055 row: "`sellerBadgeTint()` serves both portals and wants a plain name").

## Goal
The status-to-tint lookup's name matches what it does: one method both portals call, named for neither.

## Outcome
A reader of an admin fulfillment view finds a plainly-named method for the status pill's tint, with no "seller" in its name; every existing call site (seller and admin) reads through that name; the method's docblock says it serves both portals.

## Why it matters
A name that claims one owner when there are two invites a second, disagreeing copy the next time someone building an admin page can't find where the tint comes from.

## Discovery notes
- `app/Domain/Orders/FulfillmentStatus.php:67` is the method.
- Call sites: `app/Seller/FulfillmentLanes.php`, `resources/views/seller/{earnings,earnings/statement,orders/show,customers/show}.blade.php`, `resources/views/components/seller/context-rail.blade.php`, `resources/views/admin/fulfillments/show.blade.php`, `resources/views/components/admin/fulfillments-cells.blade.php`.
- `App\Domain\Listings\ListingStatus::sellerBadgeTint()` is a different method on a different enum, seller-only, and outside this ticket.

## Related work
- FEAT-053 (converged the admin views onto this method)
- FEAT-055 (found and left the naming behind)

## Working
`FulfillmentStatus::sellerBadgeTint()` renamed to `badgeTint()`; docblock kept
as-is (already named both portals). Every caller updated: `FulfillmentLanes`,
the six seller/admin views, and the test (name and dataset description
dropped "seller-portal"). `App\Domain\Listings\ListingStatus::sellerBadgeTint()`
left untouched — a different method on a different enum, seller-only, outside
this ticket. Scoped `FulfillmentStatusTest.php` + `FulfillmentLanesTest.php`:
44 passed. `make precommit` green (pre-commit hook, this commit).
