---
id: FEAT-041
type: feature
status: resolved
created: 2026-09-01
---

# FEAT-041: The seller portal's Messages tool becomes first-class

## Problem
The seller's Messages tool (`resources/views/seller/messages/*`, `resources/views/components/seller/messaging/*`, `app/Http/Controllers/Seller/MessageController.php`, `SupportController.php`) lists every thread in one undifferentiated column, shows a bare three-row textarea, offers no way to open a titled support thread, no reply-to, no resolve, no filters, and buries "Publish as FAQ" as a second form under the composer. A seller cannot see which questions are waiting for an answer.

## Goal
A seller opens Messages and sees at a glance what needs them — unanswered questions, order conversations, support — answers with a composer that feels like a real messaging tool, and closes the loop by resolving or publishing.

## Outcome
- [x] The inbox takes `?filter=` (`all`, `unread`, `questions`, `orders`, `support`) and `?status=` (`open` default, `resolved`, `all`) as two rows of chips with counts where cheap; unknown values answer 400; `questions` lists unanswered threads first. Each row shows the counterpart, a kind tag, the title (or the order for fulfillment threads), a one-line preview, a relative time, the unread dot, and a resolved mark.
- [x] The thread header shows the title, the status pill, the counterpart, and the context (the listing with a link, or the order with a link); "Mark resolved" / "Reopen" sit in the header and post to the new routes; "Publish as FAQ" opens a disclosure prefilled from the thread rather than a permanent second form.
- [x] Messages render in a two-sided transcript (`docs/messaging.md` § The transcript): the seller's own on the right in an indigo-50 panel with the avatar on the outer edge, the other side's on the left on the plain surface, text left-aligned inside both; day separators, avatar initials, sender name ("You" for the seller's own), time, and a per-message "Reply" link; a reply shows the quoted message above its body, linking to the original.
- [x] The composer grows with content, submits on Cmd/Ctrl+Enter, shows a live counter against `MessageBody::MAX_LENGTH` (read from the constant, also filling `maxlength`), shows a "Replying to …" block with Cancel when `?reply_to` names a message, and keeps `old()` values on every return.
- [x] `GET /seller/support` renders a new-conversation form (subject, optional order from the seller's fulfillments, message); `POST` opens the `admin_seller` thread through `OpenThread` and lands on it. "New conversation" links there from the inbox header.
- [x] The seller's order detail page keeps "Message buyer" (find-or-open). Below `lg`, the list/detail split still works as the Orders tool does.
- [x] `public/composer.js` exists (grow, counter, Cmd/Ctrl+Enter — ~15 lines, `<script defer>` in the seller layout) and the page works without it.
- [x] `make precommit` green, coverage 100%, feature tests cover filters, resolve/reopen, reply-to, the support form, and the 400s.

## Why it matters
Sellers support and build relationships with their buyers here. A queue that shows what is waiting and a composer that gets out of the way is what makes them answer quickly and warmly.

## Discovery notes
Design of record: `docs/messaging.md` (routes, filters, composer, resolve rules) and the "Art Store Messaging" canvas artboards "Seller · Messages, question thread" and "Seller · New conversation with support" — those are the pixel reference (indigo tokens, `x-seller.list-detail`, the same chip idiom as Orders). Depends on FEAT-040's shapes. `x-seller.list-detail` has no pinned footer slot yet (noted in memory); adding one is in scope if the "Showing N of M" line needs it.

## Related work
- FEAT-040 (foundation)
- PR #58 (the seller portal chrome this extends)

## Working

All outcome boxes are implemented per `docs/messaging.md` and the "Art Store
Messaging" canvas artboards (Main.dc.html, SellerSupport.dc.html).

- `MessagesQueryRequest` reads `?filter=`/`?status=` with an
  `LogsQueryRequest`-style bare 400 on an unrecognised value;
  `MessageController::conversationsQuery()` narrows a shared query by both,
  reused by the index route, the show route's default list pane, and the
  chip counts, so a chip's count and the rows it links to can never
  disagree.
- The inbox row's own line reads `$conversation->title` (a listing
  question's derived summary, a support thread's typed subject), falling
  back to the order for a fulfillment thread; the listing's title moved
  into the preview line's prefix instead ("Divination Tower Vase, Tall ·
  …"), matching the pixel reference more closely than the prior design,
  which showed the listing title where the question now reads.
- The transcript, composer, and support form all read `?reply_to=` /
  `old('reply_to_message_id')` against the thread's already-loaded messages
  collection rather than a fresh query, so a stray or cross-thread id
  resolves to nothing instead of a query or a 500.
- `ResolveConversationController`/`ReopenConversationController` lean on
  `ConversationPolicy::resolve`/`reopen` entirely: a request against a
  thread already in the target state answers 403 before either action's own
  `DomainRuleViolation` guard is ever reached.
- "Publish as FAQ" is a `<details>`/`<summary>` disclosure anchored in the
  thread header's action row, its panel positioned as a popover so opening
  it does not reflow the transcript.
- `x-seller.list-detail`'s pinned-footer gap (noted in memory) was not
  closed: `list-footer` still scrolls with the list rather than being
  pinned, since the seed data never approaches the 50-row window. Left as a
  follow-up if a future ticket's fixtures need it.
- `app/Http/Controllers/Admin/MessageControllerTest.php` (outside this
  lane) needed one fix: its end-to-end "sellers support request" case
  called the old single-step `GET /seller/support` (open-empty-thread,
  redirect); it now posts the form the way a seller does.

`make precommit`: 3358 tests passing, 9763 assertions. `make coverage`:
every file this ticket touched or added reads 100.0% (project-wide total
holds at the pre-existing 99.6%). `make fresh` rebuilds cleanly.
