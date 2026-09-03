---
id: IMPRV-028
type: improvement
status: open
created: 2026-09-03
---

# IMPRV-028: Admin filter forms share one control height and one primary button

## Problem
`components/admin/filters.blade.php` lays its fields out with `items-end` and a `min-h-11` submit button. Its two select components render at different heights: `seller-filter.blade.php:5` uses the ring idiom (`inset-ring`, `py-2`), `type-filter.blade.php:5` still uses `rounded border`. The button is taller than either select. On the ledger the "Seller" and "Type" labels therefore sit at different heights and the Filter button overshoots both controls. The same form serves orders, fulfillments, listings, customers, ledger, and payouts.

The logs page (`admin/logs/index.blade.php`) has its own header form. Its Filter button is inverted (`bg-stone-900`, white in dark; line 166) while every `x-admin.filters` button is `bg-stone-700`; "More filters" is a `<summary>` at `py-1.5` (line 83) beside the `min-h-11` submit; the Requests/Lines segmented control is a third height. One row carries three control heights and the admin carries two primary-button treatments for the same action.

## Goal
Every admin filter row reads as one row: one control height, one baseline, one primary button.

## Outcome
- In each of the seven admin filter forms (the six `x-admin.filters` consumers and the logs header form), every control in a row (label-plus-select, label-plus-input, submit button, secondary button or summary, segmented control, Clear link) shares one height and one baseline; no control overshoots its neighbours.
- Every select and text input in those forms uses the same field idiom the redesign uses elsewhere in the admin (the ring/outline idiom), so no form mixes bordered and ring controls.
- "Filter" and "Apply filters" render one primary-button treatment across the admin, and it is the admin's primary button (the stone treatment the rest of the portal uses). "More filters" and "Clear" render one secondary treatment.
- Tap targets stay at least 44px tall on touch widths (the reason `min-h-11` exists) without the button standing taller than the fields beside it on desktop.
- Every existing index and logs test passes unchanged: the same field names, the same GET action, the same option values, the same applied-filter chips.
- `make check` green.

## Why it matters
The filter row is the first control on six admin pages. A row whose labels and buttons do not line up reads as unfinished on every visit, and two primary-button colours for the same verb teach an operator nothing.

## Discovery notes
- Reference blocks in `__local__/resources/tailwind-application-ui-v4/html/forms/` (select menus, input groups) and `elements/buttons` set the canonical field height (`py-1.5 text-sm/6` with the ring) and button height (`px-3 py-1.5 text-sm/6 font-semibold`), which match each other by construction. The seller-tool memory notes `x-form.field` is on the border idiom too; that component is a candidate to converge at the same time or a follow-up.
- `min-h-11` arrived with DSGN-005 (small-screen admin, PR #38) as the 44px tap target. A responsive minimum (`min-h-11 sm:min-h-0`, or the field idiom's own height at `sm`+) keeps that without the desktop overshoot; the maker chooses.
- The type filter's `rounded border` select is the one control still on the old idiom in `x-admin.filters`; `customer-filter`, `status-filter`, `standing-filter`, and `removed-filter` should be checked the same way.
- The logs form was restyled in DSGN-004 before the stone chrome landed in PR #59; its inverted button is the pre-stone primary. `docs/admin.md` and the admin-tool memory record the stone tint rules (primary `stone-700` hover `stone-600`).
- Verify with Chrome screenshots of `/admin/ledger`, `/admin/orders`, `/admin/payouts`, and `/admin/logs` at 1280 wide and at 390 wide, dark and light.

## Related work
- DSGN-004 (log viewer filters), DSGN-005 (small-screen admin, the 44px targets), DSGN-006 (admin panes)
- PR #59 (admin stone chrome)
- DSGN-008 (design system audit)
