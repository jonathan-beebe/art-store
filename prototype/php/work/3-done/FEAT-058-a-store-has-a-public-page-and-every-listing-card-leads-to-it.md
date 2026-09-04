---
id: FEAT-058
type: feature
status: resolved
created: 2026-09-03
---

# FEAT-058: A store has a public page and every listing card leads to it

## Problem
The storefront has listing pages (`/art/{slug}`) and no seller pages. A listing card shows the seller's display name as plain text; a buyer who loves one piece cannot see the maker's other work or read who they are.

## Goal
A buyer can open any seller's store page from a listing and see who they are, their story, and everything they have for sale.

## Outcome
- `GET /s/{slug}` renders the store in the Warm Craft theme: cover, portrait, name, tagline, location, "N pieces for sale · selling since", the sections in order, the links, and the seller's storefront listings (for sale and sold, never draft or archived, never removed) in the storefront's grid.
- A retired address younger than thirty days redirects (301) to the current one; older ones and unknown ones answer 404. A hidden store answers 404 to everyone but its seller (who sees a "hidden" banner).
- Every listing card and listing page names the seller as a link to the store page when the store is published; a seller without a published store keeps the plain name.
- A store page view records `store.view` in the analytics store with the store as subject, deduplicated per (store, customer, UTC hour) like listing views, and the admin analytics event list shows it.
- The page has a title, a description, and an Open Graph image from the cover. `make precommit` green; `make check` green before the PR.

## Why it matters
A seller writes their story so buyers read it. Until the page is public and reachable from the pieces, the profile is a form nobody sees; the platform's promise (buy from a person) is finally visible on the storefront.

## Discovery notes
- The public view is the same component FEAT-057 renders as the seller's preview; only the layout shell (`x-layouts.shop`) and the listing grid differ. `x-listing-card` is the storefront's card.
- Slug resolution: profile by `slug`, then `store_slugs` where `retired_at` within thirty days, in one small query class; a middleware or the controller decides between redirect and 404.
- Analytics: `AnalyticsEventName` is a closed enum; add `store.view` with its label, verb, and icon, and a dedupe key shaped like `listing.view`'s (see `docs/analytics.md` and the recorder). Update `docs/alignment.md` §2.6 in MAINT-008, but the PHP row can land here.
- Seeded activity (`seed:activity`) may record store views so the admin pages have data; optional.

## Related work
- FEAT-057
- FEAT-039 (analytics store), FEAT-044..048 (admin analytics)
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Store preview column)

## Working

One commit off FEAT-057 on `php/sp-store`.

**Routes.** `GET /s/{slug}` (`shop.store`), inside the storefront's
`customer.identity` group so the view records with a session behind it. The
slug is read as a string rather than bound to a model: an address the store
left behind names no `store_profiles` row and still has to redirect.

**Resolution.** `App\Support\Store\StoreAddressLookup` — one query for the
current address, and only on a miss a second for the history.
`App\Domain\Store\RetiredSlugWindow` holds the thirty-day rule. A hidden
store, an address retired too long ago, and an address no store ever held
all answer the same 404; the store's own seller sees the page with a
banner.

**Analytics.** `AnalyticsEventName::StoreView` with its label, plural,
verb, and icon; `AnalyticsEvent::forStore()` with `subject_type = 'store'`;
`App\Domain\Store\StoreViewCollapse` for the hour window.
`EntityActivity` gained a `store` branch so an actor's feed names the store
unlinked, the way it already names a cart, rather than falling through to
"listing no longer exists".

**Decisions taken.**

- The seller's own preview of a hidden page records nothing. A published
  page records on every render, collapsed by the dedupe key.
- `x-layouts.shop` gained `description` and `image` props rather than a
  meta partial; a page passing neither renders neither tag.
- The store link reads `$listing->seller->storeProfile`, so every query
  that feeds a card eager loads `seller.storeProfile` (ten call sites).
  `Model::shouldBeStrict()` turns a missed one into a lazy-loading
  violation outside production, so a missed site fails loudly rather than
  running an N+1.

**Tried and reverted.** A controller test asserting the hour collapse
across two `$this->get()` calls: each test request gets its own session and
so its own anonymous customer, which is two different dedupe keys and two
rows — correct behavior, a wrong assertion. The collapse is pinned in
`StoreViewCollapseTest` and by a case recording three events through
`Analytics` under one key.

**Found and left.**

- `EventTotalsTest` asserts the event-name order; `store.view` slots in
  before the synthetic `page.view` row. Updated.
- `ListingQuestionControllerTest` asserts `assertSee('Made by Rye Press')`;
  the seller line has to stay on one source line for the string to be
  contiguous in the rendered HTML. The markup keeps it inline.
- The `store` subject has no admin page of its own, so its feed row is
  unlinked. An admin store page is not in any ticket.

**Gate.** `make precommit` green: 4295 tests, 33864 assertions, Pint and
PHPStan clean.

### Review pass

Six findings on this ticket, all fixed on the same branch.

- **Disclosure: a hidden store's old address named where it lives.**
  `StoreAddressLookup::movedTo()` never looked at `published_at`, so
  renaming and then hiding left the old address answering 301 to the new
  slug. The redirect target resolves through published profiles only, so a
  hidden target yields null and the controller 404s. Covered at both the
  query and the route.
- **`StoreFacts::pieceCount` counted sold work.** The sentence says "N
  pieces for sale" and the count used `onStorefront()` (for sale and
  sold). It is `forSale()` now, with a case pinning that a sold piece is
  left out. `StoreFactsTest` pinned the wrong number and was corrected.
- **`StoreFacts::of()` lazy-loaded `seller`**, which is a violation
  outside production. It calls `loadMissing('seller')`, and both page-data
  builders load `seller` up front.
- **The maker-link rule lived in two Blade files.** It is
  `StoreProfile::publicUrl()` — null while the store is hidden — and both
  the card and the listing page call it.
- **`EntityActivity`'s store branch had no test.** An actor-feed case now
  pins a `store.view` row: label `store {id}`, kind `store`, unlinked, no
  listing titles.
- **The Open Graph tags reached every storefront page.** They belong to
  this feature, so `x-layouts.shop` emits the group only for a page that
  passes `description` or `image`; a case asserts the storefront home
  carries none. Keeping them site-wide was the alternative and would have
  changed pages no ticket here owns.

Prose: the three "rather than" clauses in this ticket's files state the
positive fact now.

**Gate after the review pass.** `make check` green.
