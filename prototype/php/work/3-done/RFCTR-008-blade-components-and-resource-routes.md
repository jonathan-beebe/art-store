---
id: RFCTR-008
type: refactor
status: resolved
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

## Working
- Re-verified the Problem line against the current tree before touching anything: `@visitorCan`, `ShopLayoutComposer`, and `DatabaseNotification`-backed inboxes (landed by RFCTR-002..007) needed no rework, just carrying forward — `$notification->data['subject']` etc. stayed untouched in every view.
- Components added under `resources/views/components/`: `listing-card.blade.php` (`:listing`), `card-fields.blade.php` (no props), `debug-alert.blade.php`, `form/field.blade.php` (`@props(['name','label','type'=>'text','value'=>null,'required'=>false,'hint'=>null])`, supports `text`/`number`/`file`/`textarea` directly plus a slot fallback for anything more exotic), `layouts/seller.blade.php` and `layouts/shop.blade.php` (component layouts with a `title` prop and `{{ $slot }}`).
- `form/field.blade.php`'s wrapper `<div>` renders only the `class` attribute the caller passes (`$attributes->only('class')`); everything else on the tag forwards to the `<input>`/`<textarea>` (`$attributes->except('class')->merge([...])`). That let each usage in `form.blade.php` reproduce the original spacing exactly (title/medium/dimensions/price/quantity get no wrapper class since the grid parent already spaces them; description and image keep `class="mt-4"`) instead of a component-wide guess.
- All 20 views that used `@extends('layouts.*')`/`@section` now open with `<x-layouts.shop title="...">`/`<x-layouts.seller :title="...">` and close with `</x-layouts.shop>`/`</x-layouts.seller>`; `resources/views/layouts/` and `resources/views/partials/` and `resources/views/shop/partials/` are deleted.
- `edit.blade.php` now passes `['listing' => $listing]` explicitly to `@include('seller.listings.form', ...)` instead of relying on scope leakage from the parent view; `create.blade.php` already passed `['listing' => null]` and is unchanged in that respect.
- `AppServiceProvider::boot()`: `View::composer('layouts.shop', ...)` → `View::composer('components.layouts.shop', ...)`, matching the compiled view name behind `<x-layouts.shop>`. `ShopLayoutComposerTest` needed no edit — it only drives real HTTP requests and asserts on rendered markup, never the composer's view-name string.
- `ListingActivityController` folded into `ListingController::show` (`app/Http/Controllers/Seller/ListingController.php`); the class and its sidecar test are deleted, and its 8 tests moved into `ListingControllerTest.php` (one renamed `hides another sellers listing` → `hides another sellers listing from the activity page` to disambiguate from the edit-form test of the same original name).
- `routes/seller.php`: the six hand-written listing routes are now `Route::resource('listings', ListingController::class)->except('destroy')`; route names are unchanged (`seller.listings.index/create/store/show/edit/update/status`, the last kept as a separate `Route::post` since it isn't a resource action). `update` is now `PUT|PATCH` only — `php artisan route:list --path=seller` confirms 18 routes, same count and names as before, only the update verb changed.
- Two tests in `app/Http/Requests/Seller/ListingRequestTest.php` and the four update-path tests in `ListingControllerTest.php` switched from `->post(".../{$listing->id}")` to `->put(...)` since the resource no longer registers a POST route there.
- New tests: `ListingControllerTest` — "renders the edit form as a PUT form" (asserts the literal `<input type="hidden" name="_method" value="PUT">`); `CartControllerTest` — "renders the remove button as a DELETE form" (same assertion, value `DELETE`). Confirmed the exact markup `method_field()` emits (`type` before `name` before `value`) by reading `Illuminate\Foundation\helpers.php` rather than guessing.
- `tests/Arch.php`'s `ignoring('App\Http\Controllers')` on the `laravel` preset left as-is: `ListingController` alone would now pass the preset's `not->toHavePublicMethodsBesides([...REST methods...])` rule, but `CartController`, `FavoriteController`, `ShipmentController`, `PayoutController`, `NotificationController`, `ListingStatusController`, `OrderPaymentController`, `AccountController`, and others still expose action-verb methods (`add`, `remove`, `toggle`, `markRead`, `pay`, `readNotification`, plus several single-action `__invoke` controllers), so narrowing the ignore to exclude just `ListingController` isn't self-contained without restructuring those too — out of this ticket's scope.
- `docs/architecture.md` Sites paragraph and the `AppServiceProvider` bullet updated for the component layouts and the new composer view name. `docs/review.md`: the `ListingActivityControllerTest` reference became `ListingControllerTest`, and the two `layouts/seller`/`layouts/shop` evidence cells and the debug-alert evidence cell now read `<x-layouts.seller>`/`<x-layouts.shop>`/`<x-debug-alert>`.
- Left out: `README.md`'s repository-layout listing (`layouts/shop, layouts/seller, partials/debug-alert`) still names the deleted paths — not called out in this ticket's guidance and touching it means editing a file the ticket doesn't name, so it's flagged here instead of changed.
- Gate: PHPStan 0 → 0 errors. Pint clean (297 files). Pest 665 → 667 passed (1496 → 1498 assertions) — net +2 from the two new DELETE/PUT-form tests; the 8 activity tests moved rather than duplicated.
