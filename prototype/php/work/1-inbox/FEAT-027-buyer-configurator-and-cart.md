---
id: FEAT-027
type: feature
status: open
created: 2026-08-26
---

# FEAT-027: Buyer configurator and cart

## Problem
`etsy-product-configuration.md` §2.1–2.2 names two buyer-facing gaps this ticket closes: options priced honestly instead of buried in compound strings the buyer decodes by cross-referencing a dropdown against gallery photos (ring, candlesticks), and a modifier shown regardless of whether it applies to the buyer's selection ("blank mug" still prompting for personalization text). The buyer must always see a fully resolved, itemized price for their current selection, and only the inputs that apply to it — and the app has ~no JS, so this has to work as a server-rendered form.

## Goal
A buyer configures a listing on `/art/{slug}`, sees an exact itemized price from first paint through every choice, and adds a fully-specified line to their cart — with JavaScript off.

## Outcome
- [ ] `/art/{slug}` renders the configurator as a GET form: one control per axis (defaults preselected so the page opens with a concrete price), each option showing its signed price delta inline ("+$8.50"); a resubmit-as-GET-query-params pattern for every axis/qty change (matching the "hard project constraint: works with JS off" from the brief) — no control depends on optional progressive-enhancement JS to function.
- [ ] Unavailable combinations grey out with a reason, computed from FEAT-025's availability resolution (`enabled ∧ (serialized → any available unit; else quantity NULL or > 0)`).
- [ ] Serialized variants render a visual unit picker (photo/label/condition/price per card) in place of a numbered dropdown — this is the direct fix for the candlestick archetype's "buyer assembles unit identity from three places" cost named in the research.
- [ ] Modifiers scoped to the current selection appear/disappear via `modifier_scopes` (empty scope = always shown); each priced modifier option and each `measurement` modifier shows its add-on delta inline.
- [ ] Quantity input with the quantity-break tier table visibly rendered ("100+ → 12% off") and the discount reflected live in the price panel for the current qty.
- [ ] An itemized price panel (base + Σ option surcharges + Σ answer add-ons − quantity discount = total) recomputed server-side on every request for the current selection — this is the same breakdown shape FEAT-028 freezes onto the order.
- [ ] Add-to-cart is a POST carrying the axis selection, unit choice (if serialized), and modifier answers; it validates required answers, computes the deterministic fingerprint from FEAT-025, and either creates a new `cart_items` row or merges quantity into an existing line with an identical fingerprint.
- [ ] `cart_items` migration adds nullable `variant_id`, nullable `unit_id`, `configuration_json` (axis/value ids + labels), `answers_json`, and `fingerprint`; the unique index moves from `(cart_id, listing_id)` to `(cart_id, listing_id, fingerprint)`. A legacy zero-axis listing gets a constant empty-config fingerprint so its existing one-click add and merge-on-duplicate-add behavior is unchanged.
- [ ] Cart page renders every configured line with its stored breakdown, and legacy lines exactly as today; a configured line's price re-resolves live against current listing/variant state before checkout (not just at add-time).
- [ ] Legacy zero-axis listings keep the existing one-click "Add to cart" button — no configurator page inserted where there is nothing to configure.
- [ ] HTTP feature tests: full walk per non-legacy archetype from FEAT-025's seeds (at minimum the ring, the mug's scoped modifier disappearing on the blank option, the tee's size-tier deltas, the candlestick unit picker, the wedding quantity breaks) plus the legacy one-click path unchanged; sidecar test per new class; `make check` green; coverage 100%.
- [ ] `prototype/php/work/journal.md` updated: FEAT-027 defined/started/done lines.

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

## Related work
- FEAT-025 (data model, domain pricing, fingerprint function this ticket calls)
- FEAT-026 (seller configurator UI that populates what this ticket renders)
- FEAT-028 (checkout + order snapshot; freezes what this ticket computes live)
- `__local__/item-configuration/etsy-product-configuration.md`
- `__local__/item-configuration/etsy-product-configuration-design-doc.md`
- `docs/alignment.md` §2 (logging), §1 (ids)
