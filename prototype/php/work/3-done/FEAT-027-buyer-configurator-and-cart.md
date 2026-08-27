---
id: FEAT-027
type: feature
status: resolved
created: 2026-08-26
---

# FEAT-027: Buyer configurator and cart

## Problem
`etsy-product-configuration.md` §2.1–2.2 names two buyer-facing gaps this ticket closes: options priced honestly instead of buried in compound strings the buyer decodes by cross-referencing a dropdown against gallery photos (ring, candlesticks), and a modifier shown regardless of whether it applies to the buyer's selection ("blank mug" still prompting for personalization text). The buyer must always see a fully resolved, itemized price for their current selection, and only the inputs that apply to it — and the app has ~no JS, so this has to work as a server-rendered form.

## Goal
A buyer configures a listing on `/art/{slug}`, sees an exact itemized price from first paint through every choice, and adds a fully-specified line to their cart — with JavaScript off.

## Outcome
- [x] `/art/{slug}` renders the configurator as a GET form: one control per axis (defaults preselected so the page opens with a concrete price), each option showing its signed price delta inline ("+$8.50"); a resubmit-as-GET-query-params pattern for every axis/qty change (matching the "hard project constraint: works with JS off" from the brief) — no control depends on optional progressive-enhancement JS to function.
- [x] Unavailable combinations grey out with a reason, computed from FEAT-025's availability resolution (`enabled ∧ (serialized → any available unit; else quantity NULL or > 0)`).
- [x] Serialized variants render a visual unit picker (photo/label/condition/price per card) in place of a numbered dropdown — this is the direct fix for the candlestick archetype's "buyer assembles unit identity from three places" cost named in the research.
- [x] Modifiers scoped to the current selection appear/disappear via `modifier_scopes` (empty scope = always shown); each priced modifier option and each `measurement` modifier shows its add-on delta inline.
- [x] Quantity input with the quantity-break tier table visibly rendered ("100+ → 12% off") and the discount reflected live in the price panel for the current qty.
- [x] An itemized price panel (base + Σ option surcharges + Σ answer add-ons − quantity discount = total) recomputed server-side on every request for the current selection — this is the same breakdown shape FEAT-028 freezes onto the order.
- [x] Add-to-cart is a POST carrying the axis selection, unit choice (if serialized), and modifier answers; it validates required answers, computes the deterministic fingerprint from FEAT-025, and either creates a new `cart_items` row or merges quantity into an existing line with an identical fingerprint.
- [x] `cart_items` migration adds nullable `variant_id`, nullable `unit_id`, `configuration_json` (axis/value ids + labels), `answers_json`, and `fingerprint`; the unique index moves from `(cart_id, listing_id)` to `(cart_id, listing_id, fingerprint)`. A legacy zero-axis listing gets a constant empty-config fingerprint so its existing one-click add and merge-on-duplicate-add behavior is unchanged.
- [x] Cart page renders every configured line with its stored breakdown, and legacy lines exactly as today; a configured line's price re-resolves live against current listing/variant state before checkout (not just at add-time).
- [x] Legacy zero-axis listings keep the existing one-click "Add to cart" button — no configurator page inserted where there is nothing to configure.
- [x] HTTP feature tests: full walk per non-legacy archetype from FEAT-025's seeds (at minimum the ring, the mug's scoped modifier disappearing on the blank option, the tee's size-tier deltas, the candlestick unit picker, the wedding quantity breaks) plus the legacy one-click path unchanged; sidecar test per new class; `make check` green; coverage 100%.
- [x] `prototype/php/work/journal.md` updated: FEAT-027 defined/started/done lines.

## Why it matters
This is the buyer-side half of the design's core claim — "the price on screen is the price at checkout" and "inputs appear only when they apply" — validated against the exact failure modes (blank-mug modifier bleed-through, unit-picker indirection) the research observed sellers patching with prose.

## Discovery notes
- Read `prototype/php/docs/architecture.md` §"Sites" (storefront theme, bright/open) and the note that every page works with JavaScript off — a single ~20-line script exists today only as progressive enhancement over the unread badge; this ticket's configurator must fully function without it.
- `App\Http\Controllers\Shop\ListingController` (storefront listing page) and the existing `CartController::add`/`AddToCart` action are the shapes to extend, not replace; the design's GET-param resubmission pattern is new to this codebase — build it as query-string-driven controller logic, not a client-side state machine.
- Cart unique-index risk (flagged explicitly by the brief): today's `(cart_id, listing_id)` unique index assumes one row per listing per cart. Existing `CartTest`/`CartControllerTest`/checkout tests that assert on that uniqueness will need updating to the new `(cart_id, listing_id, fingerprint)` shape — check `app/Models/CartTest.php` and `app/Actions/Cart/*Test.php` before changing the migration, since a stale assumption there will fail silently rather than loudly if the fingerprint column defaults let two rows collide.
- `docs/alignment.md` §2.3: buyer writes here ride `cart.add`/`cart.update` — no new event name — and the storefront's existing rate limits are unaffected (no new limit; `listing_write` is seller-only).
- Money stays integer cents (`App\Domain\Money\Money`), matching the rest of the domain.
- Policy/ownership: cart lines belong to the visitor resolved by `ResolveCustomerIdentity` (`ShopController::authorizeVisitor()` / `@visitorCan`), the same as every other storefront write — no new pattern needed here, just extended to the new fields.
- Blade `<x-form.field>` components, Tailwind v4, per the existing storefront views.

