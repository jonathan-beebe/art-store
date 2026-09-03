---
id: DSGN-010
type: design
status: open
created: 2026-09-03
---

# DSGN-010: The log filter bar has an information architecture that flows at every width

## Problem
The `/admin/logs` header (`admin/logs/index.blade.php`, the `<form method="GET">` at line 20 and its children) is one `flex flex-wrap` row holding controls of three different intents and two interaction models. Scope controls (the Domain segmented links, the Level count chips) apply on click as links. Refinement controls (the Event select, the nine fields behind More filters) apply only on the Filter submit. The Requests/Lines pair is a view mode, not a filter, and sits among the filters. Because the row is a single wrap container with no regions, the layout at each width is whatever wrapping produces: at 1440 the Clear link lands alone on a second row; at 1024 the Event select stretches and the buttons drop to a second row; at 768 the form stacks into five rows with the Requests/Lines toggle floating right beside a full-width select and Filter and Clear at the bottom. No width reads as designed. The applied-state strip below the form restates the Domain choice as a chip, so the same fact shows twice.

## Goal
A founder reading the log viewer understands at a glance what narrows the list, what changes how it is shown, and where to act, and the bar keeps that reading at any width.

## Outcome
- The header groups its controls by intent, and the grouping is visible: what scopes the list (domain, level), what refines it (event and the disclosed fields), and what changes the view (requests or lines). Each group has a stable place in the bar.
- The bar has one interaction model: either every control applies on change, or every control waits for one submit. Whichever is chosen, the model is the same for a chip, the select, and the disclosed fields, and the Clear action lives beside the group it clears.
- At 390, 768, 1024, and 1440 wide the bar reflows into an arrangement designed for that width: no control is orphaned on a row of its own, no select stretches to fill leftover space, and the primary action stays where the eye expects it.
- The Level chips keep their counts, the disclosure keeps the nine rarely used fields out of the bar, the applied-state strip keeps its removable chips and match count, and a fact shown as a control in the bar is not restated as a chip below it.
- The default landing state (shop domain, grouped requests, health checks hidden) is unchanged, and every `LogControllerTest` case passes with the same query parameters.
- Light and dark both hold; keyboard focus order follows the visual grouping; each group carries an accessible name.
- `make check` green.

## Why it matters
The logs page is the founder's first stop when something is wrong. A header whose layout is an accident of wrapping costs a second of orientation on every visit and makes the viewer read as unfinished beside the rest of the admin.

## Discovery notes
- Recurrence: DSGN-004 designed this header (workflow-first: domain, level, event visible, the rest behind a disclosure). Its choices about which controls are primary still hold; this ticket is the layout and the interaction model of those controls, which DSGN-004 left to one wrap row.
- Design first, then build: produce a canvas with the bar at the four widths before touching Blade, and get it accepted, the way DSGN-004 and the admin-tool redesign did. The canvas is the reference, not a pixel spec.
- Reference blocks in `__local__/resources/tailwind-application-ui-v4/html/`: `headings/page-headings/12-with-filters-and-action.html` (a heading row with filters on one side and the action on the other), `elements/button-groups` for the domain and view segmented controls, `forms/select-menus` for the event select, and the ecommerce "filters" pattern (a filter bar with a disclosure row beneath) for the disclosed fields. Lean on those defaults; the admin's stone tint is the only deviation.
- Interaction model: the chips and segmented links already apply on click, and the select and text fields are the only reason a Filter button exists. Making the select apply on change (a small progressive-enhancement submit, with the button kept for no-JS) removes the button from the primary row and leaves Filter only inside the disclosure beside the fields it applies. The maker decides; the outcome only requires one model.
- The Requests/Lines view toggle is a candidate for the heading row's action side (right of the title), away from the filters.
- IMPRV-028 (in progress) converges the field, button, and link idioms in this header and constrains the select width; build on its components rather than around them.
- Verify with Chrome screenshots at the four widths, dark and light.

## Related work
- DSGN-004 (the first design pass on this header; canvas linked there)
- DSGN-005 (small-screen admin), DSGN-006 (admin panes)
- IMPRV-028 (filter control idioms, in progress on `php/design-nits`)
- IMPRV-016 / IMPRV-017 / IMPRV-022 (what the request rows carry; unchanged here)
