---
id: RFCTR-003
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-003: Use the ActiveSupport built-in where the code reimplements one

## Problem
Four modules under `src/app/domain` reproduce standard library behaviour: `Domain::Reports::StatusLabel.of` and `Domain::Shop::StatusLabel.humanize` are `String#humanize`; `Domain::Auth::LocalRedirect` is `ActionController::Redirecting#url_from`; `Domain::Listings::ListingSlug.base` is `String#parameterize` (which `db/seeds/listings.rb` already uses); `Domain::Money#format` plus `grouped_dollars` is `number_to_currency`.

## Goal
Readers recognise Rails idioms instead of learning local reimplementations.

## Outcome
The two `StatusLabel` modules and `LocalRedirect` no longer exist; slugs and money formatting delegate to the built-ins; rendered labels, redirect behaviour (foreign host, protocol-relative, backslash, newline all fall back) and money strings are byte-identical to today's, as proven by the existing controller and domain tests.

## Why it matters
Each reimplementation carries its own tests and its own edge cases; the built-ins are maintained upstream and already handle the security cases (`url_from`) and locale rules (`number_to_currency`).

## Discovery notes
`url_from` returns nil for anything off the request host; the security cases in `test/domain/auth/local_redirect_test.rb` belong in the auth controller tests. `parameterize` transliterates accents where the regex dropped them; if a test pins that difference, the built-in wins. Verify `number_to_currency` on negative cents and on amounts over $1,000.

## Related work
- RFCTR-001

## Working

### C1 — status labels

Both `StatusLabel` modules produced the same string as `String#humanize` for every value in
`OrderStatus::ALL`, `FulfillmentStatus::ALL`, `ListingStatus::ALL` and `paid_out`, checked value by
value before the change. The two modules and their tests are gone.

`status_label` now lives once in `ApplicationHelper` rather than once per site. Rails includes every
helper in every view, so a copy in `SellerHelper` alongside the one in `ShopHelper` would have been
shadowed by load order and never run — coverage caught it as a dead line. Seller views call
`status_label`; `Seller::ListingStatusesController` and `Domain::Reports::ListingStatusCount#label`
call `humanize` directly.

### C2 — local redirects

`ActionController::Redirecting#url_from` parses the location with `URI` and keeps it when the host
matches the request host, or when there is no host and the path starts with a single `/`. Checked
against the cases the deleted unit test pinned: foreign host, a host that only prefixes this one,
protocol-relative, `/\evil`, a target carrying a newline and a blank target all come back nil; a
root-relative path and an absolute URL on this host come back unchanged. The backslash and newline
cases fall out of `URI` raising, which `url_from` rescues.

Those cases now live in `test/controllers/auth/customer_sessions_controller_test.rb` (what the
sign-in form carries forward) and `test/controllers/auth/magic_links_controller_test.rb` (where
verification lands when the link itself holds the destination). `Auth::BaseController#local_redirect`
is gone; the two call sites call `url_from` directly.

`url_from` compares the host alone, where the deleted module compared the whole origin. An absolute
URL on this host with a different scheme or port is now kept. No route depends on the difference.
