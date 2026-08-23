---
id: FEAT-012
type: feature
status: open
created: 2026-08-23
---

# FEAT-012: Seller portal messaging and listing faqs

## Problem
The messaging tables, rules, and notification exist after FEAT-011 but no page reaches them. A seller cannot read a thread, answer a question, contact support, message a buyer about an order, or publish an answer to a listing.

## Goal
A seller runs their whole side of messaging from the portal, and a good answer becomes an FAQ entry on the listing.

## Outcome
- `/seller/messages` lists the seller's threads newest first, each showing who it is with, what it is about, the last message, and how many are unread.
- Opening a thread shows every message in order with who sent each, and a reply box. Opening it clears that thread's unread count and the count in the nav.
- Replying appends the message and returns to the thread with the reply visible.
- "Support" from the portal opens the seller's thread with the platform, and opening it a second time lands on the same thread.
- A fulfillment page has "Message the customer", which lands on the thread for that order and seller; a second visit lands on the same thread.
- A thread about a listing question offers "Publish as FAQ", pre-filled with the question asked and the seller's latest answer. Publishing puts the entry on the listing's questions page. That page also edits an entry and removes it.
- The seller's listing page links to its questions and answers.
- A Messages link with the unread count is in the portal nav on every page.
- Another seller's conversation id and an id that matches nothing both answer 404, on the thread page and on the reply.
- Every new class has a sidecar test; `make check` is green.

## Why it matters
The seller side is where a question is answered and where the FAQ loop closes. It is the half of the feature reviewers look at first.

## Discovery notes
- `docs/messaging.md` § "A question becomes a published FAQ" is the flow; the route table there is the contract. Routes go in `routes/seller.php`, behind `auth.seller`:

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/seller/messages` | `seller.messages.index` | Inbox, newest first, with unread counts |
| GET | `/seller/messages/{conversation}` | `seller.messages.show` | Thread; marks it read; offers "Publish as FAQ" when the thread has a listing |
| POST | `/seller/messages/{conversation}` | `seller.messages.store` | Reply |
| GET | `/seller/support` | `seller.support` | Finds or opens the `admin_seller` thread and redirects to it |
| POST | `/seller/orders/{fulfillment}/messages` | `seller.orders.messages` | Finds or opens the `fulfillment` thread |
| GET | `/seller/listings/{listing}/faqs` | `seller.listings.faqs.index` | Published entries, edit form, unpublish |
| POST | `/seller/listings/{listing}/faqs` | `seller.listings.faqs.store` | Publish |
| PUT | `/seller/listings/{listing}/faqs/{faq}` | `seller.listings.faqs.update` | Reword |
| DELETE | `/seller/listings/{listing}/faqs/{faq}` | `seller.listings.faqs.destroy` | Unpublish (deletes the row) |

- Route-model binding then authorize, the way every existing seller route does. `ConversationPolicy::view` is what makes another seller's thread a 404, so the reply route needs the same authorization before it posts — a form request's `authorize()` is where the existing portal puts it.
- The FAQ routes bind two models; nest them so a `{faq}` that is not on `{listing}` answers 404 (`scopeBindings()`, or a relation-scoped binding).
- Form requests: `PostMessageRequest` returning a `MessageBody`, `PublishFaqRequest` / `UpdateFaqRequest` returning a `FaqDraft`. Limits come from the domain constants, not literals in a rules array.
- The nav badge is a `SellerLayoutComposer` bound to `components.layouts.seller` in `AppServiceProvider`, beside the existing `ShopLayoutComposer` binding. It reads the same `Message` scope FEAT-011 landed.
- The "Publish as FAQ" pre-fill is a presentation decision over the thread's messages — the opening message reads as the question, the seller's latest reply as the answer, and `source_message_id` records which. Where that pre-fill lives (a domain function over the thread, a model method, a view model) is yours **(decided at build time)**; keep it out of the Blade file.
- Entry points to add: "Questions & answers" on `seller.listings.show`, "Message the customer" on `seller.orders.show`, "Support" and "Messages" in the layout nav.
- `PUT`/`DELETE` from a Blade form is `@method`. If the REST verbs fight the controller naming, `tests/Arch.php`'s `laravel` preset `ignoring` list is where an exception is recorded — one class at a time, with the reason.
- Risk: the inbox renders a counterpart name per row and `Model::shouldBeStrict()` raises on a lazy load. Eager-load the participants and the subject rows, and count unread with `withCount` rather than per row.

## Related work
- FEAT-010 and FEAT-011. FEAT-013 (storefront) is the other half of the same threads.
