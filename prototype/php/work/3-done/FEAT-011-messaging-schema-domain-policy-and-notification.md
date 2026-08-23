---
id: FEAT-011
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-011: Messaging schema domain policy and notification

## Problem
Nothing in `prototype/php/src` can hold a message. There are no messaging tables, no conversation model, no rule for who may read or post in a thread, and no notification for a received message. `App\Domain\Customers\CustomerOwnedTables` lists four tables that move on an anonymous-customer merge and conversations are not among them.

## Goal
The messaging tables, the rules that govern them, and the notification a post sends all exist and are covered by tests, with no route yet reaching them.

## Outcome
- A conversation of each of the four kinds can be opened, and asking for the same subject twice yields the same conversation rather than a second one.
- Posting a message appends it to the thread, moves that thread to the top of both participants' inboxes, and leaves the recipient one notification whose link points at the thread on the recipient's own site.
- A message counts as unread for the participant who did not send it until it is marked read; marking a thread read clears exactly those messages and leaves the sender's own untouched.
- Asking whether an actor may read a thread they are not in answers "not found"; asking whether a blocked customer may post answers no while reading still answers yes.
- A message longer than 2000 characters, an FAQ question longer than 500, and an FAQ answer longer than 2000 are each rejected.
- Publishing an FAQ entry creates a row; unpublishing removes it; the entries readable for a listing are exactly the published ones.
- Merging an anonymous customer into a verified one moves that customer's conversations, their sent messages, and any block against them onto the verified customer, and the thread reads correctly afterwards for the verified customer.
- Every new class has a sidecar test; `make check` is green.

## Why it matters
Three site tickets follow and all of them read these rules. Landing the schema, the domain, the policy, and the notification once means the three sites differ only in their pages.

## Discovery notes
- `docs/messaging.md` is the design. § "One thread per subject", § "Who may read, who may post", § "Unread counts", and § "Telling the other side" are the four things this ticket lands.

Tables:

| Table | Columns |
| --- | --- |
| `conversations` | `id`, `kind` (string: `admin_seller` / `admin_customer` / `fulfillment` / `listing_question`), `subject_key` (string), `seller_id` (FK nullable), `customer_id` (FK nullable), `admin_id` (FK nullable), `listing_id` (FK nullable), `fulfillment_id` (FK nullable), `last_message_at` (timestamp), timestamps |
| `messages` | `id`, `conversation_id` (FK, cascade), `sender_type` (string, morph alias), `sender_id`, `body` (text), `sent_at` (timestamp), `read_at` (timestamp, nullable), timestamps |
| `listing_faqs` | `id`, `listing_id` (FK, cascade), `question` (string), `answer` (text), `source_message_id` (FK nullable, `nullOnDelete`), `published_at` (timestamp, not null), timestamps |

Named indexes:

| Index | Table and columns | Why |
| --- | --- | --- |
| `conversations_subject_key_unique` | unique `(subject_key)` | find-or-open under contention |
| `conversations_seller_inbox_index` | `(seller_id, last_message_at)` | the seller inbox query |
| `conversations_customer_inbox_index` | `(customer_id, last_message_at)` | the storefront inbox query |
| `conversations_admin_inbox_index` | `(admin_id, last_message_at)` | the admin inbox query |
| `messages_thread_index` | `(conversation_id, id)` | a thread read in order |
| `messages_unread_index` | `(conversation_id, read_at)` | the unread scope |
| `listing_faqs_listing_index` | `(listing_id, id)` | the published list on a listing |

