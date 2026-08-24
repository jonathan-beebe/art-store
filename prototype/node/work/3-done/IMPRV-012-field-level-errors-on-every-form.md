---
id: IMPRV-012
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-012: Field-level errors on every form

## Problem
Only the listing form renders errors beside the field with `aria-describedby`; the FAQ form, the ship form, the checkout form, the pay form, and the message forms flash a single sentence or list errors above the form. PHP renders every form through one `<x-form.field>` component with a single global refusal mapping (`DomainRuleViolation → back()->withErrors`).

## Goal
Every form in every site tells the user which field is wrong, next to it, the same way.

## Outcome
Every POST form re-renders with the submitted values kept and each error beside its field, linked with `aria-describedby`; a refused domain rule with no field renders as a form-level error in one shared place; one template partial/helper carries the markup; tests cover one field error and one form-level error per form.

## Why it matters
The alignment brief names "form sanitization and errors" as a shared CX; PHP meets it and Node does not.

## Discovery notes
RFCTR-003's result-union parsers already return per-field errors; the gap is rendering. An EJS partial taking `{name, label, value, error}` matches the PHP component's role.

## Related work
- RFCTR-003
- docs/alignment.md §7

## Working

Picked up mid-ticket at `4962d2b` (shared `form-field.ejs`/`field-error.ejs`/`form-error.ejs`
partials, `onTrip` on `rateLimitGuard`, sign-in forms converted) with two items the previous
worker left broken. Both resolved in the first commit:

- **`fieldView`/`FormFieldView`** (`src/app/http/form-field-view.ts`) was dead code — never
  imported anywhere, no call site. Deleted rather than adopted: `form-field.ejs` already computes
  its own `errorId` from the `error` message a route passes it (the pattern the three login forms
  already used), so there was nothing left for a second, precomputed-`errorId` mechanism to do.
- **`listing-form-fields-view.ts`** (the seller site's own hand-rolled `{value, errorId}` view
  model) is superseded by the same move: the listing form now calls `form-field.ejs` directly with
  the raw `ListingDraftFields` object and the parser's `ListingDraftErrors`, the same shape every
  other converted form uses. Its file and test were deleted; the image field stays hand-rolled
  (`type="file"` has no equivalent in `form-field.ejs`, and one field is not enough to justify
  adding one).

### Forms converted, by test coverage

| Form | Field-error test | Form-level-error test |
| --- | --- | --- |
| Listing form (create + edit), `seller/views/listings/form.ejs` | `listings.test.ts` (title, price — pre-existing, re-asserted against the new markup) | none needed — no domain refusal without a field exists for this form |
| FAQ edit row + publish form, `seller/views/faqs/index.ejs` | `faqs.test.ts` (blank answer on create and on edit, bodiless create) | `faqs.test.ts` ("already published" `TransitionError` on the create form) |
| "Publish as FAQ" from a message thread, `seller/views/messages/show.ejs` | `faqs.test.ts` (blank answer, re-renders the thread page, not the FAQ index) | covered by the same thread-origin test |
| Seller ship form, `seller/views/orders/show.ejs` | `orders.test.ts` (blank carrier and tracking number, values of the other field kept) | `orders.test.ts` ("already shipped" `TransitionError`) |
| Seller decline form, same page | `orders.test.ts` (blank reason) | `orders.test.ts` (declining after shipping) |
| Seller message reply, `seller/views/messages/show.ejs` | `messages.test.ts` (blank/bodiless reply) | none reachable — the only `TransitionError` `postMessage` can raise for a seller (blocked sender) never applies to a seller |
| Storefront message reply, `shop/views/message-thread.ejs` | `messages.test.ts` (blank/bodiless reply) | `messages.test.ts` (blocked customer — but see the note below: the form itself is gone by then) |
| Admin message reply, `admin/views/message.ejs` | `messages.test.ts` (blank and bodiless) | none reachable, same reason as the seller side |
| Ask-a-question box, `shop/views/listing.ejs` | `messages.test.ts` (bodiless) | `messages.test.ts` (blocked customer) |
| Add-to-cart quantity, same page | `carts.test.ts` (not a number, over what remains in stock — value kept) | `carts.test.ts` (sold out) |
| Checkout, `shop/views/checkout.ejs` | `checkout.test.ts` (blank city, bad email, bodiless) | `rate-limit.test.ts` (the `checkout` trip, since no other domain refusal on this form is field-less) |
| Pay, `shop/views/pay.ejs` | not converted — see decision below | wired for the `payment_attempt` trip only (`rate-limit.test.ts`) |

