---
id: FEAT-057
type: feature
status: in-progress
created: 2026-09-03
---

# FEAT-057: A seller builds their store profile from typed sections

## Problem
A seller is a name and a shop name (`sellers.name`, `sellers.shop_name`; `Seller::displayName()`). There is no store page, no slug, no story, no pictures of the studio, and nothing on a listing card that leads to the person who made the piece.

## Goal
A seller can shape how their store presents on the site — name, address, tagline, pictures, story — and the platform can add new kinds of store content later without reshaping the table every time.

## Outcome
- Store, second in the left rail, is a form beside a live buyer preview: store name; store address as a slug under a fixed prefix (lowercase letters, digits, hyphens; 3–60 characters; unique across stores); tagline (80 characters); where you make things; a portrait and a cover picture; ordered sections; links (website, Instagram); visibility (published or hidden).
- Two section kinds ship: a story (heading optional, body up to 4,000 characters) and a gallery (up to eight of the store's pictures in an order). A seller adds, reorders, edits, and removes sections; the form refuses fields a kind does not use.
- Renaming the address keeps every earlier address the store answered to, with the date it was retired.
- Saving without a name or with an address another store uses fails with a field message; a seller can only edit their own store (404 otherwise).
- The seed gives every seeded seller a published store with a story and a gallery from their seeded photos, Harry Potter copy only.
- `docs/seller-portal.md` § "Store profile" describes the tables and the section rule. `make precommit` green; `make check` green before the PR.

## Why it matters
Buyers on this platform buy from a person. A store page is the first thing the brief lists that does not exist today, and the owner expects it to change a lot as it ships; a small profile row plus typed, ordered sections lets the page grow by adding a kind and a renderer, keeps every column indexed and validated, and never hides content in a JSON blob.

## Discovery notes
- Schema (accepted, `__local__/design/seller-portal/ARCHITECTURE.md` §3): `store_profiles` (`sto_`, seller_id unique, slug unique, name, tagline, location, portrait_image_id, cover_image_id, published_at), `store_slugs` (`ssl_`, store_profile_id, slug unique, retired_at nullable), `store_images` (`sim_`, store_profile_id, seller_id, path, alt), `store_sections` (`sse_`, store_profile_id, kind, position unique per profile, heading, body), `store_section_images` (`ssi_`, section, image, position unique per section), `store_links` (`slk_`, store_profile_id, kind, url, position unique per profile). Prefixed ULIDs from the frozen clock.
- Domain under `App\Domain\Store`: `StoreSlug` (normalize from a name, validate), `StoreSectionKind` with `allows(field)`, `StoreLinkKind`. A FormRequest reads `allows()` so the edge refuses what a kind does not use.
- The slug rename is one `App\Actions\RenameStoreSlug` transaction: retire the current `store_slugs` row, insert the new one, update the profile.
- Pictures can reuse the listing image upload path (`ListingImageController`, `ListingImageSeeder`, `App\Support\PlaceholderImage`) and the same storage disk; the seeded portrait and cover can be two of the seller's listing photos copied into `store_images`.
- The preview beside the form renders in the Warm Craft tokens (`config/theme.php`, `x-theme-css`) the way `x-seller.buyer-view` previews a listing; FEAT-058 turns the same view into the public page, so build the preview as the component the storefront will render.
- Form layout: the Tailwind Plus stacked form with a 220px label column per section; the canvas Store artboard has the exact copy and hints.

## Related work
- FEAT-058 (public store page)
- FEAT-025..029 (item configurator — image upload and the buyer preview idiom)
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Store)
