---
id: IMPRV-028
type: improvement
status: resolved
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

## Working
- Tests first in `app/View/Components/AdminFilterFormTest.php`: renders the seven admin pages as an admin (the logs page with a bound `LogStore` fixture, since the suite runs with the log database off) and extracts every select, text/date input, Filter/Apply/payout submit, Clear link, and the More filters summary from their forms. Pins: one select class list (four distinct before), one input class list (two before), one primary-button class list carrying `bg-stone-700` (three before, logs was `bg-stone-900`), one Clear class list, and per form the same vertical sizing tokens on submits and selects with a `min-h-` floor present.
- Four new admin components hold the idioms once: `x-admin.select` and `x-admin.input` (Tailwind native select and input: `py-1.5 text-base sm:text-sm/6`, stone outline ring, `min-h-11 sm:min-h-0`), `x-admin.button-primary` (`bg-stone-700 hover:bg-stone-600 px-2.5 py-1.5 text-sm/6 font-semibold`, same floor), `x-admin.clear-link` (`inline-flex items-center py-1.5 text-sm/6`, same floor). The six filter select components render through `x-admin.select`, which retired the `rounded border` idiom on `type-filter` and `standing-filter`. `filters.blade.php`, the payouts POST form, and the logs header form and disclosure panel render through the components; the logs inverted primary is gone; the More filters summary takes the Tailwind secondary treatment at `text-sm/6`.
- One height by construction: field and button share `py-1.5 text-sm/6` (36px at `sm`+); below `sm` every control carries the 44px floor. The payouts submit dropped its full-width mobile treatment so all nine submits render one class list.
- The logs Event select is width-constrained by a `w-56` wrapper at the call site and its label is `sr-only` via a `labelClass` prop, so the header stays two rows. The Domain/Level/View chips remain `py-1 text-xs`, centred by the row's `items-center`. The header's information architecture is DSGN-010.
- Verified in Chrome at 1280 in dark: `/admin/ledger`, `/admin/payouts`, `/admin/logs`. Light by class review.
- Gate: `make test` 4053 passed; `make lint` clean; `make assets` builds.
- Found, not fixed: the Requests/Lines and Domain/Level chips are a shorter control class than the fields; DSGN-010 owns that header's layout.