- `subject_key` exists because SQL treats `null` as distinct in a unique index, so a composite unique over the five nullable id columns would not stop a duplicate `admin_seller` row. The key is the domain's (`ConversationSubject::subjectKey()`); `Conversation::firstOrCreate(['subject_key' => …], […columns…])` is the write. Keep the id columns populated beside it — the inbox queries and the merge read them.
- `app/Domain/Messaging/` holds the pure parts: `ConversationKind` (enum: `participantColumns()`, `subjectColumn()`, `admits(ActorType)`, `topic(...)`), `ConversationSubject` (`final readonly`, private constructor, one named factory per kind, `subjectKey()`, `columns()`), `MessageBody` and `FaqDraft` (value objects carrying `MAX_LENGTH` / `QUESTION_MAX_LENGTH` / `ANSWER_MAX_LENGTH`). No `Illuminate`, no clock, no facades — `tests/Arch.php` enforces it.
- The `sender` on `Message` is a `MorphTo` over the map `AppServiceProvider` enforces, so `sender_type` reads `seller` / `customer` / `admin`.
- The unread rule is one `#[Scope]` method on `Message` (`unreadBy($reader)`): `read_at is null` and not sent by that reader, compared through `$reader->getMorphClass()`. Everything downstream — the per-thread `withCount`, the nav total, `MarkConversationRead`, and FEAT-016's stream — goes through it. A second `where` restating the rule is the failure mode to avoid.
- A `Conversation` scope (`withParticipant($actor)`) keyed off `ActorType::participantColumn()` gives the inbox query and the total one definition of "threads this actor is in".
- `ConversationPolicy`: `view` returns `Response::denyAsNotFound()` for a non-participant; `post` is `view` plus `Customer::canShop()`. `FulfillmentPolicy` already has the `whenAllowed(Response, bool)` shape to copy. Register it the way the other three are (attribute or `Gate::policy`, your call).
- Actions under `app/Actions/Messaging/`: `OpenConversation`, `PostMessage`, `MarkConversationRead`, `PublishListingFaq`, `UpdateListingFaq`, `UnpublishListingFaq`. Every one takes `DateTimeImmutable $now` last and is `final` and invokable.
- `MessagePosted` (final readonly, message + instant) dispatched inside `PostMessage`'s transaction; `NotifyOfMessage` implements `ShouldHandleEventsAfterCommit`; `MessageReceived` reads `config('notifications.channels')` and gets both `toArray()` and `toMail()` from `NotificationMessage::messageReceived(...)`. Register the pair in `AppServiceProvider` — listener discovery is off.
- The notification's URL is the thread on the **recipient's** site. `ActorType::conversationRouteName()` / `inboxRouteName()` keep the mapping in the domain beside `homeRouteName()`; the listener calls `route()`. The route names do not exist until FEAT-012–FEAT-014, so decide how this ticket's tests stand in for them (defining the names now in a route file with placeholder controllers is one option; asserting the route name rather than the URL is another).
- `CustomerOwnedTables::all()` gains `conversations` and `customer_blocks`. Sent messages carry a morph sender, so `MergeAnonymousCustomer` re-points them through the relation the way it already re-points notifications — not through the table list. This is load-bearing: a message the verified customer sent must not read as unread to them afterwards.
- Every model needs a factory with states per meaningful shape (a conversation per kind, a read and an unread message).
- Risk: `Model::shouldBeStrict()` means a lazy load raises. An inbox that reads a counterpart's name per row will trip it — plan for eager loads from the start.

## Related work
- FEAT-010 (the admin participant and `customer_blocks`). FEAT-012, FEAT-013, and FEAT-014 all build on this.

## Working

Landed as designed, with these decisions:

- **`RecipientType` retired, folded into `ActorType`.** The two enums held
  identical values (`seller`/`customer`) once `admin` joined; rather than add
  a third case to `RecipientType` and keep two enums that say the same thing,
  `AppServiceProvider`'s morph map now reads `ActorType::Seller->value`, etc.,
  and `Admin::class` joins it. `RecipientType.php` and its sidecar are
  deleted. `ActorType` gained `participantColumn()`, `conversationRouteName()`,
  and `inboxRouteName()`.
- **Notification URL: asserted the route name, did not add routes.** FEAT-012
  through FEAT-014 have not landed `seller.messages.show` /
  `shop.messages.show` / `admin.messages.show` yet, and this ticket adds no
  routes, controllers, or views. `NotifyOfMessage` guards with
  `Route::has($routeName)` before calling `route()`; the notification's `url`
  is `null` today and starts resolving automatically once a later ticket
  registers the real route under that name — no code change needed here. The
  sidecar proves both branches: one test asserts the null url against the
  app's current (route-less) state, another registers a throwaway route
  in-test (`app('router')->getRoutes()->refreshNameLookups()` is required
  after a runtime `Route::get()->name()` call, since Laravel only rebuilds the
  name lookup table when it compiles the collection for dispatch) to prove the
  url resolves once the name exists.
- **`ConversationSubject`'s optional subject column and id are one nullable
  array**, not two separately-nullable properties. Two independent
  `?string`/`?int` fields let PHPStan see `columns()` as possibly returning a
  null value even though the two are always set together by construction;
  `?array{column: string, id: int}` closes that gap without a cast.
- **`$now` on `UpdateListingFaq` and `UnpublishListingFaq` is unused.** Kept
  per the ticket's "every action takes `DateTimeImmutable $now` last" — a
  reword and an unpublish have no timestamp to stamp today, but the six
  Messaging actions keep one uniform signature.
- **`ConversationKind::topic()`** takes `(?int $orderId, ?string $listingTitle)`;
  the two support kinds ignore both and answer `'Support'`.

Verification: `make check` — 931 tests passed, 2041 assertions (baseline
826/1887), 0 PHPStan errors, Pint clean. `make coverage` — 100.0%.
`tests/SidecarsTest.php`'s exception list is unchanged (still empty).

Found, not fixed: nothing outside scope — no routes/controllers/views were
touched, per the ticket.
