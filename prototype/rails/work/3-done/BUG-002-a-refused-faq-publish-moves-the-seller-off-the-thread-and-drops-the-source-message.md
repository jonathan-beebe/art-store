---
id: BUG-002
type: bug
status: resolved
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

## Working

**Re-validated the problem.** `ListingFaq::QUESTION_LIMIT` 500 against
`Message::BODY_LIMIT` 2000 means `ListingFaq.draft_from` can pre-fill the
panel with a question the model refuses; `maxlength` on a textarea caps typing
and leaves a prefilled value as it is. The refusal ran `render :index`, which
draws `seller/faqs/index` — the FAQ page, not the thread — and `_fields`
carried no `source_message_id`, so a resubmit from there published with the
attribution dropped.

**The re-render.** `MessagingSite#present_thread` moved into a `ThreadPage`
concern that `MessagingSite` includes, so the assigns have one definition.
`SellerThreadPage` includes `ThreadPage`, overrides `present_thread` with
`super` plus `@faq`, and names `TEMPLATE` — the portal's thread view.
`Seller::ConversationsController` and `Seller::MessagesController` include it
beside `MessagingSite`; `Seller::FaqsController` includes it on its own, since
it draws that page without being a messaging site.
`Seller::FaqsController#render_refusal` reads the refused entry's
`source_message` for the conversation: with one it assigns the thread, calls
`present_thread(Message.new, faq: draft)` and renders `SellerThreadPage::TEMPLATE`
at `:unprocessable_content`, the way `MessagingSite#create` answers a refused
reply; with none it keeps the old `render :index`.

**The attribution.** `form.hidden_field :source_message_id` moved out of
`_publish_faq.html.erb` into `_fields.html.erb`, rendered whenever the record
holds one. Both forms that render `_fields` — the thread's publish form and
the FAQ page's — carry it now, so a resubmit from either keeps the answer the
entry was lifted from.

**The assign.** `seller/conversations/show.html.erb` reads `@faq` in place of
calling `ListingFaq.draft_from(@conversation)` from the template. The refused
draft has to take the draft's place on the re-render, which is what the assign
is for. That also settles IMPRV-001 (b).

**Left alone.** `Shop::MessagesController` and `Admin::MessagesController` still
return their `thread_template` as a string literal; only the portal has a second
controller drawing the same view.

**Verification.** `make test`: 748 runs, 2358 assertions, 0 failures, 0 errors,
line coverage 1263 / 1263 (100.00%). Four tests added: an over-long question
and an over-long answer published from a thread coming back on the thread with
the field error, the messages and the reply form still there and nothing
published; the re-render holding the seller's text and the hidden
`source_message_id`; the shortened resubmission publishing with its source.
`docs/messaging.md` states the refusal path, `docs/architecture.md` the two
concerns.
