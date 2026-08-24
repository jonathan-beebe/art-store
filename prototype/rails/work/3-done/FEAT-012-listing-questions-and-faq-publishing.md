---
id: FEAT-012
type: feature
status: resolved
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

## Working

### Verified before changing anything
- `Conversation.open` / `#post!` / `Listing has_many :faqs` / `ListingFaq.publish` (FEAT-010)
  and `MessagingSite` plus the three thread views (FEAT-011) are in place as the ticket says.
- Baseline: 687 runs, 2082 assertions, 100% line coverage (1168/1168).
- The listing page's existing forms (add to cart, favorite) redirect with `flash[:alert]`
  on a refusal rather than re-rendering, so the question form does the same.

### Decisions
- **Refused question redirects.** `Shop::ListingQuestionsController#create` rescues
  `ActiveRecord::RecordInvalid` and redirects to the listing with the message's own error
  in `flash[:alert]`, matching `Shop::CartItemsController`.
- **A refused question leaves no thread.** `Conversation.open` and `post!` run inside one
  transaction, so an empty body puts no empty row in either inbox.
- **A source outside the listing's threads is 404, not a validation error.** The lookup is
  `Message.where(conversation: @listing.conversations).find(id)`, so a message from another
  listing or another seller is a row this seller cannot reach — the same shape as
  `Conversation.involving(actor).find(id)` in `MessagingSite`.
- **`ListingFaq.draft_from(conversation)` rather than `Conversation#faq_draft`.** The FAQ
  knows where it can be lifted from; the general messaging record stays clear of one kind's
  product feature. It returns nil for a thread that is not a listing question and for one
  the seller has not answered, so the thread view renders the form only when there is a pair.
  It uses the new `Conversation#latest_message_from(actor)`.
- **`ListingFaq.oldest_first`** mirrors `Message.oldest_first` and matches the
  `(listing_id, created_at)` index. The storefront reads `listing.faqs.oldest_first` with no
  predicate, since a row exists only while published.
- **Field error ids come from `form.field_id`.** The FAQ index carries one form per entry
  plus the new-entry form, so each edit form takes `namespace: "faq_<id>"` and the publish
  form on the thread takes `namespace: "publish_faq"`. `seller/shared/_field_error` gets the
  generated id, so `data-field-error` is unique per form on the page.
- **A refused publish or edit renders the FAQ index**, not the thread page. That page holds
  both forms, so one refusal path serves the entry written from scratch and the one lifted
  from a thread.
- `form_with model:` on `ListingFaq` derives `seller_listing_listing_faqs_path` from the
  class name, so every FAQ form passes `url:` explicitly against the `resources :faqs` route.

### Left alone
- `MessagingSite` is untouched; the publish form is a partial the seller thread view renders,
  so `Shop::` and `Admin::` carry nothing new.
- No migration: `listing_faqs` and its index already exist from FEAT-010.

### Verification
- `make test`: 723 runs, 2228 assertions, 0 failures, 0 errors. Line coverage 1222/1222
  (100.00%) — Models, Controllers, Helpers, Mailers all 100%.
- Curl walk against http://localhost:3000 (seeded listing `low-tide-at-dusk`, seller
  `maya@example.com`):
  1. `GET /art/low-tide-at-dusk` carries "Ask the seller a question". 
  2. Anonymous `POST /art/low-tide-at-dusk/questions` → `302 /messages/6`.
  3. An empty body → back on the listing with "Write a message.", no thread opened.
  4. Seller signs in over the magic link, `GET /seller/messages/6` shows the question; no
     "Publish as FAQ" before the reply.
  5. `POST /seller/messages/6/messages` replies; the thread then carries the publish form
     with `listing_faq[source_message_id]=5` and both fields pre-filled.
  6. `POST /seller/listings/1/faqs` → `302 /seller/listings/1/faqs`, "Published to the
     listing."; the row records `source_message_id=5`.
  7. A fresh cookie jar on `GET /art/low-tide-at-dusk` reads "Questions and answers" with the
     pair under `data-faq`.
  8. A 501-character question → `422` with
     `data-field-error="listing_faq_question"` reading "Keep the question under 500 characters."
  9. A `source_message_id` from a rival seller's thread → `404`, nothing published.
  10. `PATCH .../faqs/:id` edits the answer; the listing page shows the new text.
  11. `DELETE .../faqs/:id` unpublishes; the listing page drops the entry and the heading.
- Dev database tidied afterwards: the rival-seller thread and the extra FAQ rows built during
  the walk were removed.
