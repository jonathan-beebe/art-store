---
id: IMPRV-007
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-007: Templates interpolate finished view models

## Problem
`app/sites/shop/views/cart.ejs:37`, `app/sites/shop/views/checkout.ejs:76`, and `app/sites/shop/views/order.ejs:76` each compute a line total with `<%= formatCents(line.unitPriceCents * line.quantity) %>` — a raw `*` in the template. `app/core/cart/cart-line.ts:17-19` already exports `cartLineTotal(line)`, which routes the same multiplication through `multiplyCents` and its integer assertions; the templates bypass it, so a non-integer price or quantity renders a wrong number silently instead of throwing where core would have caught it.

Lookups, string building, and date slicing happen inside templates in four places: `app/sites/shop/views/checkout.ejs:8-9` finds a field by `part` in a `fields` array and falls back to a literal label inline; `app/sites/shop/views/home.ejs:47` builds a query string with `` `q=${encodeURIComponent(search.term || '')}&medium=${encodeURIComponent(search.medium || '')}` ``; `app/sites/admin/views/payouts.ejs:55` writes `value="<%= today.slice(0, 10) %>"` against a full ISO instant passed from the route for a `<input type="date">`; `app/sites/seller/views/partials/status-buttons.ejs:8` computes `Mark <%= statusLabel(next).toLowerCase() %>` in the view.

The three site layouts (`shop`, `seller`, `admin` under `views/layout.ejs`) repeat the same page character-for-character in several places: the doctype/`<html lang>`/charset/viewport/stylesheet head block, the two partial includes, the `<main><%- body %></main>` wrapper, and the unread-messages badge markup, differing only in Tailwind classes. The sign-in/sign-out fork is a fourth repetition.

None of the 62 `<%-` (raw, unescaped) occurrences in the templates carries a justifying comment, though all 62 are either an `include(...)` or the layout's `<%- body %>` — structurally safe but undocumented.

`app/sites/seller/views/partials/field-error.ejs:2` renders `<p id="<%= field %>-error" …>`, and `app/sites/seller/views/listings/form.ejs` includes it after seven controls, but no `<input>`/`<textarea>` in `form.ejs` declares `aria-describedby` pointing at that id, and none gets `aria-invalid="true"` when it has an error — a screen-reader user hears the label and value but never the reason the submit bounced. Line 101 of the same file already uses `aria-describedby` correctly for hint text on the same control whose error is unassociated.

`app/sites/shop/views/partials/listing-card.ejs:5,8` and `listing.ejs:4,9` set `alt="<%= listing.title %>"` immediately beside an `<h2>`/`<h1>` with the same title, so a screen reader announces the title twice per card — every card on the storefront grid.

`app/sites/shop/views/checkout.ejs:28,50` render boolean HTML attributes through a ternary inside escaped interpolation: `<%= isVerified ? 'readonly' : '' %>` and `<%= field.isRequired ? 'required' : '' %>`, which emits a stray space when false.

## Goal
Templates contain only loops and interpolation; every computed value they render — line totals, filter labels, field errors — is computed by a pure function upstream.

## Outcome
- Line totals come from `cartLineTotal` on the view model.
- Checkout missing-field labels, home filter query, payouts date, and status button labels are computed by pure functions with tests.
- Shared layout chrome is one partial.
- Every raw `<%-` output has a one-line justification.
- Error messages are linked to their inputs with `aria-describedby` and `aria-invalid`.

## Why it matters
The doctrine states templates contain loops and interpolation only, and that money/quantity arithmetic lives in core — the raw `*` in three templates is exactly the case that rule exists to prevent, and it already has three occurrences, past the duplication threshold the codebase otherwise uses to justify abstraction. The lookups/string-building/date-slicing findings are the same rule applied to non-money view logic: the codebase already has the right shape elsewhere (`shop-page.ts`, `admin/page.ts`, `customer-order.ts`, `decline-notice.ts`, `checkout-fields.ts` are all pure view-model layers), so this is inconsistency with an established pattern, not a missing pattern. The field-error association gap is a genuine accessibility defect: a sighted user sees the red text under the field, a screen-reader user gets no signal that an error exists or what it says. The three repeated layouts are the same page copied three times, which is the "abstraction only for duplication felt three times" threshold met.

## Discovery notes
- Put `lineTotalCents` on the `CartLineView` (and the equivalent on the order-item view model) via `cartLineTotal`, and have the templates interpolate `formatCents(line.lineTotalCents)`.
- `missingFieldLabels: readonly string[]` from the checkout route; `filterQuery: string` from the home route; `asOfDate: Day` (or similarly narrow) from the payouts route; `{ status, buttonLabel }[]` for the status buttons. Each is a small pure function next to its route, and each becomes directly unit-testable.
- A shared `views/partials/head.ejs` (title suffix as a parameter) and a `views/partials/unread-badge.ejs` (classes as a parameter) remove the mechanical duplication without merging the three sites' visual identities, which should stay separate. The include-path convention is also inconsistent today — seller uses relative includes, shop and admin use view-root-absolute — worth resolving in the same pass.
- One comment above each layout's `<%- body %>` ("the page's already-escaped markup") settles the load-bearing case; a line in `docs/architecture.md` stating `<%-` is reserved for `include` and `body` covers the rest without a comment on all 62 sites.
- Have the route's view model hand each field `{ value, errorId | null }`, then render `aria-describedby="<%= field.errorId %>"` and `aria-invalid="true"` when set. Seven controls in `form.ejs`, one shape.
- `alt=""` on the listing-card and listing-detail images, since the adjacent title already carries the name as a heading/link text. Keep the descriptive `alt` on `seller/views/listings/edit.ejs:5` ("Current image for …"), where there is no adjacent title and the alt text is doing real work.
- Replace the two ternary-into-attribute sites in `checkout.ejs` with `<% if (...) { %>readonly<% } %>` style conditionals, or fold them into the same field view model as the error-association fix.

Files expected to touch: `app/sites/shop/views/cart.ejs`, `checkout.ejs`, `order.ejs`, `home.ejs`, `listing.ejs`, `views/partials/listing-card.ejs`; `app/sites/admin/views/payouts.ejs`; `app/sites/seller/views/partials/status-buttons.ejs`, `views/partials/field-error.ejs`, `views/listings/form.ejs`; the three `views/layout.ejs` files and their route-side view-model builders (`shop-page.ts`, `admin/page.ts`, seller equivalent); `app/core/cart/cart-line.ts` (consumer only, no change expected there).

This ticket's aria-describedby/field-error work touches the same view-model shape that IMPRV-002 (typed request validation) and RFCTR-003 (form parsing returning explicit errors) produce field errors for — land after either if both are in flight, so the field-error shape is defined once.

## Related work
- 03-core-shell.md: "Money arithmetic in templates, bypassing `cartLineTotal`/`multiplyCents`"
- 06-tests-views.md: "Templates do money arithmetic that core already owns", "Lookups, string building and date slicing inside templates", "The three site layouts are the same page, copied", "Every `<%-` is an include or the layout body; none carries the justifying comment", "Field errors are not associated with their inputs", "Image `alt` text duplicates the heading beside it", "Boolean attributes rendered through escaped interpolation"
- 02-types-boundaries.md: "View models are `Record<string, unknown>` bags" (the cheap 80% referenced in the manifest — typing the shared helper bag is related but out of scope for this ticket, which focuses on removing computation from templates rather than typing the render boundary)
- Related tickets: IMPRV-002, RFCTR-003 (field-error shape)
