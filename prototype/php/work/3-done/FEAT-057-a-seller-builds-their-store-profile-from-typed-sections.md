---
id: FEAT-057
type: feature
status: resolved
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

## Working

Landed as one commit off `php/seller-portal-next` on `php/sp-store`, plus
two earlier commits for the domain types and the schema.

**Schema.** Six tables, migrations `2026_09_03_000300..000305`.
`store_profiles.portrait_image_id` and `cover_image_id` hold a `sim_` id
with no database foreign key: `store_images` carries `store_profile_id`, so
a key back the other way is a cycle SQLite cannot create in either order.
`RemoveStoreImage` clears both columns before it deletes the row.
`store_slugs.slug` is unique across the whole table (current and retired
alike), so no rename can produce an ambiguous redirect.
`store_section_images` is unique on both `(section, position)` and
`(section, image)`.

**Domain.** `App\Domain\Store`: `StoreSlug` (normalize, validate,
`firstFree`), `StoreSectionKind` with `allows(StoreSectionField)`,
`StoreLinkKind`, `StoreVisibility`, `StorePictureRole`, `StoreSectionMove`,
`StoreDraft`.

**Actions.** `StartStore`, `SaveStore`, `RenameStoreSlug`, `AddStoreSection`,
`SaveStoreSection`, `MoveStoreSection`, `RemoveStoreSection`,
`AddStoreImage`, `RemoveStoreImage`.

**Decisions taken.**

- The store is minted on the first `GET /seller/store`, the shape
  `Customer::cart()` already gives a storefront visitor. The alternative
  considered was a first-run form state that creates the row on the first
  PUT; the cart precedent won because sections and pictures need an id to
  hang on and the route is behind `auth.seller`, so only the seller
  themselves can trigger the write.
- The store writes emit no log event and take no rate limiter.
  `docs/alignment.md` §2.3 closes the event vocabulary ("a write with no
  event above stays silent") and §3 closes the limiter names. Adding
  `store.*` events or a `store_write` limiter is a contract change the
  other two prototypes owe; MAINT-008 can take it. Noted in
  `docs/seller-portal.md`.
- Links are two fields on the store form rather than their own resource:
  one row per kind per profile, synced by `SaveStore`. A link kind the
  seller clears loses its row.
- Gallery placement is a checkbox set on the section form, saved by
  rewriting `store_section_images` rather than patching it, so the order
  the form sent is the order the page renders.
- Seven seeded sellers get a store, not six: `ConfiguratorArchetypeSeeder`
  adds a seventh (George Weasley) and the ticket says "every seeded
  seller".

**Tried and reverted.** Pest `beforeEach` with `$this->seller` /
`$this->profile` instance properties: PHPStan at level max reports
"Access to an undefined property" for every use and the repo carries no
baseline. Rewritten as local variables and top-level closures captured with
`use()`, which is what the rest of the suite already does.

**Found and left.** `DatabaseSeederTest` asserts the seeder count; bumped
11 → 12 for `StoreProfileSeeder`.

**Gate.** `make precommit` green: 4248 tests, 33784 assertions, Pint and
PHPStan clean.

### Review pass

Six findings on this ticket, all fixed on the same branch.

- **The gallery's order was unreachable.** The form was a checkbox set and
  `SaveStoreSection` honoured an order nothing produced. Each picture now
  carries an `order[{image}]` number, `StoreSectionRequest::imageIds()`
  sorts by it, and a picture with no number sorts last. Covered through
  HTTP.
- **A body over 4,000 characters lost the text.** The section forms all
  post the same field names, so errors now land in a bag named for the
  section (`StoreSectionRequest::errorBagFor()`), set in
  `prepareForValidation()`. The page reads that bag beside that section,
  shows `old()` only for the section that failed, and carries `@error`
  blocks for heading, body, images, and order. The textarea lost its
  `maxlength` — the browser was truncating silently where the request has
  a ceiling and a message.
- **`store_images.alt` had no field.** The upload form gained a
  description input; every picture was rendering `alt=""`.
- **`MoveStoreSection::SENTINEL_POSITION` was `-1`** against an
  `unsignedInteger` column, which MySQL and Postgres reject. It is 9999
  now: above `MAX_PER_PROFILE`, inside the column's range, same three-step
  swap.
- **`AddStoreImage` orphaned a file on rollback.** The disk write still
  runs first; a throwing transaction now deletes the path before it
  rethrows. Covered by a case that trips the foreign key.
- **`UpdateStoreRequest`'s bare `url` rule** admitted `data:` and `blob:`,
  which `StoreLink::href()` emitted verbatim. It is `url:http,https`.

Prose: the six "rather than" clauses in this ticket's files state the
positive fact now.

**Gate after the review pass.** `make check` green.
