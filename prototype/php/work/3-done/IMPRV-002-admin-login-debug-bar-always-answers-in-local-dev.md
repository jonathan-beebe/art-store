---
id: IMPRV-002
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-002: Admin login debug bar always answers in local dev

## Problem
The prototype has no mail delivery; every magic link reaches the person through the session-flashed debug bar (`<x-debug-alert>`). The admin flow admits only seeded admins (`SendAdminMagicLinkRequest`), and an address with no `admins` row gets the neutral "Check your email" response with no link and no explanation — correct production shape, but in a browser during local dev the flow dead-ends with nothing in the debug bar and no way to learn the seeded admin address short of reading the README.

## Goal
Every magic-link request made in local dev answers in the debug bar — with the link, or with why there is no link.

## Outcome
- Requesting a link at `/admin/login` for a seeded admin shows the magic link in the debug bar (unchanged, pinned by a test).
- Requesting a link for an address with no admin row, in local dev with `session` delivery, shows a debug-bar notice that no admin account exists for that address and names the seeded admin address to use.
- Under `mail` delivery the two responses stay byte-for-byte identical (the existing non-revealing test holds).
- Seller and customer magic-link flows are confirmed unchanged — they already flash their links.
- `make check` green; coverage 100%.

## Why it matters
The admin portal is demoed in a browser with no mailbox behind it; a sign-in page that silently swallows the only credential path reads as broken.

## Discovery notes
- The debug bar is `resources/views/components/debug-alert.blade.php`, reading `session('debug_magic_link')`. A sibling flash key (e.g. `debug_notice`) rendered in the same bar keeps one component owning the debug surface.
- Gate the notice on the same condition that flashes links (`config('magic_links.delivery') === 'session'`), so `mail` delivery keeps the non-revealing shape. `AdminSeeder::EMAIL` is the constant to name.
- The notice belongs where the admit decision is made (controller/request layer), not in the view.

## Related work
- FEAT-010 (admin actor; the admits rule and the non-revealing test), FEAT-015 (AdminSeeder and its email constant)

## Working
- `AdminLoginController::send()` now builds the redirect first, still sends the link only when `$request->admits()`, and — for an unadmitted address under `config('magic_links.delivery') === 'session'` — flashes `debug_notice` naming the submitted address and `AdminSeeder::EMAIL`. Under `mail` delivery neither `debug_magic_link` nor `debug_notice` is flashed, so the existing byte-for-byte non-revealing test (`it answers the same page byte for byte whether or not the address has an admin`) stays green untouched.
- `resources/views/components/debug-alert.blade.php` gained an `@elseif (session('debug_notice'))` branch beside the existing `debug_magic_link` branch, keeping the debug bar the one component that owns that surface.
- Added to `app/Http/Controllers/Auth/AdminLoginControllerTest.php`: a test that `debug_notice` is flashed and contains both the submitted address and `AdminSeeder::EMAIL`, a test that the redirected page renders it, a test that an admitted address flashes no notice, and a test that `mail` delivery flashes no notice.
- Seller and customer login flows were not touched — `SellerLoginController` and `CustomerLoginController` (and their `SendMagicLinkRequest`) have no admits-gate; every submitted address there already gets a link and a `debug_magic_link` flash, so the outcome bullet held by inspection, no code change needed.
- Verified over curl against the running stack (`http://localhost:8000`): POST `email=unknown-visitor@example.com` to `/admin/login`, followed the redirect, and the page shows `No admin account exists for unknown-visitor@example.com. The seeded admin address is admin@example.com.`; POST `email=admin@example.com` (seeded) still shows `Debug magic link:` with a working `/auth/magic/...` link.
- `make check`: lint (Pint, 448 files) clean, PHPStan level max 0 errors, Pest 1111 passed / 2497 assertions (was 1107 / 2491). Coverage 100.0% (`composer test:coverage -- -d memory_limit=1G`).
- Found, not fixed: none.
