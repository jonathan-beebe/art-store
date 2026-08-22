---
id: RFCTR-004
type: refactor
status: open
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
