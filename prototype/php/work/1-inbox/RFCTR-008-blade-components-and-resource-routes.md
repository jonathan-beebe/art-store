---
id: RFCTR-008
type: refactor
status: open
created: 2026-08-23
---

# RFCTR-008: Blade components and resourceful seller routes

## Problem
`resources/views/components/` does not exist; shared markup is `@include` with array payloads (`shop/partials/listing-card.blade.php` from `home.blade.php:42` and `favorites.blade.php:13`; `shop/partials/card-fields.blade.php` from three pages; `partials/debug-alert.blade.php` from both layouts), and layouts use `@extends`/`@yield`. `seller/listings/edit.blade.php:16` includes the form relying on `$listing` leaking from parent scope while `create.blade.php:13` passes `['listing' => null]`; `form.blade.php` repeats the same three-line `@error` block six times with the `aria-describedby` idiom on each input. `routes/seller.php:17-23` spells out six listing routes by hand, the update is `POST` (`:22`) while the cart remove correctly uses `@method('DELETE')`, and `listings.show` maps to a different controller than the other five.

## Goal
The views are built from named components with explicit props, and the seller listing routes are a resource.

## Outcome
- Components exist for the listing card, the card fields, the debug alert, a form field (label + input + error + `aria-describedby`), and the two layouts; the partials are gone and the form partial's implicit variable is gone.
- Seller listings are `Route::resource` (update via `PUT` with `@method('PUT')`), with the activity view as the resource's `show`; route names stay the same so existing tests and links hold.
- A test asserts the cart remove button renders as a `DELETE` form and the listing edit form as a `PUT` form.
- Rendered HTML for every page is equivalent (tests green; visual check of both sites).

## Why it matters
Blade components are the template idiom a reviewer expects in 2026 Laravel; `@include` with arrays reads as Laravel 5.

## Discovery notes
- Anonymous components (`resources/views/components/*.blade.php` with `@props`) suffice; class-based components are only needed if logic appears.
- `<x-layouts.shop>` / `<x-layouts.seller>` with `{{ $slot }}` replace `@extends`.

## Related work
- RFCTR-002
