---
id: FEAT-043
type: feature
status: open
created: 2026-09-01
---

# FEAT-043: The storefront's conversation pages feel like a relationship

## Problem
The storefront's messaging pages (`resources/views/shop/messages/*`, `resources/views/shop/listing.blade.php`'s ask section, `app/Http/Controllers/Shop/MessageController.php`, `SupportController.php`, `ListingQuestionController.php`, `OrderMessageController.php`) are hand-styled lists and card stacks with a plain textarea. A visitor with no address can ask a question, `/support` redirects into one untitled thread, there is no way to say what a support message is about or which order it concerns, no reply-to, no sense of a thread being answered and closed.

## Goal
A customer signed in to Art Store reads and writes to makers and to the owners in one warm, legible place, asks about a piece before buying, and knows when a conversation was answered.

## Outcome
- Asking a question on a listing requires a signed-in customer: signed out, the section explains and offers "Sign in to ask" whose magic link returns to the listing; signed in, the form (grow, counter, Cmd/Ctrl+Enter) opens a fresh `listing_question` thread per ask and lands on it.
- `GET /support` (signed in) renders "Talk to us": subject, optional order from the customer's orders (`?order=` preselects), message; `POST` opens the `admin_customer` thread. The order page offers "Contact Art Store about this order" beside the existing "Message the maker".
- The inbox shows each thread with avatar initials, counterpart (the desk reads as "Art Store Support"), kind pill, title or order, preview, date, unread count, and a resolved mark; `?filter=` (`all`, `unread`) and `?status=` (`open` default, `resolved`, `all`) as pills; unknown values answer 400; "Contact support" in the header.
- The thread page shows the title in the display face, the counterpart and their shop, the listing card (image, title, price) or the order link as context, the two-sided transcript (`docs/messaging.md` § The transcript: the customer's own messages on the right in an `accent-soft` panel with the avatar on the outer edge, the other side's on the left on the surface, text left-aligned in both) with day separators, "You" for the customer's own messages, admin messages under the admin's name, per-message "Reply", the quote block, a calm resolved note ("Sybill marked this resolved … reply below if there's anything else"), and the composer in Warm Craft tokens. Replying to a resolved thread reopens it (FEAT-040's rule) and the page says so.
- Warm Craft tokens only (`config/theme.php`, `x-ui.*`), dark mode holds, everything works without JavaScript; the shop layout loads `public/composer.js`.
- `make precommit` green, coverage 100%, feature tests cover the signed-out ask, the support form with and without an order, filters and 400s, reply-to, and the reopen-by-reply path.

## Why it matters
This is where a buyer feels supported by the company and connected to the maker. The pages should read like correspondence, not like a ticketing system.

## Discovery notes
Design of record: `docs/messaging.md` and the canvas artboards "Storefront · Messages", "Storefront · Thread, resolved, composer", "Storefront · Ask a question (both states)", "Storefront · Talk to us". Depends on FEAT-040. Requiring sign-in for the ask reverses the earlier anonymous-ask decision; `docs/messaging.md` records why. The storefront keeps its own partials rather than sharing the seller/admin components.

## Related work
- FEAT-040 (foundation)
- FEAT-011 / FEAT-013 (the first storefront messaging round)
