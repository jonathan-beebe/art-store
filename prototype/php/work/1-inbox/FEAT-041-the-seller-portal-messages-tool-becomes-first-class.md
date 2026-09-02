---
id: FEAT-041
type: feature
status: open
created: 2026-09-01
---

# FEAT-041: The seller portal's Messages tool becomes first-class

## Problem
The seller's Messages tool (`resources/views/seller/messages/*`, `resources/views/components/seller/messaging/*`, `app/Http/Controllers/Seller/MessageController.php`, `SupportController.php`) lists every thread in one undifferentiated column, shows a bare three-row textarea, offers no way to open a titled support thread, no reply-to, no resolve, no filters, and buries "Publish as FAQ" as a second form under the composer. A seller cannot see which questions are waiting for an answer.

## Goal
A seller opens Messages and sees at a glance what needs them — unanswered questions, order conversations, support — answers with a composer that feels like a real messaging tool, and closes the loop by resolving or publishing.

## Outcome
- The inbox takes `?filter=` (`all`, `unread`, `questions`, `orders`, `support`) and `?status=` (`open` default, `resolved`, `all`) as two rows of chips with counts where cheap; unknown values answer 400; `questions` lists unanswered threads first. Each row shows the counterpart, a kind tag, the title (or the order for fulfillment threads), a one-line preview, a relative time, the unread dot, and a resolved mark.
- The thread header shows the title, the status pill, the counterpart, and the context (the listing with a link, or the order with a link); "Mark resolved" / "Reopen" sit in the header and post to the new routes; "Publish as FAQ" opens a disclosure prefilled from the thread rather than a permanent second form.
- Messages render in a transcript with day separators, avatar initials, sender name, time, and a per-message "Reply" link; a reply shows the quoted message above its body, linking to the original.
- The composer grows with content, submits on Cmd/Ctrl+Enter, shows a live counter against `MessageBody::MAX_LENGTH` (read from the constant, also filling `maxlength`), shows a "Replying to …" block with Cancel when `?reply_to` names a message, and keeps `old()` values on every return.
- `GET /seller/support` renders a new-conversation form (subject, optional order from the seller's fulfillments, message); `POST` opens the `admin_seller` thread through `OpenThread` and lands on it. "New conversation" links there from the inbox header.
- The seller's order detail page keeps "Message buyer" (find-or-open). Below `lg`, the list/detail split still works as the Orders tool does.
- `public/composer.js` exists (grow, counter, Cmd/Ctrl+Enter — ~15 lines, `<script defer>` in the seller layout) and the page works without it.
- `make precommit` green, coverage 100%, feature tests cover filters, resolve/reopen, reply-to, the support form, and the 400s.

## Why it matters
Sellers support and build relationships with their buyers here. A queue that shows what is waiting and a composer that gets out of the way is what makes them answer quickly and warmly.

## Discovery notes
Design of record: `docs/messaging.md` (routes, filters, composer, resolve rules) and the "Art Store Messaging" canvas artboards "Seller · Messages, question thread" and "Seller · New conversation with support" — those are the pixel reference (indigo tokens, `x-seller.list-detail`, the same chip idiom as Orders). Depends on FEAT-040's shapes. `x-seller.list-detail` has no pinned footer slot yet (noted in memory); adding one is in scope if the "Showing N of M" line needs it.

## Related work
- FEAT-040 (foundation)
- PR #58 (the seller portal chrome this extends)
