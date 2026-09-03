---
id: IMPRV-027
type: improvement
status: resolved
created: 2026-09-03
---

# IMPRV-027: Stat tiles fit their figures at every width

## Problem
The shared-borders stat grid renders in five places: the admin dashboard money row at six columns from `lg` (`admin/dashboard.blade.php:98`), the ledger and accounting headline rows at four columns (`admin/ledger/index.blade.php:11`, `admin/accounting/index.blade.php:6`), and the seller dashboard and earnings rows at four columns (`seller/dashboard.blade.php:7`, `seller/earnings.blade.php:6`). Every tile sets its figure at `text-3xl` inside `p-6`, and each grid is `overflow-hidden`. In the 1152px admin column a six-column cell leaves about 140px for the figure, so "$3,191.40" clips at the cell edge. Two-word labels ("Fees earned", "Fees refunded") wrap in the same cells, which drops their figure a line and breaks the baseline across the row. The five grids are five copies of the same markup, so each drifts on its own.

## Goal
Every headline figure in the seller and admin portals is readable in full, on one baseline with its neighbours, at any viewport width.

## Outcome
- In every stat grid listed above, every figure is fully visible at every viewport width from 375px to 1440px: no clipped digits, no horizontal overflow, and no figure wider than its cell.
- Every tile label sits on one line, so the figures in a row share a baseline.
- The five grids render one tile markup, so a change to the tile lands in all five and the seller and admin tiles differ only by accent.
- Light and dark both hold.
- Every existing dashboard, ledger, accounting, and earnings test passes unchanged, including the `data-stat` and `data-cell` hooks those tests read.
- `make check` green.

## Why it matters
The money row is the first thing a founder reads on the dashboard and the only row on the ledger and accounting pages that summarises the totals below. A clipped total reads as a wrong total.

## Discovery notes
- Reference block: `__local__/resources/tailwind-application-ui-v4/html/data-display/stats/05-with-shared-borders.html` (the block the tiles copied) sets the figure at `text-2xl` and runs three columns at `md`; the block's spacing is `px-4 py-5 sm:p-6`. The dashboard doubled the column count and raised the size, which is where the overflow comes from.
- Directions the maker can weigh: fewer columns at `lg` for the six-figure money row (two rows of three), the block's `text-2xl`, `whitespace-nowrap` or `truncate` on labels with `min-w-0` on the cell, `tabular-nums` on the figure, or a fluid size. Any one of these is a design choice; the outcome is what is fixed.
- One shared tile component (an `x-ui.stat` or similar with an accent parameter) is the shape the outcome's "one tile markup" bullet implies; the dashboard's below-`sm` mobile block (`dashboard.blade.php:7-90`) is a separate list and can stay.
- Verify with Chrome screenshots at 1024, 1280, and 1440 wide on `/admin`, `/admin/ledger`, `/admin/accounting`, `/seller`, and `/seller/earnings`, in dark and light.

## Related work
- DSGN-005 (admin small-screen first), DSGN-006 (admin panes)
- PR #58 / PR #59 (the seller and admin chrome redesigns that introduced the stat grid)
- DSGN-008 (design system audit)

## Working
- Tests first in `app/View/Components/StatTileTest.php` (a sidecar beside the View namespace; the anonymous Blade component has no PHP class). Three tests render the five stat pages as admin and seller and pin: one figure class list and one label class list across every tile after normalising the `stone-`/`gray-` accent tokens; every figure at `text-2xl` (failed before the change, the tiles were `text-3xl`); every figure carrying the bottom-pinning `mt-auto`.
- New `components/stat-tile.blade.php`: one cell of a shared-borders grid with an `accent` prop (`stone` | `gray`), a `label` prop, the figure in the slot, and attribute passthrough onto the figure so `data-stat` / `data-cell` land where the dashboard, ledger, and accounting tests read them (the ledger test asserts adjacency like `data-stat="held">$180.00`, still byte-for-byte). The cell is a column flex container, `min-w-0`, `px-4 py-5 sm:p-6`; the label wraps; the figure is `text-2xl tabular-nums tracking-tight mt-auto`, so a two-line label grows upward and every figure in a row sits on the same bottom line because grid rows stretch cells to one height.
- The five grids render the component: admin dashboard money row and the Traffic tile, ledger, accounting, seller dashboard, seller earnings. The two six-figure rows (dashboard money, accounting) drop `lg:grid-cols-6` to `sm:grid-cols-3`, two rows of three; the four-figure rows keep four columns, where `text-2xl` leaves room for the widest total. The dashboard's below-`sm` block and the `<dl>` count grids are a different tile and are untouched.
- Truncation was tried first and rejected: at 375px a two-up seller earnings cell has about 139px of text width and "Released, awaiting payout" clipped to an ellipsis.
- Verified in Chrome at 1280 in dark: `/admin` money row (two rows of three, figures aligned), `/admin/accounting`, `/seller` and `/seller/earnings` as a seller. The browser tool's window resize does not change the captured viewport, so 375 and 1024 were checked by class arithmetic. Light mode by class review: every dark token has a light counterpart.
- Gate: `make test` 4046 passed / 33114 assertions; `make lint` clean; `make assets` builds.
- Found, not fixed: `mt-auto` relies on the grid's default `align-items: stretch`; an `items-start` on a grid container would silently unpin the figures and no test reads the rendered position.
