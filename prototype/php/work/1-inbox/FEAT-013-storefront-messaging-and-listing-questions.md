---
id: FEAT-013
type: feature
status: open
created: 2026-08-23
---

# FEAT-013: Storefront messaging and listing questions

## Problem
The seller can read and answer threads after FEAT-012, but the storefront has no way to start one. A shopper cannot ask about a listing, read a reply, contact support, or message a seller about an order, and the published FAQ entries a seller creates are visible to nobody.

## Goal
A shopper — signed in or not — asks a seller a question, reads the answer, and sees the published answers on the listing page.

## Outcome
- The listing page has "Ask the seller a question". A visitor who has never signed in can ask; asking lands on the thread with the question in it.
- The listing page lists the seller's published questions and answers for that listing.
- `/messages` lists the visitor's threads newest first with unread counts; opening one shows the messages and a reply box, and clears the unread count.
- "Contact support" on the account page opens the customer's thread with the platform; a second visit lands on the same thread.
- The order page has "Message the seller" per fulfillment, which lands on the thread for that order and seller; a second visit lands on the same thread.
- A blocked customer opens their threads and reads them with no reply box, and a submitted reply is refused.
- A Messages link with the unread count is in the storefront nav on every page, including pages that require nobody.
- Somebody else's conversation id and an id that matches nothing both answer 404, on the thread page and on the reply.
- A visitor who asks a question anonymously and then verifies an email finds the thread on their verified account.
- Every new class has a sidecar test; `make check` is green.

## Why it matters
The question that becomes an FAQ starts here, and it has to start without a sign-in wall — an anonymous shopper is the common case on a storefront.

## Discovery notes
- Routes go in `routes/shop.php`, inside the `customer.identity` group and **outside** `auth.customer` — an anonymous visitor is a participant:

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/messages` | `shop.messages.index` | Inbox |
| GET | `/messages/{conversation}` | `shop.messages.show` | Thread; marks it read; no reply form while `post` is denied |
| POST | `/messages/{conversation}` | `shop.messages.store` | Reply |
| POST | `/art/{listing:slug}/questions` | `shop.listing.questions` | Ask the seller; lands on the thread |
| GET | `/support` | `shop.support` | Finds or opens the `admin_customer` thread |
| POST | `/orders/{order}/fulfillments/{fulfillment}/messages` | `shop.order.messages` | Finds or opens the `fulfillment` thread |

- The storefront authorizes through `ShopController::authorizeVisitor()` and `Gate::forUser($this->visitor())`, not `$this->authorize()` — the visitor is middleware-resolved, not signed in on a guard. Blade asks with `@visitorCan('post', $conversation)`; that directive is registered in `AppServiceProvider` already.
- `/orders/{order}/fulfillments/{fulfillment}/messages` mirrors the existing `order.delivered` route — use `scopeBindings()` the same way so a fulfillment on another order 404s.
- The question route binds the listing by slug and must go through the same storefront-visibility check the listing page uses, so a draft or archived listing 404s rather than opening a thread.
- The read-only thread for a blocked customer is the policy doing its job: `@visitorCan('post', …)` false means no form, and the form request authorizing again means a hand-rolled POST is refused with the policy's words. No `if` in the controller.
- `ShopLayoutComposer` gains the unread message count beside `cartItemCount` and `unreadNotificationCount`. It already returns early when there is no visitor, which is what `/login` needs.
- Published FAQ entries render on `shop.listing` with no predicate — a row exists only while published.
- The merge test belongs here: ask anonymously, verify by magic link, find the thread on the verified account with the message reading as the visitor's own.
- Risk: `tests/Pest.php` binds `app/Http/Controllers/Shop` to `Tests\StorefrontTestCase`, which pins a visitor by cookie (`arriveAs`). New sidecars under `Shop/` and `Requests/Shop/` inherit that; a test that asks anonymously needs no sign-in at all.

## Related work
- FEAT-010, FEAT-011, FEAT-012. FEAT-002 (anonymous identity and merge) is the reason the anonymous ask works.
