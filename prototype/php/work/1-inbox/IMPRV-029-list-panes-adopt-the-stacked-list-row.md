---
id: IMPRV-029
type: improvement
status: open
created: 2026-09-03
---

# IMPRV-029: List panes adopt the stacked-list row

## Problem
The seller and admin list panes render three row idioms. Admin orders, fulfillments, and listings (`components/admin/orders-cells.blade.php:22`, `fulfillments-cells.blade.php:26`, `listings-cells.blade.php:24`) and the seller's listing rows (`components/seller/listing-rows.blade.php:22`) use `flex items-center gap-3 px-6 py-3`: two lines on the left, a status pill on the right. Admin sellers and customers (`sellers-cells.blade.php`, `customers-cells.blade.php`) reuse the mobile card row (`x-admin.card-row`, `p-4`) with a time and a monospace amount on the right. The messaging inboxes (`components/messaging/inbox.blade.php:82`, `components/seller/messaging/inbox.blade.php`) use `px-6 py-4`. Against the Tailwind stacked-list "with links" row the panes run a tighter rhythm (`py-3` against `py-5`), have no trailing chevron, and disagree about what the right column holds.

## Goal
Every list pane in the seller and admin portals reads as the same list, drawn from the Tailwind stacked-list block, in the portal's accent.

## Outcome
- Every list pane rendered through `x-admin.list-detail` and `x-seller.list-detail` (admin: orders, fulfillments, listings, sellers, customers, messages; seller: listings, orders, messages) renders one row shape: a leading visual where the record has one (listing thumbnail, initials avatar), a title line and a muted supporting line, a right-aligned meta column (a status pill or a figure, over a muted time where one applies), and a trailing chevron; rows share the stacked-list block's vertical rhythm and gaps.
- The seller rows carry the indigo accent and the admin rows the stone accent; everything else about the row is identical between the two portals.
- The selected row is still marked (fill and inset rail) and still carries `aria-current`; the whole row is still one link; row order, counts, and the facts each row shows today are unchanged.
- The pane's own tests and the cell tests pass unchanged (`ListPaneWindowTest`, the index and show controller tests that read `data-pane-cell`), and the messaging tests pass unchanged.
- Light and dark both hold in both portals.
- `make check` green.

## Why it matters
A founder and a seller spend most of their time in a list beside a detail. Three row shapes in one product make the panes read as three tools, and a row without a chevron does not read as something to open.

## Discovery notes
- Reference blocks: `__local__/resources/tailwind-application-ui-v4/html/lists/stacked-lists/03-with-links.html` (the row: `flex justify-between gap-x-6 py-5`, `size-12` leading visual, `min-w-0 flex-auto` title column at `text-sm/6 font-semibold` over `text-xs/5` muted, a `hidden sm:flex sm:flex-col sm:items-end` meta column, then a `size-5 text-gray-400` chevron), and `14-narrow-with-truncated-content.html` / `17-narrow-with-badges.html` for the pane width the list sits in. Lean on the block's defaults; deviate only for the accent.
- One shared row component per portal (or one with an accent parameter, the shape IMPRV-026 used for the auth layout) with slots for the leading visual, title, supporting line, and meta column would stop the three idioms from returning. The per-section cells files then only decide what goes in each slot.
- The `x-admin.card-row` component keeps its mobile-card role for the tables' below-`sm` view; only its use as a pane row goes away.
- Admin sellers show the seller's email on both lines when the seller has no shop name (`hermione@example.com` twice); that is data, and a row that hides a supporting line equal to the title would be a cheap improvement to fold in.
- The admin-tool memory records that `x-seller.list-detail` has no pinned list-footer slot ("Showing N of M" scrolls with the list); that is separate and stays out unless the row change makes it free.
- Verify with Chrome screenshots of each pane at 1280 wide, dark and light, in both portals; the seller session needs a seeded seller's magic link from `/seller/login`.

## Related work
- DSGN-006 (admin list and detail panes; the cells files)
- PR #58 / PR #59 (seller and admin chrome redesigns)
- IMPRV-026 (accent-parameterised shared layout, the same shape a shared row could take)
- DSGN-008 (design system audit)