## Working

`/art/{slug}` renders one `<form>` per listing: a `<select>` per option axis
(defaults resolved server-side via `AxisSelectionResolver`, greyed
`<option>`s carry a `not offered`/`out of stock` reason via
`OptionAvailability`), a card-grid `<input type="radio">` unit picker for a
serialized variant (sold units excluded), a control per in-scope modifier
(`Modifier::appliesTo()` gates visibility), the quantity-break table, and an
itemized price panel built by `ConfigurationPricer`/`PriceBreakdownAssembler`
from the resolved `PriceBreakdown`. One submit button overrides
`formmethod="GET"`/`formaction` back to the listing page ("Update options");
the other posts to `POST /cart/{listing}` ("Add to cart") — no JS, matching
the two-button-one-form pattern already used for the legacy path.

`App\Support\Configurator\ConfiguratorPageResolver` is the one adapter both
`ListingController`, `ListingQuestionController`'s rate-limit re-render, and
`AddToCartRequest` call — it folds a listing's axes/variants/units/modifiers
against a `ConfiguratorInput` (raw query or POST fields, transport-agnostic)
into a `ListingConfiguration`: the per-axis/option grey-outs, the unit list,
the resolved modifier answers, the quantity tiers, the price breakdown, and
the configuration/answers/fingerprint snapshot `AddToCart` persists.
`AddToCartRequest::rules()` requires an answer only for a modifier that both
applies to the current selection and is `required`, and (when the matched
variant is serialized) a `unit` naming one of its available units.

`cart_items` (rewritten in place, not a new migration) gains nullable
`variant_id`/`unit_id`, `configuration_json`, `answers_json`, and a
non-nullable `fingerprint`; the unique index is now `(cart_id, listing_id,
fingerprint)`. `AddToCart` takes a `listingHasVariants` flag distinct from
"has a matched variant" — a listing with variant rows but no variant for the
current combination (the table's sparse "not offered" cell) is refused via
`ConfiguredCartQuantity`, rather than silently falling back to the
legacy, variant-free stock check a listing with only a modifier (no axes, no
variants at all) still correctly uses. `CartLine` gained
`ofBreakdownTotal()` so a configured line's cart-page and checkout-page total
is the same live-resolved `PriceBreakdown` total the price panel showed, not
a naive `unitPrice * quantity`. `CartItem::currentBreakdown()` /
`currentAvailability()` re-resolve a stored line against the live variant on
every render. `MergeAnonymousCustomer`'s cart fold now groups by
`(listingId, fingerprint)` instead of `listingId` alone, carrying
variant/unit/configuration/answers through the fold so a configured line
survives a customer merge instead of crashing on the new non-nullable
`fingerprint` column.

### Numbers

2173 tests, 6261 assertions, 100% lines. `make check` (lint, assets,
coverage) green. Manually walked all eight archetypes plus add-to-cart and
the cart page over HTTP after `make fresh`.

### Deviations

- Hand-rolled Blade markup for the axis/unit/modifier controls instead of
  `<x-form.field>` — the component's single label+input+error shape doesn't
  fit a `<select>` needing per-option `disabled`/delta text, or the
  card-grid radio unit picker; `<x-form.field>` stays the fit for the
  quantity input alone, but consistency argued for one hand-rolled block.
- `DELETE /cart/{listing}` (`RemoveFromCart`) still removes every line for
  that listing, not one fingerprint — pre-existing route/action shape the
  ticket did not ask to change. A cart can now legitimately hold two
  configurations of the same listing (proven by a `CartControllerTest`
  walk); removing one currently takes both. Worth a ticket of its own if a
  buyer configuring the same listing twice becomes a real path.
- `PlaceOrder`/`OrderItem` still snapshot `listing->price_cents` untouched —
  explicitly FEAT-028's job per this ticket's own framing. `CartTotals`
  (and so `order.total_cents`) already reflects a configured line's real
  price via `CartLine::ofBreakdownTotal()`, so if a configured line reaches
  checkout before FEAT-028 lands, the order total and the sum of its
  (unconfigured-priced) `order_items` would disagree — flagged for FEAT-028,
  not fixed here.

No contradiction found with `docs/item-configurator.md`; §3's formula,
§5's flow, and §10's platform notes matched the implementation as designed.

## Related work
- FEAT-025 (data model, domain pricing, fingerprint function this ticket calls)
- FEAT-026 (seller configurator UI that populates what this ticket renders)
- FEAT-028 (checkout + order snapshot; freezes what this ticket computes live)
- `__local__/item-configuration/etsy-product-configuration.md`
- `__local__/item-configuration/etsy-product-configuration-design-doc.md`
- `docs/alignment.md` §2 (logging), §1 (ids)
