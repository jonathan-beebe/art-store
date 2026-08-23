---
id: FEAT-012
type: feature
status: resolved
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

## Working

Re-validated: no route under `routes/seller.php` reached the messaging tables
before this ticket; `seller.messages.*`, `seller.support`,
`seller.orders.messages`, and `seller.listings.faqs.*` all had to be added.

### Decisions

- **`ConversationFactory::forSubject(ConversationSubject $subject)`** added
  per the FEAT-011 review note. It writes the same columns `openFor()` would
  for that subject, so `->forSubject($subject)->create()` never contradicts
  its own `subject_key`. Used throughout the new controller/composer tests
  that need a specific seller+customer+listing combination; `listingQuestion()`
  stays for tests that only need *a* listing thread.
- **`OpenConversation` and `PostMessage` stay two transactions.**
  `seller.orders.messages` (open) and `seller.messages.store` (post) are two
  separate requests already, so the pair only meets in one place:
  `SupportController`/`OrderMessageController`, which open a thread and
  redirect — they never post the first message. Nothing in this ticket calls
  both in one request. Wrapping them would also need a `DB::transaction` call
  from a controller, which `tests/Arch.php` forbids; the fix, if a future
  ticket does compose them in one request, is a small coordinating action
  under `app/Actions/Messaging`, not a controller-level transaction. Left as
  is, since `OpenConversation`'s `firstOrCreate` is idempotent — a failed
  `PostMessage` after a successful open leaves an empty thread, not a
  duplicate or corrupt one.
- **The notification URL is asserted for real.** `seller.messages.show` now
  exists, so `NotifyOfMessageTest`'s "leaves the url null" case moved to a
  customer recipient (`shop.messages.show` still does not exist) and the
  "links to the thread" case dropped its throwaway `Route::get()` in favor of
  the real route, asserting `toArray()['url'] === route('seller.messages.show',
  $conversation)`.
- **"Publish as FAQ" shows whenever the thread has a listing**, not only once
  the seller has answered — matching the route table's "offers... when the
  thread has a listing" rather than gating on a reply existing. `Conversation
  ::faqPrefill(): ?FaqPrefill` (a small `App\Domain\Messaging\FaqPrefill`
  value object) reads the opening message as the question and the seller's
  latest message as the answer, and returns null when either is missing; the
  Blade form renders on `$conversation->listing !== null` and falls back to
  empty fields via `old(..., $faqPrefill?->question)`, so an unanswered
  listing thread still lets the seller compose an entry from scratch.
- **`App\Support\ActorDisplay::nameOf()`** factors out the seller/admin
  `displayName()` vs. customer `name ?? "Customer #{id}"` convention the
  admin site's customer pages already used inline; `Conversation
  ::counterpartName()` (the inbox's "who is this with") and `Message
  ::senderName()` (the thread's "who sent this") both read it, rather than
  each restating the three-way match.
- **Nested FAQ routes use `Route::resource(...)->scoped()`, not
  `->scopeBindings()`** — the latter is a method on `Route`/`RouteRegistrar`,
  not on `PendingResourceRegistration` returned by `Route::resource()`.
  `->scoped()` with no field overrides still sets binding fields for every
  URI parameter, which is what turns on relationship-scoped implicit binding,
  so a `{faq}` not on `{listing}` 404s through route-model binding before the
  controller runs.
- **`PublishFaqRequest`'s `source_message_id` rule** scopes the `exists`
  check to messages whose conversation is about this listing, via a `whereIn`
  subquery — `Rule::exists('messages', 'id')->where(...)`'s callback receives
  a plain `Illuminate\Database\Query\Builder`, which has no `whereHas()`, so
  the relationship check is a subquery against `conversations` instead.
- **Three pre-existing "fixed query count" tests bumped by one**
  (`DashboardControllerTest`, `EarningsControllerTest`, `ListingControllerTest`
  — 5→6, 5→6, 4→5): `SellerLayoutComposer` adds one query to every seller page
  render for the nav's unread-message count.
- **`Admin::platformAdmin()`** added (`self::query()->oldest('id')->first()`)
  for "the first admin by id" `docs/messaging.md` names for both support
  routes; `SupportController` uses it and redirects with an error, no thread
  opened, when it is null.
- Drive-by: `README.md` and `docs/architecture.md` still read "826 tests
  (1887 assertions)" from FEAT-010, never updated across FEAT-011's two
  landings. Brought both to this ticket's numbers while already touching them.

### Verification

`make check` (Pint → PHPStan level max → full Pest suite): Pint clean on 411
files, 0 PHPStan errors, **999 tests passed, 2196 assertions** (from the
941/2056 baseline). `make coverage`: 100.0%. `tests/SidecarsTest` passes with
every new class covered. `php artisan route:list --path=seller` confirms all
nine new/changed route names register under `auth.seller` with the exact
names `docs/messaging.md`'s route table specifies.

### Found, not fixed

- Nothing outside scope. The FAQ index page's per-row edit forms share field
  names (`question`/`answer`) across rows, so a failed validation on one
  row's edit repopulates every row's `old()` value — acceptable for a page
  with one active edit at a time, not filed since it is this ticket's own new
  code rather than a pre-existing defect.

## Review

Probed the route table against `docs/messaging.md`, 404 discipline on both
`{conversation}` verbs and the nested `{faq}`, mark-read timing, the
find-or-open pair, the FAQ lifecycle, the notification URL, and the inbox's
strict-model and query-count behaviour. Nothing in the shipped behaviour was
wrong: another seller's id and a bogus id answer 404 on GET and POST, the form
requests authorize before they validate, `->scoped()` does turn on
relationship-scoped binding for `{faq}`, mark-read runs on the GET alone and
touches only the counterpart's messages, and the notification URL is null for a
customer recipient today and becomes `shop.messages.show` the moment FEAT-013
registers that name.

Changed:

- **`Conversation::withUnreadCountFor(reader)`** — the per-thread badge moved
  from an inline `withCount` in `Seller\MessageController` (with a private
  `unreadByViewer` helper carrying the generics) to a model scope beside
  `withParticipant`, the shape `Listing::withEventCounts` already uses and the
  one `docs/architecture.md` names for counts a page shows. The storefront and
  admin inboxes now have a scope to call rather than a `withCount` to restate.
- **`@property-read int $unread_count`** on `Conversation` — the inbox row read
  an attribute the model never declared, unlike `Listing`'s three count
  properties.
- `str(...)->limit(120)` in the inbox view, replacing the only fully-qualified
  class reference in the Blade tree.
- `docs/messaging.md` names `app/Support/ActorDisplay.php` in its code list and
  the unread-counts diagram names the new scope.

Tests added (5): an inbox holding a `fulfillment` and an `admin_seller` thread
(the eager loads a listing-question-only inbox never exercised); a fixed
six-query inbox render across five threads, pinning the composer at one query
and the eager loads flat; a refused reply leaving the thread unread; a
`{faq}` on the seller's *other* listing answering 404 on `DELETE` (`PUT`
already had this); and `seller.orders.messages` writing the exact
`ConversationSubject::fulfillment(...)` key the storefront's own route will ask
for. Plus a sidecar case for the new scope.

`make check`: Pint clean on 411 files, 0 PHPStan errors, **1004 tests, 2208
assertions**. `make coverage`: 100.0%.

### Review — found, not fixed

Nothing. The shared `question`/`answer` field names across the FAQ index page's
per-row edit forms stand as the ticket recorded them.
