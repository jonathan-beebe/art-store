---
id: IMPRV-026
type: improvement
status: resolved
created: 2026-09-03
---

# IMPRV-026: The seller and admin sign-in pages are one centered column in the portal's chrome

## Problem
The seller and admin sign-in pages (`src/resources/views/auth/seller-login.blade.php`, `admin-login.blade.php`) are the last screens in either portal still on the pre-redesign shape: a thin guest header from the layout's guest branch (`layouts/seller.blade.php:133-159`, `layouts/admin.blade.php:310-336`), a left-aligned h1, and a bordered gray card holding one field and a button, all pinned to the top-left of a `max-w-6xl` column. PRs #58 and #59 moved every signed-in screen to the canonical Tailwind chrome; the first screen a seller or admin sees did not move with them.

## Goal
The first screen of each portal reads as part of the same product as the screens behind it.

## Outcome
- `/seller/login` and `/admin/login` render as a single centered column in the viewport: brand mark, "Sign in" heading, one email field, one submit button, and the portal's one-line footer copy, with nothing above or beside it.
- The seller page carries the seller portal's indigo accent and the admin page the admin portal's stone accent; both render correctly in light and dark.
- The three existing states all render inside that same centered column: the empty form, the "Check your email" confirmation naming the submitted address, and the rate-limit error. No state falls back to the old header-and-card shape.
- Every existing test in `SellerLoginControllerTest`, `AdminLoginControllerTest`, and the smoke suite passes unchanged: the form posts to the same route names, the field is still `name="email"`, the confirmation and "Too many requests" copy still appear, the admin's byte-for-byte non-revealing response still holds, and the debug bar still shows the magic link in local dev.
- The customer `/login` page and the storefront are untouched.
- `make check` green.

## Why it matters
The sign-in page is the one screen every seller and admin sees on every visit, and today it is the only screen that contradicts the redesign. A founder demoing the seller tool starts from its weakest screen.

## Discovery notes
- Reference block: `__local__/resources/tailwind-application-ui-v4/html/forms/sign-in-forms/01-simple.html` ("Simple"). Its shape: `html`/`body` `h-full`, a `flex min-h-full flex-col justify-center` column, an `sm:max-w-sm` inner column with the mark and heading, then the form. Drop the password field, forgot-password link, and the "Not a member?" footer; our flow is one email field. The submit stays "Email me a sign-in link".
- The demo's mark is an external SVG; both layouts already carry an inline brand mark (the rounded "A" square, `indigo-600` in `seller.blade.php:136`, `stone-600` in `admin.blade.php:218`). Reuse that rather than fetching an asset.
- The layout guest branch is shared with only two other views, `errors/rate-limited-seller` and `errors/rate-limited-admin`. Options the maker can weigh: a guest prop or a separate minimal auth layout for the sign-in page, leaving the rate-limited pages where they are.
- The two login views differ only in action route, confirmation copy, and the seller footer line. One shared partial or component with the accent as a parameter is a reasonable end state; two views that share the shape by copy is also acceptable for two files.
- Input and button idioms: `x-form.field` is still on the border idiom while the redesign uses the ring/outline idiom the Simple block uses. Match the redesign.
- The demo block puts the column at `justify-center` of the full viewport; on short viewports keep `py-12` so the confirmation alert above the form does not clip.

## Related work
- FEAT-002 (magic-link flow and the login views)
- IMPRV-002 (admin debug notice on the redirected login page)
- PR #58 / PR #59 (seller and admin chrome redesigns that left the guest branch behind)
- DSGN-008 (design system audit)

## Working
- Tests first: five new tests per sidecar (`SellerLoginControllerTest`, `AdminLoginControllerTest`). "No guest header" is pinned by `assertDontSee` on the dashboard and login route hrefs, for the empty form, the confirmation after a successful POST, and the rate-limited re-render; one structural test per portal pins exactly one `<h1>` "Sign in", one `name="email"`, one `<button>` "Email me a sign-in link"; the seller footer line is pinned. Six failed before the change.
- New `components/layouts/auth.blade.php`: a standalone shell (skip link, `x-debug-alert`, `<main id="main-content">` as a `flex-1 justify-center` column, `sm:max-w-sm` inner column) with an `accent` prop (`indigo` | `stone`) that selects the mark fill and the heading neutral. The seller and admin layouts and their guest branches are untouched, so the two rate-limited error pages keep their shape.
- `auth/seller-login.blade.php` and `auth/admin-login.blade.php` render inside that shell with the Simple block's field, button, and footer spacing; inputs use the ring/outline idiom, the button is full width. Seller: gray neutrals, indigo accent. Admin: stone neutrals and accent, matching the admin shell's tint rules.
- The layout renders only `$errors->get('rate_limit')` above the form (the key `Controller::tooManyRequests()` writes); the field renders its own `@error('email')`. Before this the whole bag rendered at the top and the email message showed twice on a failed validation. Pinned by one new test per sidecar (`renders the invalid-email validation message exactly once`) and a `substr_count` on "Too many requests" in the existing rate-limit tests.
- The brand mark is `aria-hidden`; the page title and the `<h1>` carry the name.
- Verified in Chrome on the running stack: empty form, confirmation with the debug bar above the column. Admin page verified via curl without a session cookie.
- Gate: auth sidecars 67 passed; `make test` 4043 passed / 33087 assertions; `make lint` clean (Pint, PHPStan 0 errors); `make assets` builds.
- Found, not fixed: none.
