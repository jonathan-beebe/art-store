---
id: FEAT-042
type: feature
status: resolved
created: 2026-09-01
---

# FEAT-042: The admin site runs a support desk that sees everything

## Problem
The admin Messages tool (`resources/views/admin/messages/*`, `resources/views/components/messaging/*`, `app/Http/Controllers/Admin/MessageController.php`, `SellerMessageController.php`, `CustomerMessageController.php`) shows only threads bound to the signed-in admin's own row, so the second admin sees nothing and neither sees seller ↔ customer conversations. There is no queue of threads waiting on the desk, no resolve, no reply-to, and "Message seller" / "Message customer" open the one untitled thread rather than a new titled one.

## Goal
Anna and Jonathan open Messages and see the whole marketplace's conversations, work the ones waiting on them, and step into a seller ↔ customer thread by opening their own with either side.

## Outcome
- [x] The inbox lists every thread with `?filter=` (`needs-reply` default, `all`, `sellers`, `customers`, `orders`, `questions`) and `?status=` (`open` default, `resolved`, `all`); unknown values answer 400. `needs-reply` is open desk threads whose latest message is not an admin's. Rows carry a kind tag (Seller / Customer / Order / Question), the title or order, a preview, a relative time, the unread dot, and "handled by <admin>" where set. The nav badge counts unread on desk threads only.
- [x] A desk thread shows the title, status pill, the seller or customer with a link to their admin page, the order context with a link, "handled by", a "Mark resolved" / "Reopen" button, the two-sided transcript (`docs/messaging.md` § The transcript: the admin's own messages on the right in a stone-100 panel with a stone-300 edge, the other side's on the left; day separators, per-message "Reply"), the reply quote, and the composer (grow, counter from the constant, Cmd/Ctrl+Enter, reply block with Cancel).
- [x] An oversight thread (seller ↔ customer) names both sides in the header, renders the transcript read-only with a short notice, offers "Message <seller>" and "Message <customer>" that open a new titled desk thread carrying the order (or, for a listing question, nothing — see Working) as context, and does not mark the thread read.
- [x] The seller and customer admin pages' message forms take a subject, an optional order, and a message, and open a fresh titled thread through `OpenThread`.
- [x] The admin layout loads `public/composer.js`; everything works without it. Stone/taupe tint rules from PR #59 hold.
- [x] `make precommit` green, coverage 100% on every touched file, feature tests cover the filters and 400s, oversight read-only, resolve/reopen, reply-to, and both new-thread forms.

## Why it matters
The desk is how the two owners support and form relationships with sellers and customers. Seeing everything, with a queue of what is waiting, is the admin feature the whole subsystem exists to serve.

## Discovery notes
Design of record: `docs/messaging.md` and the canvas artboards "Admin · Desk, needs reply" and "Admin · Oversight thread (read-only)". Depends on FEAT-040 (desk scopes, admin policy, oversight scope). The admin's existing list/detail scaffold (`mode="list"` / `mode="detail"` on `x-layouts.admin`, `cells` slot, `x-admin.cell-footer`) is the shell to stay in.

## Related work
- FEAT-040 (foundation)
- PR #59 (the admin chrome this extends)

## Working

Built on top of FEAT-040's shapes without touching Domain/Actions/Models/Policies:
`ConversationKind`, `ThreadOpening`, `OpenThread`, `PostMessage`, `Resolve/
ReopenConversation`, and the `Conversation`/`Message` scopes were used as-is.

- New: `App\Http\Requests\Admin\MessagesQueryRequest` (filter/status, 400 on
  an unrecognised value, the `LogsQueryRequest` idiom), `OpenSellerThreadRequest`
  / `OpenCustomerThreadRequest` (title + optional own-order context, replacing
  `SendMessageRequest`), `PostMessageRequest::replyToMessageId()`.
- Rewritten: `MessageController` (filtered index, oversight-aware show that
  marks read only where `post` is allowed, reply-to resolution that ignores a
  foreign or bogus id rather than erroring), `SellerMessageController` /
  `CustomerMessageController` (title + optional fulfillment/order context,
  `RateLimitName::ConversationOpen`). New single-action `ResolveConversationController`
  / `ReopenConversationController` — a shared `resolve`/`reopen` pair on
  `MessageController` tripped the `Tests\Arch` preset's one-controller-one-verb
  rule, so they followed the codebase's existing `OrderCancellationController`
  /`LiftCustomerBlockController` shape instead. That rule is the one deviation
  worth naming: the ticket's own routes table reads `[MessageController::class, 'resolve']`,
  the actual routes point at the two new controllers.
- Views: `components/messaging/{kind-tag,avatar,filter-chips,open-thread-form}.blade.php`
  (new), `inbox.blade.php` / `body-form.blade.php` / `thread.blade.php` (rewritten
  for kind tags, the two-column tinted transcript, day separators, reply-to,
  resolve/reopen buttons, and the oversight notice + buttons). `ActorDisplay`
  gained `initialsOf()` for the transcript avatars (`App\Support`, not a
  restricted namespace). `admin/sellers/show.blade.php` and
  `admin/customers/show.blade.php` swapped the old reply-shaped form for
  `x-messaging.open-thread-form`, anchored at `#message-seller-form` /
  `#message-customer-form` and reading `?fulfillment=` / `?order=` to preselect
  the order a "Message …" button carried in.
- Deviation from the doc, reasoned: `ThreadOpening::adminSeller()`'s only
  context column is `fulfillment_id` and `adminCustomer()`'s is `order_id` —
  neither takes a `listing_id`. A `fulfillment`-kind oversight thread's two
  "Message …" buttons carry the fulfillment/order id as designed; a
  `listing_question`-kind oversight thread's buttons carry no context at all
  (nothing in the domain to carry it in), rather than the ticket's "carrying
  the order or listing as context". Widening `ThreadOpening::adminSeller()`
  to take a listing id would be the minimal domain change to close this gap;
  left as a follow-up rather than touched here.
- `show()`'s and `store()`'s own list-pane query reads `filter=all&status=all`
  (every thread, no predicate) rather than the index's own current filter:
  the index has no filter of its own to remember on a bare show/store visit,
  and the desk's `needs-reply` default would leave an oversight thread, and
  any resolved one, with no place in its own pane.
- `make precommit`: **3374 tests passing, 9789 assertions.** `make fresh`
  rebuilds cleanly. `make coverage`: 99.6% project-wide (the pre-existing
  legacy-model gap FEAT-040 already recorded); every file this ticket touched
  or added reads 100.0%.
