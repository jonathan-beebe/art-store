---
id: DSGN-008
type: design
status: resolved
created: 2026-08-31
---

# DSGN-008: the design system is audited, accurate, and complete

## Problem

`/design-system` (`resources/views/shop/design-system/` — atoms, colors,
typography, components, layouts, mobile, relationships, specimens/) is the
living reference for the Warm Craft language, and the storefront has moved
under it: DSGN-007's home redesign (featured band, golden-ratio tile,
media tile row with drawer and gallery panel, three-then-nine listing
sets, wayfinding footer), the browse-media sheet, and newer tokens such as
`photo-scrim` (commit 4aaf8d9). Spot checks show much of this is captured,
but no systematic pass has verified the reference against the shipped
product since those changes landed — its accuracy and completeness are
currently unaudited, not known-good.

## Goal

The design system is verified against the shipped storefront and stands as
a known-accurate, complete reference.

## Outcome

A full sweep of the shipped storefront, seller, and admin surfaces against
every `/design-system` page has been performed and its findings resolved:
every component and token the product wears has a live, matching entry or
specimen; no design-system section demonstrates a look the product no
longer wears; and the audit's scope and result are recorded in the
ticket's Working notes so "accurate and complete" has a dated basis
rather than an assumption.

## Why it matters

A living reference is only worth consulting while it is trusted; an
unaudited one silently decays until new work copies stale patterns.
Verifying it now — right after a heavy design push — is the cheap moment,
while the changes and their authors' intent are fresh.

## Discovery notes

Advisory.

- The `design-system-sweep` skill exists for exactly this: sweeping
  project components not defined in the design system and adding them.
- Walk the rendered pages, not just the templates — a stale cached copy
  already caused one false alarm here; audit against the live server
  (`make up`, port 8000).
- Candidate check-list from a grep pass (verify visually, not
  authoritative): media-gallery-panel, `x-ui.section-header`/`x-ui.chip`,
  the wayfinding footer, the home page anatomy on the layouts page, the
  `--ui-photo-scrim` token on the colors page.
- Cheap cleanup while in there:
  `resources/views/components/tile.blade.php:12`'s comment cites
  "(DSGN-007)" — the house rule says code never references tickets.

## Related work

- DSGN-007 (commit fb83ab4) — the home redesign, the largest recent shift
- Commit 0004902 — the theme system and the design-system page's origin
- Commit 4aaf8d9 — the photo-scrim token

## Working

- 2026-08-31 — re-validated: no systematic pass has run since DSGN-007
  landed; the reference at `/design-system` renders live (HTTP 200 on the
  dev server). The audit applies.
- Scope decisions:
  - The `design-system-sweep` skill describes a `src/components/design-system`
    layout from another stack; this prototype's reference lives at
    `resources/views/shop/design-system/`. The ticket's Outcome governs the
    audit; the skill's extract-and-document intent carries over.
  - Audit runs against the rendered pages on the live server (curl), with
    template reads as the fallback for surfaces behind sign-in; the scope
    notes record which was which.
  - Two audit lanes: storefront vs. the reference, and seller + admin vs.
    the reference, each checking both directions (product feature missing
    from the reference; reference demonstrating a look the product no
    longer wears).
- Audit scope, 2026-08-31, against the live server on :8000:
  - Storefront, rendered: `/design-system` (all seven sections, four
    specimens), `/`, `/medium/ceramic`, `/browse/jewelry`,
    `/browse/jewelry/rings`, a plain and a configurator listing page,
    `/search?q=print`, `/cart`, `/favorites`, `/orders`, a rendered support
    thread. Templates only (need a signed-in customer with cart/order
    state): checkout, pay, order, account.
  - Seller and admin, rendered with live magic-link sessions
    (`MAGIC_LINK_DELIVERY=session`): seller dashboard, earnings, the full
    listing-editing surface, orders, messages, notifications; admin
    dashboard, all six list/show panes, accounting, ledger, payouts,
    stats, messages, logs.
  - Token cross-check: every `var(--ui-*)` in `app.css` has a
    `config/theme.php` registry entry and vice versa — no orphans.
