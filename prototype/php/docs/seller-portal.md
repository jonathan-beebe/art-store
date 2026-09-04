# The seller portal

## Store profile

A seller presents on the site as a **store**: a name, an address under
`/s/{slug}`, a tagline, where they work, a portrait, a cover, links, and an
ordered list of sections. `GET /seller/store` is the screen; the same
component that renders the preview beside the form is what the storefront
renders as the page.

### The tables

The profile row holds only what every store has once. Everything the page
*says* is a section row of a typed kind, ordered by position.

```mermaid
erDiagram
    sellers ||--o| store_profiles : "presents as"
    store_profiles ||--o{ store_slugs : "has answered to"
    store_profiles ||--o{ store_images : owns
    store_profiles ||--o{ store_sections : "is built from"
    store_sections ||--o{ store_section_images : places
    store_images ||--o{ store_section_images : ""
    store_profiles ||--o{ store_links : ""

    store_profiles {
        text id PK "sto_"
        text seller_id FK "unique"
        text slug "unique"
        text name
        text tagline "nullable, 80"
        text location "nullable"
        text portrait_image_id "nullable, sim_"
        text cover_image_id "nullable, sim_"
        datetime published_at "nullable; null = hidden"
    }
    store_slugs {
        text id PK "ssl_"
        text store_profile_id FK
        text slug "unique across the table"
        datetime retired_at "nullable; null = current"
    }
    store_images {
        text id PK "sim_"
        text store_profile_id FK
        text seller_id FK
        text path
        text alt "nullable"
    }
    store_sections {
        text id PK "sse_"
        text store_profile_id FK
        text kind "story | gallery"
        int position "unique per profile"
        text heading "nullable"
        text body "nullable, 4000"
    }
    store_section_images {
        text id PK "ssi_"
        text store_section_id FK
        text store_image_id FK
        int position "unique per section"
    }
    store_links {
        text id PK "slk_"
        text store_profile_id FK
        text kind "website | instagram"
        text url
        int position "unique per profile"
    }
```

`portrait_image_id` and `cover_image_id` hold a `sim_` id without a database
foreign key: `store_images` carries `store_profile_id`, so a key back the
other way is a cycle SQLite cannot create in either order.
`App\Actions\Store\RemoveStoreImage` clears both columns before it deletes
the row.

### The section rule

`App\Domain\Store\StoreSectionKind::allows(StoreSectionField)` is the one
statement of which fields a kind uses:

| Kind      | Heading | Body | Images |
| --------- | ------- | ---- | ------ |
| `story`   | yes     | yes  | no     |
| `gallery` | yes     | no   | yes    |

`App\Http\Requests\Seller\StoreSectionRequest` reads it twice — once to
decide which fields to validate, once in its after-validation pass to refuse
a field the kind does not use, so a body posted at a gallery is an error
the seller sees.

Every section on the screen posts its own form under the same field names,
so a section's errors go into a bag named for it
(`StoreSectionRequest::errorBagFor()`), and the page reads that bag beside
that section. The section that failed shows the words the seller typed; the
others show what is stored. A gallery numbers its pictures with an
`order[{image}]` field, and `imageIds()` sorts by it — a picture with no
number sorts last.

A new kind of store content is a case here, a renderer in
`resources/views/components/store/profile.blade.php`, and — when it needs
columns no other kind has — a child table keyed by section. It is never a
wider `store_profiles` row and never a JSON blob the database cannot index
or validate.

### Addresses are history

The current address lives on the profile for the unique index and the fast
lookup. Every address the store has ever answered to is a `store_slugs` row;
`retired_at` says when it stopped being current. The column is unique across
the whole table, so a rename can never take an address another store has
ever used and a redirect can never be ambiguous.

`App\Actions\Store\RenameStoreSlug` is the one writer: in one transaction it
stamps the current row retired, brings the new address in as the current
row, and updates the profile. A rename back to an address the store retired
earlier revives that row. A rename to the address the store already holds
writes nothing.

### The routes

| Route                                              | What it does                                    |
| -------------------------------------------------- | ----------------------------------------------- |
| `GET /seller/store`                                | The form and the buyer preview                  |
| `PUT /seller/store`                                | Name, address, tagline, location, links, visibility |
| `POST /seller/store/images`                        | One picture, as the portrait, the cover, or a gallery picture |
| `DELETE /seller/store/images/{image}`              | Takes a picture out of the store                |
| `POST /seller/store/sections`                      | Adds a section of a kind                        |
| `PUT /seller/store/sections/{section}`             | The section's text and, for a gallery, its pictures |
| `DELETE /seller/store/sections/{section}`          | Takes the section off the page                  |
| `POST /seller/store/sections/{section}/reorder`    | Moves it one place up or down                   |

