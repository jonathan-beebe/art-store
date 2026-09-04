---
id: FEAT-059
type: feature
status: resolved
created: 2026-09-03
---

# FEAT-059: A thread shows who the customer is beside the words

## Problem
The seller thread page (`resources/views/components/seller/messaging/thread.blade.php`) is the transcript and the composer. The seller answering "is this a good customer?", "which order is this about?", or "have we talked before?" leaves the page to find out.

## Goal
A seller reading a conversation sees the customer's history with them, the piece or order the thread is about, and their other conversations, without leaving the thread.

## Outcome
- The thread pane gains a 320px context rail at `xl` and up (stacked under the transcript below that): the customer's avatar, name, and email; orders and spend with this seller, favorites, conversations, since; a View customer link. Support threads show the desk instead of a customer.
- "About this piece" shows the listing's cover, title, price, and stock for a listing question; "About this order" shows the order's item line, status badge, id, subtotal, and placed date for a fulfillment thread; each opens its page.
- "Other conversations" lists the customer's other threads with this seller, newest first, each opening in the same pane.
- Nothing about the transcript, the composer, resolve, reopen, or Publish as FAQ changes. `make precommit` green; `make check` green before the PR.

## Why it matters
The brief calls Messages "command central … collecting all correspondence with the customer". The rail is what makes a thread a place where the seller knows who they are talking to.

## Discovery notes
- Numbers come from FEAT-054's `SellerCustomers` (one customer's aggregates); the listing and order facts are already eager-loaded on the conversation.
- The rail is a component (`x-seller.context-rail`) fed with small readonly values, rendered inside the existing `x-seller.list-detail` detail slot; the canvas Messages artboard has the layout and copy.
- Keep the seller inbox rows and domain tabs from FEAT-050 untouched.

## Related work
- FEAT-054, FEAT-050 (inbox domains), PR #62 (messaging v2)
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Messages)

## Working

Design decided before the first test:

- `App\Seller\ThreadContext::forSeller(Seller, Conversation)` is the rail's
  one read: the counterpart's identity, FEAT-054's `CustomerRow` where the
  counterpart has bought, the listing or the parcel the thread is about, and
  their other threads with this seller. The `FeedScope` idiom — a readonly
  value object in `App\Seller` with a named constructor that reads.
- `x-seller.context-rail` renders it; the thread component gains one prop.
- Privacy: a buyer's numbers and their email show because an order carried
  them. A visitor who has only asked a question shows a name and nothing else.

### What landed

- `App\Seller\ThreadContext` and `x-seller.context-rail`: identity, the
  buyer's numbers with this seller and a View customer link, About this
  piece, About this order, and other conversations.
- The rail is 320px at `xl` and stacks under the transcript below it, inside
  the thread component's own pane; `seller/messages/show.blade.php` passes
  one new prop.
- `docs/seller-portal.md` gains a Messages section.

### Decided along the way

- The email in the rail is the `CustomerRow`'s, so it shows for a buyer and
  reads "No email" for a visitor who has only asked a question. The seller
  sees an address because an order carried it; a question does not carry one.
- A support thread shows the desk and no other conversations, which is what
  a desk thread has: no customer.
- An order card shows for any thread naming one of this seller's parcels,
  which includes a desk thread raised over an order.

### Left alone

- The rail scrolls with the transcript rather than on its own. The
  list-detail scaffold gives the detail pane one scroll container, and
  splitting it would touch every seller list/detail screen.
- The transcript, the composer, resolve, reopen, Publish as FAQ, and the
  FEAT-050 inbox rows and domain tabs are untouched.

### Gate

`make precommit` green on every commit; `make check` green before the PR.

### Review pass

Coordinator review, merge after fixes. What changed:

- The rail's order card loads the order's items narrowed to this seller, so
  a two-seller order can no longer name the other seller's piece; the
  listing's pictures load with it, so the card's cover is in hand.
  `ThreadContextTest` carries the two-seller case.
- `App\Seller\ThreadLink` carries a thread's title, link, and how long ago
  it was spoken in, built with the clock `MessageController` passes.
  `x-seller.context-rail` calls no clock and reads no models for its list.
- `ConversationKind::tagLabel()`/`tagTint()` and
  `App\Domain\Seller\Initials::of()` replaced the two mappings this ticket
  had left in Blade and duplicated in the domain.
- Comments and the doc section state the positive.