- Findings — storefront (resolved this ticket unless noted):
  1. Layouts section lacked the home page's anatomy (featured band →
     media tile row with drawer/sheet → three-up set → category grid →
     nine-up set → wayfinding footer). Fixed: documented as a third shape.
  2. Browse wireframe caption claimed a 1-up → 2-up → 3-up progression;
     the shared listing grid is 2-up → 3-up. Fixed.
  3. Browse wireframe depicted `/medium/{medium}`'s shape while labeled
     "browse"; `/browse/{categoryPath}` has no tile row. Fixed: relabeled,
     category variant covered.
  4. Mobile section and the buy-bar/swipe-gallery/cover-rail specimens
     presented unshipped patterns as shipped; git history (0004902,
     "mobile picker explorations") shows they were explorations from the
     start, so they are relabeled as such rather than deleted; the
     browse sheet (media-gallery-panel) is the shipped pattern. The
     orphaned `partials/media-cover-rail.blade.php` stays, reachable via
     its specimen.
  5. Relationships pairings omitted `on-photo` on `photo-scrim`, the tile
     captions' combination. Fixed (rated honestly or noted, per what the
     translucent scrim admits).
  6. `x-card-fields` and `x-order-item-detail` (checkout/pay/order) had
     no design-system presence. Fixed: live demos added.
  7. Follow-up, unfixed: `browse.blade.php`'s subcategory pill hand-rolls
     a chip-like affordance that differs from `x-ui.chip` in padding,
     border, weight, and hover; the storefront wears two chips. Unifying
     changes shipped look — out of this audit's scope.
  8. Note: the components page demos `x-ui.section-header`'s link variant,
     which no shipped page uses; real API surface, left as is.
- Findings — seller and admin:
  - The reference scopes itself to the storefront in every section's own
    prose; seller/admin absence is a documented exclusion. Accuracy holds
    trivially — the reference makes no seller/admin claims.
  - Follow-up, unfixed: seller and admin consume zero Warm Craft tokens —
    raw Tailwind grays with `dark:` pairs across 51 files, `font-sans`
    over the theme stack, and the admin's `ok/warn/bad` badge vocabulary
    parallel to the storefront's `danger/success/notice`. Their color
    pairs sit outside the relationships page's rated WCAG guarantee.
    Tokenizing those surfaces is its own piece of work.
  - `config/theme.php`'s header claimed "views only ever say `bg-canvas`"
    unqualified; scoped to the storefront so it states what is true.
- Resolution, 2026-08-31: all fixes rendered-verified on the live server.
  The photo-scrim pairing is rated via `Contrast::compositeOver()` against
  the scrim's 0.72 alpha composited over white — the lightest ground a
  photograph can present, so the shipped ratio (7.2, AA, both modes) is a
  floor; the relationships page renders the real gradient with that
  assumption stated. The order-item demo builds an unsaved `OrderItem`
  from the configurator specimen's listing. `make check` green: 3219
  tests, 100% line coverage, PHPStan clean (one real PHPStan catch fixed
  in passing: `Contrast::channels()` now casts `hexdec()` to int).
- Validation review: accept. One advisory follow-up, unfixed: the
  order-item preview's `configuration_json` never carries the synthetic
  `Piece` line `PlaceOrder::configurationSnapshot()` appends for a
  serialized unit, and sets no `unit_id` — a serialized configurable
  listing would under-represent a real order line in the demo. Fixing it
  means extracting `PlaceOrder`'s unit-appending into a helper both call.
- Pre-audit finding: the ticket's cheap-cleanup item
  (`components/tile.blade.php:12` citing "(DSGN-007)") is already clean —
  the comment carries no ticket reference. A repo grep shows ticket ids
  (DSGN-002/003/004/006/007) in dozens of other comments across
  `resources/views`, `resources/css/app.css`, and `app/`; that contradicts
  the house rule "code never references tickets" at a scope far past this
  ticket. Recorded here as a finding to surface, left unfixed.
