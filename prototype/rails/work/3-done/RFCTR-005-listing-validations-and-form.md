---
id: RFCTR-005
type: refactor
status: resolved
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

## Working

`Listing` now declares the six field validations with the sentences the tests
assert, `normalizes` the four text columns, assigns a free slug in a
`before_validation on: :create`, and holds `TRANSITIONS`, `next_statuses`,
`transition_to!`, `purchasable?`, `on_storefront` and `record_event!`. The
`price` reader and writer convert dollars to `price_cents` and back; the writer
keeps the text as it was typed, so a refused form renders the seller's own
input rather than a re-rendered number. `ListingEvent` carries its event types
in the `enum` itself.

`Seller::ListingsController` is the stock new/create/edit/update shape over
`params.expect`, and `_form.html.erb` is `form_with model: [:seller, listing]`
reading values off the record and messages off `listing.errors`. The rendered
markup is unchanged: `config.action_view.field_error_proc` returns the tag it
was given, which was the smallest way to keep Rails' `field_with_errors`
wrapper out of a form that already renders its own errors through
`seller/shared/_field_error`. Setting it in `config/application.rb` covers
every form in the app, and no form here wants the wrapper.

Two behaviours moved rather than stayed identical, both invisible to the tests
that existed. The image check reads the content type Active Storage identified
from the uploaded bytes instead of the content type the request declared —
Active Storage overwrites a declared type with what it reads out of the file,
so the declared one is no longer available by the time the model sees it; the
controller test's refused upload now carries a real PDF header. And a file
field left empty posts as `""`, which mass assignment would take as "detach the
image", so `Listing#image=` ignores a blank upload — the portal replaces an
image, it never removes one. `Listing#image_url` renders the saved attachment,
since a pending upload has no URL until it is saved and a rejected edit
re-renders the form.

`Domain::Listings::{ListingDraft, ListingSlug, ListingStatus, ListingEventType,
ListingAvailability}` and all four `app/actions/listings/` classes are deleted,
with their tests folded into `test/models/listing_test.rb`. `ListingStock` and
`StockChange` stay for RFCTR-007; `ListingStock` reads `Listing.transition` for
the one move it computes without a record in hand. `Domain::Reports::{
ActivityTotals, ListingStatusTally}` and `Domain::Shop::FavoriteChange` read
the status and event strings directly.

`Domain::Money.from_dollars` is left in place: the listing form was its only
caller, so it is now exercised only by its own test.
