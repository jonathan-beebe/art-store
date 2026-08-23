---
id: IMPRV-007
type: improvement
status: resolved
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

## Working

Verified against the current tree (RFCTR-003 had already landed its parse-result changes to `seller/routes/listings.ts` and `checkout.ts` before this ticket started; re-read both fresh). All findings in the Problem section still applied.

Changed:
- `app/core/cart/cart-line.ts` untouched (consumer only, as expected). `app/actions/carts/cart-contents.ts`: `CartLineView` gained `lineTotalCents: Cents`, computed in `cartContents()` via `cartLineTotal`. `app/sites/shop/customer-order.ts`: new `OrderItemView`/`OrderFulfillmentView`/`CustomerOrderView`, `loadCustomerOrder` now decorates each fulfillment's items with `lineTotalCents` the same way. Both are additive fields, so the routes that just pass `contents.lines` / `found.fulfillments` through (`carts.ts`, `checkout.ts`, `orders.ts` — none in territory) needed no changes; `cart.ejs`, `checkout.ejs`, `order.ejs` now interpolate `line.lineTotalCents`/`item.lineTotalCents` instead of multiplying.
- `app/core/shop/listing-search.ts`: new `filterQuery(search)`, same string shape the template built inline. `home.ts` passes it; `home.ejs`'s pagination links use it instead of building the string themselves.
- `app/sites/shop/checkout-fields.ts`: new `missingFieldLabels(missingParts)`. `checkout.ts`'s `renderCheckout` now computes it instead of passing raw `missingParts`; `checkout.ejs` loops the labels with no lookup.
- `app/core/status-label.ts`: new `statusButtons(transitions)` → `{ status, label }[]`, label pre-built as `Mark <status>`. `status-buttons.ejs` now calls it and loops the result instead of `.toLowerCase()`-ing inline. Wiring this required touching `seller/routes/listings.ts` beyond the form-fields line the brief scoped that file to (`index`/`show` render calls needed `statusButtons` added alongside the `statusLabel` they already pass) — no other place can hand a template a function to call. Treated as in scope since it's additive and the outcome is explicitly required; flagging the interpretation here per the brief's guidance on blocked decisions.
- `app/sites/seller/listing-form-fields-view.ts` (new, +test): `listingFormFieldsView(fields, errors)` → each of the 7 form controls as `{ value, errorId }` (image has no `value`). `seller/routes/listings.ts`'s form render-data lines (`newForm`, `create`'s 422, `editForm`, `update`'s 422, both branches of `renderOversizedImageForm`) now build `fields` through it; `errors` is still passed alongside for `field-error.ejs`'s message text. `form.ejs`'s 7 controls read `fields.X.value` and add `aria-describedby`/`aria-invalid` when `fields.X.errorId` is set; the image control's `aria-describedby` appends the error id to its existing hint id instead of replacing it.
- `app/sites/shop/shop-page.ts` and `app/sites/admin/page.ts`: `shopPage`/`adminPage` are now generic, returning `typeof VIEW_HELPERS & typeof PAGE_DEFAULTS & T` / `typeof VIEW_HELPERS & T & { title: string }` instead of `Record<string, unknown>`. `admin/page.ts` isn't in the brief's file list but the "typed helper bag for shopPage/admin page" outcome names it explicitly and no other file could satisfy it — included. No seller equivalent exists (each seller route inlines its own helpers into `reply.render`), and building one would mean touching every seller route file, which is out of territory, so left alone.
- `app/views/partials/head.ejs` (title, titleSuffix) and `app/views/partials/unread-badge.ejs` (classes, unreadMessageCount) are new, included from all three `views/layout.ejs` via the same view-root-absolute `views/partials/...` path the existing `debug-alert`/`flash` includes already use. The debug-alert include and the `<script defer src="/app.js">` tag are untouched (BUG-006/FEAT-016). Did not touch the include-path inconsistency inside each site's own partials (seller's relative `../partials/...` vs shop/admin's `sites/<site>/views/partials/...`) — the discovery note called it optional ("worth resolving"), not a required outcome, and touching every include in every template (stat.ejs, empty.ejs, card-fields.ejs, decline-notice.ejs, form.ejs, field-error.ejs) was judged to add risk for no required behavior change.
- Each layout's `<%- body %>` gained a `<%# the page's already-escaped markup %>` comment above it. Left the 62 `<%- include(...) %>` sites uncommented, per the discovery note — `docs/architecture.md`'s Views row (single sentence, only that row) now states `<%-` is reserved for `include` and `body`.
- `alt=""` on `cart.ejs`, `listing.ejs`, `views/partials/listing-card.ejs` (title is already the adjacent heading/link text). `seller/views/listings/edit.ejs`'s descriptive alt was already correct and untouched. `seller/views/listings/index.ejs`'s thumbnail was already `alt=""`.
- Boolean attributes: `checkout.ejs` (`readonly`, `required` on the two ternary sites named in the ticket), plus `home.ejs`'s and `payouts.ejs`'s `selected` ternaries on `<option>` — same anti-pattern, in territory templates, fixed for consistency even though the Problem section didn't name them individually.
- `admin/routes/payouts.ts`: git status showed it clean (unmodified) when reached, done last as instructed — single anchored edit of the render-data line, `today: toTimestamp(...)` → `asOfDate: toTimestamp(admin.clock.now()).slice(0, 10)`. Kept the slice in the route rather than introducing a `Day` core type, since core is off-limits beyond the two named additions (`listing-search.ts`, `status-label.ts`) and the brief capped this file to one line; `payouts.ejs` reads `asOfDate` with no slicing.

Verified (grep templates for `*`, `.find(`, `encodeURIComponent`, `.slice(`): one hit left, `app/sites/seller/views/listings/form.ejs:106` — `accept="image/*"`, a literal MIME-type wildcard attribute value, not a computation.

Left alone: `checkout.ejs`'s `shipping[field.part] || ''` (plain property access on an object already shaped for the form, not a `.find(` lookup — not named in the ticket's findings); the seller/shop/admin per-template include-path inconsistency (see above); a seller-side `sellerPage`-style helper bag (no such helper exists yet, out of territory to introduce across every seller route).

Concurrent edits from other tickets in the shared tree during this work: a `plugins/id-param.ts` → `http/id-param.ts` rename (and `plugins/form-body.ts` → `http/form-body.ts`) touched import lines in several files this ticket also edited (`customer-order.ts`, `checkout.ts`, `seller/routes/listings.ts`, `admin/routes/messages.ts` — the last untouched by this ticket otherwise); left those import-path changes as they were found, per the brief.

`npm run check` from `prototype/node/src`: exit 0, typecheck clean, lint clean, **1519 pass, 0 fail** (shared tree; other tickets landing concurrently, so not all of the delta from the last recorded shared-tree count is this ticket's). Coverage 99.54% lines / 96.61% branches / 99.47% functions, above the 90/80 line/branch gates. This ticket added 21 new test cases across `shop-page.test.ts` (new), `customer-order.test.ts` (new), `checkout-fields.test.ts` (new), `listing-form-fields-view.test.ts` (new, 5 tests), plus additions to `listing-search.test.ts`, `status-label.test.ts`, `cart-contents.test.ts`, `home.test.ts`, and `seller/routes/listings.test.ts`.
