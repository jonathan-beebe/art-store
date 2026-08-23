---
id: RFCTR-015
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-015: Split Listing's activity reporting into a concern; Fulfillment computes its own fee

## Problem
`src/app/models/listing.rb` now holds validations, slug assignment, the status transition table, stock moves, dollars-to-cents price, storefront search, and the activity reporting that the seller portal reads (`activity_totals`, `activity_by_day`), which makes it the longest model in the app. `src/app/models/order.rb` computes each fulfillment's `fee_cents`/`net_cents` in `Order.split_by_seller` by calling `Fulfillment.fee_for`/`net_for`, so the fee rule is applied from outside the record that stores it.

## Goal
Each model reads as one responsibility per file, with the fee rule living where the fee is stored.

## Outcome
`Listing`'s activity reporting lives in a concern under `app/models/listing/` (or `app/models/concerns/`), `Listing` itself is shorter than its tests need to scroll past; `Fulfillment` derives `fee_cents` and `net_cents` from `subtotal_cents` itself (a `before_validation`), and `Order.split_by_seller` passes only the subtotal; the suite passes unchanged.

## Why it matters
A long model hides its contract; a fee computed by the caller can drift from the column it fills.

## Discovery notes
The 37signals shape is `app/models/listing/activity.rb` as `module Listing::Activity` with `extend ActiveSupport::Concern`, included from `Listing`. `Fulfillment::PLATFORM_FEE_PERCENT` is already on `Fulfillment` (RFCTR-009).

## Related work
- RFCTR-005
- RFCTR-009
- RFCTR-011