**Button-only forms left alone** (no typed field, so nothing to convert), confirmed by reading
every `<form method="post">` in the three sites: logout (×3), favorite, mark-notification-read
(×2), drain outbox, remove cart line, cancel order, the pay button that opens `/orders/:id/pay`,
message-seller/message-customer open buttons, unpublish FAQ, lift block, lift removal, and every
status-transition button.

### The two items called out in the brief

1. **`fieldView`** — deleted; see above.
2. **`decline-notice.ejs`** — kept as its own partial, not folded into `form-error.ejs`. It is not
   a submission re-render: `POST /orders/:id/pay` always redirects (to the order page on success or
   on decline alike — the order's own status tells the two apart), and the decline reason is read
   back from the last `Payment` row on the next `GET`, on both `/orders/:id` and `/orders/:id/pay`.
   `form-error.ejs`'s contract is specifically a 422 re-render carrying that submission's kept
   values; `decline-notice.ejs` is a persisted status banner shown on a plain `GET`, with no
   submission behind it to keep. Restructuring the redirect-based payment flow to fit the
   re-render contract was out of scope for a card form that has no field to attach an error to
   in the first place (a decline is a whole-attempt refusal, never one field's).

### Parsers normalized

- **`parseCheckoutForm`** (`core/shop/checkout-form.ts`): `errors` was `readonly string[]` of
  field *names*; now `Partial<Record<'email' | keyof ShippingAddress, string>>` of messages, with
  the same "Enter the X." wording the other parsers use. `missingAddressParts` (`core/orders/
  shipping-address.ts`) is now typed by its real return type (`Exclude<keyof ShippingAddress,
  'line2'>[]`, not `string[]`) so the new indexing type-checks. `checkout-fields.ts`'s
  `missingFieldLabels` — the function this replaced — is deleted, along with its test.
- **`parseRefundReason`** (`core/orders/refund.ts`): `error: string` → `errors: Partial<Record<
  'reason', string>>`. Cheap, so done regardless of the admin cut below; the two admin call sites
  (`admin/routes/orders.ts`, `admin/routes/fulfillments.ts`) were updated only enough to compile
  (`Object.values(reason.errors)[0]` in place of `reason.error`) since their forms were not
  converted this pass.
- **`parseListingDraft` / `parseFaqDraft` / `parseShipmentDetails`** already matched the target
  shape — untouched.
- Added **`parseCartQuantity`** (`core/shop/cart-quantity.ts`) — the add-to-cart quantity had no
  parser at all before this ticket, just a Zod `z.coerce.number().int().min(1)` that 400'd on
  anything it couldn't coerce. The schema is now a passthrough (`z.string().optional()`) and the
  whole number / 1-to-stock-on-hand decision moved into this pure function, the same "parse, don't
  validate" shape as the other four.

### `onTrip` wiring (the 429 re-render)

Wired for every form-guarded route the ticket named: `POST /login` on all three sites (already
landed), `listing_write` (`seller/routes/listings.ts` — both create and update needed their own
`rateLimitGuard` instance, typed by their different route params), every site's `message_post`
(seller and admin thread replies; the storefront's thread reply *and* its ask-a-question box,
which needed a second, separately-typed guard instance since it shares the `message_post` limit
with the thread route but sits behind a different param shape), `payment_attempt`
(`shop/routes/order-payments.ts`), and `checkout`'s own guard. `GET /support`'s `conversation_open`
trip is confirmed still answering the generic page (`rate-limit.test.ts`, unchanged).

**Left unwired**: the ask-a-question route's *other* guard, `conversation_open` (it runs before
`message_post` in that route's `preHandler` array and has no `onTrip` of its own — a
`conversation_open` trip there, rarer than the `message_post` limit behind it, still falls through
to the generic page). Recorded as gap 11 in `docs/review.md` (rewritten — it used to say no route
had `onTrip` wiring at all, which is now false).

### Cut

**The admin site's five forms** — `views/customer.ejs` (block reason), `views/listing.ejs`
(removal kind + reason), `views/order.ejs` (cancel reason, inline refund amount),
`views/fulfillment.ejs` (refund reason), `views/payouts.ejs` (threshold) — are unconverted, per
the ticket's own stated cut line. Each still does `reply.setFlash({ alert }); redirect(...)`.
Recorded as gap 13 in `docs/review.md`'s known-gaps list, with a matching "suggested next steps"
entry.

### A design note worth a reviewer's attention

Two `TransitionError` catch branches — the seller message reply's and the storefront message
reply's, both for `postMessage`'s "blocked" refusal — pass `formError` into a re-render whose
template hides the very form that error belongs to, because the same "blocked" state that raised
the error also flips `thread.mayPost` false, and the template swaps the form for a `role="status"`
paragraph when that happens. The formError is computed but never rendered in that branch; the
`role="status"` paragraph carries the same information through the page's existing structure
instead. Left as is (the code is still correct, just visibly redundant in that one branch) rather
than special-cased, since a future `TransitionError` from `postMessage` that does *not* flip
`mayPost` (none exists today, but the catch is written generally) would still need it. The cart
and order-ship equivalents of this same shape (`isPurchasable`, `canShip`/`canDecline` flipping
false along with the refusal) were handled by moving the `form-error.ejs` include outside the
gate that would otherwise hide it — the same fix was not available here because there is no
non-form fallback markup on the FAQ/listing pages the way there is on the order-show and listing
pages; `message-thread.ejs`'s fallback already exists and already carries the message, so nothing
further was needed there.

### Deviations from the contract

- `form-field.ejs` gained `readonly` and `placeholder` locals (not in the original set the
  previous worker documented) — needed for checkout's read-only verified email and to preserve
  the reply/question boxes' placeholder copy without dropping back to hand-rolled markup.
- Message-reply `TransitionError`s are field-less by the doctrine's own rule ("a refused domain
  rule with no field renders in the one shared form-error.ejs slot"), but `messageBodyError`
  (`core/messaging/message-body.ts`) — a blank or over-length body — was pulled out of that
  generic catch and checked directly in each route before calling `postMessage`, so it attaches to
  the `body` field instead of falling into the form-level slot. `postMessage` still re-checks it
  internally (unreachable now from any of the three message routes, left as defense in depth).

### `make check`

Green throughout, committed in batches as each went green. Coverage moved from 99.50/96.74/99.56
(lines/branches/functions) at the `b251bb8` baseline to 99.4x/9x.x/99.x by the last batch — see the
final commit's own coverage report for the exact figures; the branch-coverage dip is dead branches
removed (the `fieldView` deletion), a few `TransitionError` catches that are no longer reachable
from their own route now that `messageBodyError` is checked ahead of them (seller and storefront
message replies), and three `onTrip` re-render callbacks this ticket wired but left untested
(the seller and admin thread-reply trips, and the ask-a-question box's trip) — all three now
documented above rather than chased for their own sake.

### Fix-up

Two IMPRV-012 review gaps closed as tests-only follow-up, no shipped behaviour touched:

- Added `src/app/core/shop/cart-quantity.test.ts`, the dedicated literal-input unit test
  `parseCartQuantity` was missing — a blank/absent input, a non-numeric string, zero, a negative, a
  non-integer, one, a mid-range value, a whitespace-padded value, exactly the stock on hand, one
  over it, and an error message that names the real stock figure rather than a fixed number.
- Added the three untested `onTrip` trip tests to `src/app/plugins/rate-limit.test.ts`: the seller
  thread-reply trip (`seller/routes/messages.ts`), the admin thread-reply trip
  (`admin/routes/messages.ts`), and the ask-a-question box's trip
  (`shop/routes/messages.ts`). Each drives `message_post` to its trip, then asserts 429,
  `data-form-error` with the "Too many requests" sentence, the submitted body kept in the
  re-rendered form, and no message row (or, for the question box, no conversation row) written.

No test revealed a behavioural bug; `make check` stayed green throughout (1906 tests, up from 1891;
coverage 99.42/95.94/99.49 lines/branches/functions).

Left in `work/2-doing/`, not journaled or closed — that is the lane orchestrator's call after
review.