The first `GET /seller/store` mints the store — hidden, named after the shop
— through `App\Actions\Store\StartStore`, the shape
`App\Models\Customer::cart()` already gives a storefront visitor. Every
route answers 404 for another seller's rows (`App\Policies\StoreProfilePolicy`).

### Limits

| Thing                        | Ceiling                                    |
| ---------------------------- | ------------------------------------------ |
| Address                      | 3–60 characters, `[a-z0-9]` with single hyphens |
| Tagline                      | `StoreProfile::MAX_TAGLINE_LENGTH` (80)    |
| Pictures per store           | `StoreProfile::MAX_IMAGES` (24)            |
| Story body                   | `StoreSection::MAX_BODY_LENGTH` (4,000)    |
| Pictures per gallery         | `StoreSection::MAX_GALLERY_IMAGES` (8)     |
| Sections per store           | `StoreSection::MAX_PER_PROFILE` (12)       |

### Seeds

`Database\Seeders\StoreProfileSeeder` gives every seeded seller a published
store: a tagline, where they work, a story, a gallery, and two links. The
picture rows name the same files on the public disk that the seller's
listings already show, so the seed copies nothing.

### What the store does not write

`docs/alignment.md` §2.3 closes the log-event vocabulary and §3 closes the
rate-limit names. Store writes emit neither: there is no `store.*` event and
no store limiter until the contract gains them, so the actions here write
silently; minting a name the other two prototypes lack is what §2.3
forbids.

## The public page

`GET /s/{slug}` renders the store in the Warm Craft theme. It is the same
component the seller previews beside their form
(`resources/views/components/store/profile.blade.php`); only the shell
(`x-layouts.shop`) and the listing grid below it differ.

### Resolving an address

```mermaid
flowchart TB
    A["GET /s/{slug}"] --> B{"a profile holds this slug?"}
    B -- yes --> C{"published, or the seller's own?"}
    C -- yes --> D["render the page"]
    C -- no --> E["404"]
    B -- no --> F{"a store_slugs row retired inside 30 days?"}
    F -- yes --> G["301 to the current address"]
    F -- no --> E
```

`App\Support\Store\StoreAddressLookup` is the query;
`App\Domain\Store\RetiredSlugWindow` is the thirty-day rule. The redirect
target is resolved through published profiles only, so an old address of a
store that has since been hidden answers 404 and never names where the
store now lives.
A hidden store, an address retired too long ago, and an address no store
ever held all answer the same 404, so a hidden store is never confirmed to
exist. Its own seller is the exception: they see the page with a banner
saying buyers cannot open it.

### What the page shows

The cover, the portrait, the name, the tagline, the location, "N pieces for
sale · Selling since <Month Year>" (`App\Support\Store\StoreFacts` — the
count is `Listing::forSale()`, so a sold piece stays on the page and out of
the number), the sections in order, the links, and the seller's storefront
listings
(`Listing::onStorefront()` — for sale and sold, never draft, archived, or
removed) in the storefront's own grid partial.

The page carries a title, a description (the tagline, else the opening of
the first story, else the name), and an Open Graph image (the cover, else
the portrait). `x-layouts.shop` gained `description` and `image` props for
this, and emits the Open Graph group only for a page that passes one of
them; every other storefront page renders as it did.

### Listing cards lead to it

`x-listing-card` and `/art/{slug}` name the seller as a link to their store
when the store is published, and as plain text otherwise. The link reads
`$listing->seller->storeProfile`, so every query that feeds a card eager
loads `seller.storeProfile` — `Model::shouldBeStrict()` turns a missed one
into a lazy-loading violation outside production, so it fails loudly.

### Analytics

A view of a published page records `store.view` in the analytics store with
`subject_type = 'store'` and the profile's `sto_` id, deduplicated per
(store, customer, UTC hour) by `App\Domain\Store\StoreViewCollapse` —
`listing.view`'s shape. A seller previewing their own hidden page records
nothing. The admin analytics event list reads
`AnalyticsEventName::cases()`, so the event appears there; an actor's feed
names the store unlinked, the way it already names a cart.
