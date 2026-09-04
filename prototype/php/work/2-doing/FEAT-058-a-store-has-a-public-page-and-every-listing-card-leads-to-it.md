---
id: FEAT-058
type: feature
status: in-progress
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
