---
id: FEAT-043
type: feature
status: resolved
created: 2026-09-01
---

# FEAT-043: The storefront's conversation pages feel like a relationship

## Problem
The storefront's messaging pages (`resources/views/shop/messages/*`, `resources/views/shop/listing.blade.php`'s ask section, `app/Http/Controllers/Shop/MessageController.php`, `SupportController.php`, `ListingQuestionController.php`, `OrderMessageController.php`) are hand-styled lists and card stacks with a plain textarea. A visitor with no address can ask a question, `/support` redirects into one untitled thread, there is no way to say what a support message is about or which order it concerns, no reply-to, no sense of a thread being answered and closed.

## Goal
A customer signed in to Art Store reads and writes to makers and to the owners in one warm, legible place, asks about a piece before buying, and knows when a conversation was answered.

## Outcome
- [x] Asking a question on a listing requires a signed-in customer: signed out, the section explains and offers "Sign in to ask" whose magic link returns to the listing; signed in, the form (grow, counter, Cmd/Ctrl+Enter) opens a fresh `listing_question` thread per ask and lands on it.
- [x] `GET /support` (signed in) renders "Talk to us": subject, optional order from the customer's orders (`?order=` preselects), message; `POST` opens the `admin_customer` thread. The order page offers "Contact Art Store about this order" beside the existing "Message the maker".
- [x] The inbox shows each thread with avatar initials, counterpart (the desk reads as "Art Store Support"), kind pill, title or order, preview, date, unread count, and a resolved mark; `?filter=` (`all`, `unread`) and `?status=` (`open` default, `resolved`, `all`) as pills; unknown values answer 400; "Contact support" in the header.
- [x] The thread page shows the title in the display face, the counterpart and their shop, the listing card (image, title, price) or the order link as context, the two-sided transcript (`docs/messaging.md` § The transcript: the customer's own messages on the right in an `accent-soft` panel with the avatar on the outer edge, the other side's on the left on the surface, text left-aligned in both) with day separators, "You" for the customer's own messages, admin messages under the admin's name, per-message "Reply", the quote block, a calm resolved note ("Sybill marked this resolved … reply below if there's anything else"), and the composer in Warm Craft tokens. Replying to a resolved thread reopens it (FEAT-040's rule) and the page says so.
- [x] Warm Craft tokens only (`config/theme.php`, `x-ui.*`), dark mode holds, everything works without JavaScript; the shop layout loads `public/composer.js`.
- [x] `make precommit` green, coverage 100%, feature tests cover the signed-out ask, the support form with and without an order, filters and 400s, reply-to, and the reopen-by-reply path.

## Why it matters
This is where a buyer feels supported by the company and connected to the maker. The pages should read like correspondence, not like a ticketing system.

## Discovery notes
Design of record: `docs/messaging.md` and the canvas artboards "Storefront · Messages", "Storefront · Thread, resolved, composer", "Storefront · Ask a question (both states)", "Storefront · Talk to us". Depends on FEAT-040. Requiring sign-in for the ask reverses the earlier anonymous-ask decision; `docs/messaging.md` records why. The storefront keeps its own partials rather than sharing the seller/admin components.

## Related work
- FEAT-040 (foundation)
- FEAT-011 / FEAT-013 (the first storefront messaging round)

## Working

All outcome boxes are built per `docs/messaging.md` and the four
`ShopInbox`/`ShopThread`/`ShopAsk`/`ShopSupport` artboards. Landed as three
commits on `php/messaging-shop`:

- `MessageController` gains `?filter=`/`?status=` (via a new
  `ShopMessagesIndexRequest`, 400 on an unrecognised value), `?reply_to=`
  quoting on the thread, and the reopen-by-reply flash; `show.blade.php` and
  `index.blade.php` are rewritten for the transcript/inbox design.
  `SupportController` is split into `show`/`store` (was one placeholder
  `__invoke` that opened an untitled thread on GET); `POST /support` is a
  new route. `ListingPagePresenter::forShop` gains `isSignedIn`
  (`Auth::guard('customer')->check()`, distinct from the cookie identity).
- New: `App\Http\Requests\Shop\ShopMessagesIndexRequest`, `SupportRequest`,
  `App\Support\Shop\ConversationKindLabel` (the "Question"/"Order"/"Support"
  pill word — a storefront-only presentational read of `ConversationKind`),
  `components/shop/messaging/{composer,reply-quote}.blade.php`,
  `shop/support.blade.php`.
- **3351 tests, 9744 assertions, `make precommit` and `make fresh` green.**
  Every touched/added file reads 100% line coverage; project-wide coverage
  sits at 99.6% (the same pre-existing legacy-model gap FEAT-040 noted, not
  touched here).

**Deviations from the doc, both storefront-only presentational choices, no
Domain/Actions/Models/Policies change:**
- The inbox row and the thread header show a seller's own `name` (falling
  back to `displayName()`) rather than `Conversation::counterpartName()`'s
  shop-name-first read, so a maker is named the way the artboards show
  ("Sybill Trelawney", not "Trelawney's Tower Studio") — `counterpartName()`
  itself is unchanged and still what the seller/admin sites read.
- "Contact Art Store about this order" sits once near the top of the order
  page beside "Cancel this order" rather than repeated inside every
  fulfillment's "Message the seller" row, since it is an order-level action
  and an order can hold more than one fulfillment.

**Outside this lane:** `app/Http/Controllers/Admin/MessageControllerTest.php`
had one scenario built on the old `GET /support` auto-opening a thread and
redirecting; updated it to `POST` the new form fields, since `/support` now
renders a form rather than opening on GET. No other admin/seller file
touched.
