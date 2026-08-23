---
id: FEAT-012
type: feature
status: open
created: 2026-08-23
---

# FEAT-012: Listing questions and FAQ publishing

## Problem
The storefront listing page (`src/app/views/shop/listings/show.html.erb`) has no way to ask the seller anything, and nothing renders `listing_faqs` rows (FEAT-010's table). A seller who answers a good question in a thread has no way to make the answer part of the listing for the next buyer.

## Goal
A question a shopper asks on a listing reaches the seller as a thread, and the seller's answer can be published on the listing page for everyone.

## Outcome
A shopper — anonymous or signed in — submits a question from the listing page and lands on the new (or existing) thread on the storefront; the seller sees it in the inbox and replies; from that thread the seller can publish a question-and-answer pair, pre-filled from the latest question and the seller's reply, and the listing page then shows it to every visitor under a FAQ heading; the seller manages the listing's FAQs from the portal listing pages (edit the text, unpublish), and an unpublished entry disappears from the listing page; a question over 500 characters or an answer over 2000 is refused with the form re-rendered; a shopper who later verifies their address keeps the thread; integration tests walk the whole loop and the suite stays at 100% line coverage.

## Why it matters
This is the one conversation kind that produces a product feature beyond the chat itself — a seller's answer compounds into storefront content. The Node prototype calls it out as the feature that pays for the messaging model.

## Discovery notes
`post "art/:slug/questions"` → `Shop::ListingQuestionsController#create` sits on the storefront with no verified-customer guard, so the `current_customer` row the identity concern mints is the participant — the same way favorites work for a visitor. Listing lookup is `Listing.on_storefront.find_by!(slug:)` as the listing page does. Seller side: `resources :listings do resources :faqs, only: %i[index create update destroy] end` under the seller namespace, with `destroy` as "unpublish". The thread page (FEAT-011) for a seller viewing a `listing_question` thread gains the publish form with a hidden `source_message_id`; the pre-fill is the latest customer message as the question and the seller's latest reply as the answer. The listing page's FAQ section reads `listing.faqs` in `created_at` order with no predicate because a row exists only while published. A `Seller::ListingsController#show` link to "FAQs" keeps the management page reachable without the thread.

## Related work
- FEAT-005 (customer storefront)
- FEAT-010
- FEAT-011
- prototype/node/docs/messaging.md ("A question becomes a published FAQ")
