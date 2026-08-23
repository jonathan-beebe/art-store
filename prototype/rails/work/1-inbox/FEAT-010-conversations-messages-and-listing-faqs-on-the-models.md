---
id: FEAT-010
type: feature
status: open
created: 2026-08-23
---

# FEAT-010: Conversations, messages, and listing FAQs on the models

## Problem
No model in `src/app/models/` holds a conversation or a message; the schema (`src/db/schema.rb`) has no `conversations`, `messages`, or `listing_faqs` table. `Notification` (`src/app/models/notification.rb`) has builders for "Item sold" and "Order shipped" only. `Customer::MERGED_ASSOCIATIONS` (`src/app/models/customer.rb:5`) lists the rows that follow an anonymous visitor into a verified account and cannot include threads that do not exist yet.

## Goal
The marketplace has one conversation model that serves every pairing — admin ↔ seller, admin ↔ customer, seller ↔ customer about a fulfillment, seller ↔ customer about a listing — with the rules of who may read, who is the reader of a message, and what is unread decided once on the record.

## Outcome
In the Rails console and in model tests: a conversation of each of the four kinds can be opened with exactly the participants and subject that kind requires, and a malformed one is invalid; opening the same kind + participants + subject twice returns the one existing thread; posting a message appends it, moves the thread to the top of both participants' inboxes, and files an in-app notification for the counterpart pointing at the counterpart's own thread page; a participant's unread count for a thread counts messages the other side sent and nobody has read, and reading the thread as that participant zeroes it without touching the reader's own messages; a non-participant is not a participant and the scope that lists an actor's conversations excludes the thread; a body over 2000 characters is invalid; a listing FAQ row can be published from a message, edited, and unpublished (gone from the table), with question ≤ 500 and answer ≤ 2000; merging an anonymous customer into a verified one moves their conversations and the messages they sent; the full suite stays at 100% line coverage.

## Why it matters
Every site's inbox, thread page, notification, badge, and FAQ reads these rules. If they live on the record there is one definition of "participant", "unread", and "one thread per subject"; three controllers cannot drift.

## Discovery notes
Node's one-table shape — `kind` plus nullable `seller_id`/`customer_id`/`admin_id` and a subject — adapts to Rails as `belongs_to … optional: true` for the three participants and `belongs_to :subject, polymorphic: true, optional: true` (`Listing` or `Fulfillment`); a `KINDS` constant naming each kind's participant pair and subject class gives the validation and the find-or-create one source. `Message` with `belongs_to :sender, polymorphic: true` matches how `Notification` already names its recipient (RFCTR-010), and a polymorphic `has_many :sent_messages, as: :sender` on `Customer` is what lets `Customer#absorb` re-point it through `reflect_on_association(...).foreign_key` unchanged. Candidate record API, in the architecture doc's verb style: `Conversation.open(kind:, subject:, **participants)`, `Conversation.involving(actor)`, `#participant?(actor)`, `#counterpart_of(actor)`, `#post!(sender, body)`, `#read_by!(reader)`, `#unread_count_for(actor)`, `#topic`; `ListingFaq.publish(listing, question:, answer:, source_message:)`; `Notification.new_message(message)` through the existing private `deliver` so RFCTR-014's mailer will cover it. The notification URL is the recipient's path — the seller's thread is under `/seller/messages/:id`, the customer's under `/messages/:id`, the admin's under `/admin/messages/:id`; the model can take the path from the caller or derive it from the recipient's class, either is defensible, but it must not be the sender's path. `Fulfillment` and `Listing` can gain `has_many :conversations, as: :subject`. A `published_at` that is `not null` and an unpublish that deletes the row is the Node decision and keeps the storefront query predicate-free. Routes and controllers are the next ticket; this one is models, migrations, and model tests only — the notification URL can be built with `Rails.application.routes.url_helpers` once FEAT-011's routes exist, so either stub the path helper in a lambda the model takes, or land the `url` on the notification in FEAT-011 and leave `Notification.new_message` taking `url:`.

## Related work
- FEAT-003 (commerce domain core)
- RFCTR-010 (polymorphic notification recipient)
- RFCTR-011 (dissolve domain namespace)
- FEAT-009
- prototype/node/work/3-done/FEAT-007-messaging-center.md
- prototype/node/docs/messaging.md
