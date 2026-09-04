---
id: FEAT-059
type: feature
status: open
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
