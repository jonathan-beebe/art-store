---
id: BUG-002
type: bug
status: open
created: 2026-08-23
---

# BUG-002: A refused FAQ publish moves the seller off the thread and drops the source message

## Problem
`ListingFaq::QUESTION_LIMIT` is 500 while `Message::BODY_LIMIT` is 2000, so a legal 600-character question pre-fills the seller's "Publish as FAQ" form (`src/app/views/seller/conversations/show.html.erb`, `ListingFaq.draft_from`) with a value the model will refuse; `maxlength: 500` does not truncate a prefilled textarea, so the panel looks ready until submit. The refusal path (`src/app/controllers/seller/faqs_controller.rb:17-21`) then does `render :index`, which moves the seller from the thread onto the FAQ page, and `_fields.html.erb` carries no `source_message_id`, so re-submitting from there publishes with the attribution dropped.

## Goal
A refused publish keeps the seller where they were, with everything they submitted, and a long question is publishable by shortening it in the form.

## Outcome
Publishing from the thread with a question or answer over the limit re-renders with the field error, the shortened resubmission succeeds, and the published row carries the `source_message_id` the thread form held; integration tests cover the over-limit publish from the thread and the successful retry; the suite stays at 100% line coverage.

## Why it matters
The FAQ loop is the messaging feature's product payoff; the current path silently drops the message-to-FAQ link exactly when a good long answer is being lifted.

## Discovery notes
The refused publish knows whether it came from the thread (`source_message_id` present, or an explicit hidden field naming the return path); re-rendering the thread page with the draft's errors mirrors how `MessagingSite#create` re-renders a refused reply. Keeping `source_message_id` in `_fields.html.erb` as a hidden field whenever the record carries one also closes the attribution drop for the edit-page path.

## Related work
- FEAT-012
- prototype/rails/work/3-done/FEAT-012-listing-questions-and-faq-publishing.md
