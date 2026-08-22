---
id: FEAT-008
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-008: Final validation, review, and end-to-end smoke

## Problem
Five feature tickets were built by separate agents in parallel. Their integration, the README's accuracy, the coverage target, and the full seller → customer → payout loop have not been verified as one system.

## Goal
The prototype is demonstrably runnable and testable by a team member following only the README.

## Outcome
- From a clean checkout (`rm -rf src/vendor src/node_modules`, delete the sqlite file), the README's first-run steps bring the app up and `make test` is green.
- `make coverage` reports ≥ 90% line coverage on `app/Domain` and ≥ 80% overall; gaps below that are either closed with tests or listed in the README's "Known gaps".
- A scripted smoke (`make smoke`, a PHPUnit test or a shell script using curl against the served container) walks: seller sign-in → create listing → mark for sale → anonymous customer views, favorites, adds to cart → guest checkout → magic link → finalize → seller sees notification and ships → customer confirms delivery → payout run → earnings page shows the payout.
- A review pass against the original brief (`__local__/prompt.md`) lists each requirement with its status and the route/test that proves it, in `docs/review.md`.
- Conventions from `docs/architecture.md` hold: no domain `if`s in controllers, sidecar tests beside every non-trivial file, no JavaScript required for any flow. Violations are fixed or listed.

## Why it matters
This is the hand-off. The team's first impression is whether the README works.

## Discovery notes
Run everything inside the container. Use `php artisan route:list` to cross-check the route names in the docs. If a fix is small, make it with a test; if it is large, file a BUG ticket in `work/1-inbox/` and list it in `docs/review.md`.

## Working

### Clean first run

`docker compose down --rmi local -v`, then removed `src/vendor`,
`src/node_modules`, `src/.env`, `src/database/database.sqlite`,
`src/public/build`, `src/public/storage`, `src/coverage`. `make up` rebuilt the
image and brought the app up with no other step. `/` and `/seller/login` answer
200, and the CSS the HTML names (`/build/assets/app-BJbvW7-4.css`) serves 200 at
45,684 bytes. `make fresh` seeded 12 listings onto the first storefront page.
Nothing in README.md or the entrypoint needed correcting.

### gd and the image upload rule

Added `gd` (with freetype and jpeg) to the Dockerfile. The upload rule moved
from `mimetypes:` to `image` + `mimes` + `dimensions:min_width=1,min_height=1`,
and the fixtures moved to `UploadedFile::fake()->image()`.

`image` and `mimes` alone do not close the hole the ticket describes: both read
`guessExtension()`, which `Illuminate\Http\Testing\File` answers from the
declared mimetype. `dimensions` calls `getimagesize()` on the file, so a text
file that claims `image/jpeg` is refused. A test asserts that.

### Suite and coverage

`make test`: 471 tests, 1101 assertions, green (470 before this ticket, +1 for
the claims-to-be-an-image case, +1 smoke, and one pre-existing test re-run).
`make coverage`: **98.20% lines overall (1093/1113)**, **100% on `app/Domain`
(277/277)**, 100% on `app/Actions`, 99.74% on `app/Http`, 86.07% on
`app/Models`. Both targets are met, so the remaining gaps are listed in the
README rather than papered over with tests for unread `belongsTo` relations.

### Smoke

`src/tests/SmokeTest.php`, a `Smoke` testsuite of its own, and `make smoke`. It
runs in 0.3s so the default `make test` keeps it. One test, 77 assertions,
walking: seller magic-link sign-in (link read out of the rendered debug alert)
-> create listing with a real image upload -> mark for sale -> a fresh anonymous
customer minted by the identity middleware views, favorites, carts -> guest
checkout -> the magic link out of the order page's debug alert -> verify -> pay
4242 -> seller's "Item sold" notification -> ship -> customer confirms delivery
-> `payouts:run --as-of=<next Monday>` -> earnings shows $432.00, which is 90%
of the $480.00 price. Time is frozen, so the payout period lands the same way on
any day the suite runs.

The seller magic link had to come from the rendered page rather than the flashed
session key: the intervening GET of `/seller/login` consumes the flash, which is
the same thing a browser does.

### Browser check

curl walk against the running container with a cookie jar. Storefront: `/`,
`/art/{slug}` (all 12 on the first page), `/cart`, `/favorites`, `/orders`,
`/login` all 200; `/checkout` and `/account` 302 to `/cart` and `/login` with an
empty cart and no session. Seller: signed in as `maya@example.com` through the
debug link, then `/seller`, `/seller/listings`, `/seller/listings/{id}`,
`/seller/listings/{id}/edit`, `/seller/listings/create`, `/seller/orders`,
`/seller/orders/{id}`, `/seller/earnings`, `/seller/notifications` all 200.
Another seller's listing ids 404. A full live guest checkout ended at
"Paid · $520.00". No 500s, no broken asset references.

### Conventions audit

- **No domain `if`s in controllers.** Every branch under
  `app/Http/Controllers` reads a domain predicate (`OrderPayment::isPayableBy`,
  `ListingAvailability::isPurchasable`, `MagicLinkStatus`, `ListingSearch::hasTerm`)
  or a shell fact (guard check, empty cart, null row).
- **Sidecars.** Four non-trivial files have none: `Actions/Auth/SignInSeller`,
  `Actions/Auth/SignInCustomer`, `Actions/Customers/ClaimCustomerIdentity`,
  `Actions/Customers/ResolveCustomerFromCookie`. All at 100% line coverage
  through the controller tests. Listed rather than fixed — four new test classes
  over already-covered code find nothing.
- **No JavaScript.** No `<script>` in any Blade file, no `resources/js`, Vite
  input is the stylesheet alone.
- **Comments.** Every comment in the tree is a decision record. No restatements,
  no adverb padding.
- **Pint.** One pre-existing violation in `Auth\SellerLoginControllerTest`
  (fully-qualified class name), fixed. 258 files clean.

### Left alone

FEAT-005's source inside FEAT-004's commits — history only, the tree is right.
