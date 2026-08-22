---
id: RFCTR-005
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-005: Listing validations, the listing form, and status transitions on Listing

## Problem
`Domain::Listings::ListingDraft.errors_for` hand-rolls field validation (presence, length, dollar pattern, whole number, image content type); `Seller::ListingsController` carries `@fields`/`@errors` hashes, `submitted_fields`, `fields_of` and a dollars formatter; `app/views/seller/listings/_form.html.erb` is `form_with url:` with every value plumbed by hand. `Listings::CreateListing`, `UpdateListing`, `ChangeListingStatus` and `RecordListingEvent` are one-method service objects around `Listing`, and `Domain::Listings::ListingStatus` holds the transition table the model's `enum` already names.

## Goal
`Listing` validates itself and the seller form is a stock Rails model form.

## Outcome
`Listing` declares its validations and a dollars-to-cents `price` attribute; the form is `form_with model:` and renders errors from `@listing.errors`; create/update/status-change/record-event are model methods; the controller is the conventional new/create/edit/update shape; the same messages and `data-*` hooks render and every seller-portal test passes unchanged.

## Why it matters
The current shape reimplements `ActiveModel::Validations` and the form builder, and the architecture doc has to explain why the model is left unvalidated. Seeds already create valid rows, so validations on the model cost nothing.

## Discovery notes
Keep the exact error sentences the tests assert (`"Enter a title."`, `"The price is an amount in dollars, like 249.00."`). `Listing#slug` generation (`ListingSlug.first_free`) fits `before_validation on: :create`. The storefront reads `Listing.for_sale` and `purchasable?`-style predicates; those belong on the model too.

## Related work
- RFCTR-003
- RFCTR-011
