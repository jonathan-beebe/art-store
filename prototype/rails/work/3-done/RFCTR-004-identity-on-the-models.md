---
id: RFCTR-004
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-004: Identity behaviour lives on MagicLink, Seller and Customer

## Problem
Sign-in is spread over five service objects and six domain modules: `Auth::SendMagicLink`, `Auth::ClaimSellerIdentity`, `Customers::ClaimCustomerIdentity`, `Customers::MergeAnonymousCustomer`, `Customers::ResolveCustomerFromCookie` under `src/app/actions`, and `Domain::Auth::{ActorType,EmailAddress,MagicLinkStatus,MagicLinkToken}`, `Domain::Customers::{IdentityPlan,OwnedTables}` under `src/app/domain`. `MagicLink#actor_type` wraps a string in a `Data` class; email normalization and validity live in a module the three models delegate to; the merge re-points rows with raw SQL over a table list and is tested against a probe table it creates itself.

## Goal
The models own sign-in: issuing a link, consuming it, claiming or merging an account.

## Outcome
`MagicLink`, `Seller` and `Customer` expose intention-revealing methods (issue, usable/expired/consumed, claim, merge, resolve-from-cookie); `actor_type` is an `enum`; email normalization and format validation are declared on the models; the merge moves rows through the associations `Customer` already declares; the `app/actions/auth`, `app/actions/customers` and `app/domain/auth`, `app/domain/customers` trees are gone; every identity integration test and the smoke test pass unchanged.

## Why it matters
A reader of `customer.rb` today cannot see that a customer can be claimed or merged; the behaviour is in a directory Rails does not have. Raw SQL over a table list re-implements the associations and needs a bespoke test fixture.

## Discovery notes
`normalizes :email, with: ->(email) { email.strip.downcase }` is the whole of `EmailAddress.normalize`. `MagicLink.issue(email:, actor_type:, redirect_to:)` returning the plaintext token beside the row keeps the digest-only storage. `Customer.claim(email, current:)` can hold the four-way decision `IdentityPlan` encodes. The `MagicLinkDelivery` port is a separate ticket (RFCTR-012).

## Related work
- RFCTR-003
- RFCTR-011
- RFCTR-012

## Working

`MagicLink` now issues and spends its own links: `MagicLink.issue(email:,
actor_type:, redirect_to:, now:)` returns `[link, token]` and stores only
`Digest::SHA256` of the token, `MagicLink.find_by_token` reads it back, and
`#usable?` / `#expired?` / `#consumed?` / `#consume!` replace the status
module. `actor_type` is an `enum` over the same two strings, so the controller
reads `link.seller?` and picks a path out of a two-entry hash. The expiry
window stays in `config/initializers/magic_links.rb` and is read through
`MagicLink.expiry`.

`Seller.claim(email)` and `Customer.claim(email, current:)` replace the two
claim actions. `Customer.claim` holds the decision the `IdentityPlan` data
class encoded: create a verified row, sign in the account already holding the
address, claim the anonymous row in place (`Customer#claim_address`), or absorb
it into the account (`Customer#absorb`). `Customer#absorb` moves favorites,
carts, orders, listing events, and notifications with `update_all` through the
associations, so the table list, the raw SQL, and the `table_exists?` guards
are gone; `Customer` now declares the `has_many :listing_events` that
`ListingEvent` was already pointing at, with `dependent: :nullify` because a
merge moves those rows rather than deleting them. `Customer.from_cookie`
replaces the resolve action and still follows a `customer_merges` row forward.

Email normalization and the address shape live in one `EmailAddress` concern
under `app/models/concerns`, included by all three models. The concern keeps a
name that `Domain::Shop::CheckoutForm` can reach for its own completeness
check, which is why the shape is a constant there rather than on `MagicLink`.
A sign-in form that submits something that is not an address gets an unsaved
link back from `MagicLink.issue` and re-renders with `422` and the same flash
text.

The three lines that glued issuing to delivery became `MagicLinkSender`, a
controller concern included by `Auth::BaseController` and
`Shop::CheckoutsController`. `MagicLinkDelivery.build(flash)` is untouched —
RFCTR-012 owns that port.

Tests: the eight files under `test/actions/{auth,customers}` and
`test/domain/{auth,customers}` are gone, their behaviour rewritten against the
model API in `test/models/{magic_link,seller,customer}_test.rb`, including the
merge cases the old suite proved against a probe table it created itself (a
favorite, a cart, an order, a listing event, and a notification move; a
bystander's rows stay; the trail is written; the anonymous row survives). The
message precedence a consumed-and-expired link gets moved into
`Auth::MagicLinksControllerTest`. Controller tests changed only where they
named a deleted constant. 609 runs, 1601 assertions, 0 failures, 100% line
coverage; `zeitwerk:check` and `db:reset` (seeds) both pass.

Left alone: the `Domain::Shop::CheckoutPurchaser` normalization is now an
inline `strip.downcase` rather than a call into the deleted module — RFCTR-011
dissolves that namespace and can fold it into the model layer then.
