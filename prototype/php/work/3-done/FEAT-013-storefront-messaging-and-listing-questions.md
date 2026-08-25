---
id: FEAT-013
type: feature
status: resolved
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

| Method | Path                                                  | Name                     | Purpose                                                |
| ------ | ----------------------------------------------------- | ------------------------ | ------------------------------------------------------ |
| GET    | `/messages`                                           | `shop.messages.index`    | Inbox                                                  |
| GET    | `/messages/{conversation}`                            | `shop.messages.show`     | Thread; marks it read; no reply form while `post` is   |
|        |                                                       |                          | denied                                                 |
| POST   | `/messages/{conversation}`                            | `shop.messages.store`    | Reply                                                  |
| POST   | `/art/{listing:slug}/questions`                       | `shop.listing.questions` | Ask the seller; lands on the thread                    |
| GET    | `/support`                                            | `shop.support`           | Finds or opens the `admin_customer` thread             |
| POST   | `/orders/{order}/fulfillments/{fulfillment}/messages` | `shop.order.messages`    | Finds or opens the `fulfillment` thread                |

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

## Working

Re-validated: no route under `routes/shop.php` reached the messaging tables
before this ticket; `shop.messages.*`, `shop.listing.questions`,
`shop.support`, and `shop.order.messages` all had to be added.

### Decisions

- **`MessageController`, `SupportController`, `OrderMessageController` mirror
  their seller counterparts almost line for line**, swapping
  `$this->authorize()`/`Gate::inspect()` for `$this->authorizeVisitor()`/
  `Gate::forUser($this->visitor())->inspect()` per `ShopController`'s
  visitor-is-middleware-resolved shape. No FAQ-publish section on the
  storefront's thread view — publishing stays a seller-only action.
- **`AskSellerRequest::authorize()` returns a `Response`** the same way
  `PublishFaqRequest` does, checking `$this->listing()->status
  ->isOnStorefront()` against the route-bound listing — the ownership-before-
  validation ordering `docs/architecture.md` describes (a `FormRequest`
  validates only after `authorize()` allows), so a draft or archived listing
  404s even when the submitted body would otherwise fail validation. The
  block check can't live there, because the conversation a listing question
  posts to does not exist until the controller opens it.
- **`ListingQuestionController` opens the conversation, then calls
  `$this->authorizeVisitor('post', $conversation)` before posting** — the same
  `ConversationPolicy::post` a reply goes through, so a blocked customer's
  question is refused with the policy's words. `OpenConversation` runs first
  because there is no conversation to authorize against until it does; a
  blocked customer's ask therefore opens an empty thread rather than none,
  the same idempotent-open tradeoff FEAT-012 accepted for a failed reply.
- **The merge test lives in `ListingQuestionControllerTest`**: a visitor with
  no cookie asks a question, a *different* customer already holds the email
  the visitor later verifies, and the magic link's `MergeAnonymousCustomer`
  folds the anonymous thread onto the existing account (asserted by
  `Conversation::sole()->id` staying the same row while `customer_id` moves,
  and `Message::unreadBy($verified)` reading zero for the visitor's own
  question). The first attempt at this test verified an address nobody held
  yet, which claims the anonymous row in place rather than merging it into a
  second account — not a bug, just the wrong fixture for what "merge" means.
- **Watch-item #2 resolved by moving, not weakening**: `NotifyOfMessageTest`'s
  "leaves the url null" case now sends to an `admin` recipient over an
  `admin_seller` thread, since `admin.messages.show` still has no route. Added
  a matching customer-side case (`shop.messages.show` now resolves to a real
  URL) beside the existing seller one, so both sites this ticket did not
  break stay asserted for real rather than by absence.
- **Watch-item #4 held by construction**: `OrderMessageController` builds
  `ConversationSubject::fulfillment($fulfillment->seller_id, $order
  ->customer_id, $fulfillment->id)` — the same (seller, customer, fulfillment)
  argument order `seller.orders.messages` uses — pinned by a test asserting
  the exact `subjectKey()` match, mirroring FEAT-012's own probe for this.
- **No fixed query-count test needed adjusting** (watch-item #4's other
  half): grepping the storefront test tree found no
  `expectsDatabaseQueryCount` assertions before this ticket, so the new
  `unreadMessageCount` query on `ShopLayoutComposer` had nothing pinned to
  move.
- Listing page: the ask form and the published Q&A list are plain sections
  under the existing article, not a new component — matching the storefront's
  existing hand-rolled-Tailwind-per-page style rather than seller's shared
  Blade partials.

### Verification

`make check` (Pint → PHPStan level max → full Pest suite): Pint clean on 423
files, 0 PHPStan errors, **1043 tests passed, 2302 assertions** (from the
1004/2208 baseline). `make coverage`: 100.0%. `tests/SidecarsTest` passes with
every new class covered. `php artisan route:list` confirms `shop.messages.*`,
`shop.listing.questions`, `shop.support`, and `shop.order.messages` all
register with the exact names and methods `docs/messaging.md`'s route table
specifies, inside `customer.identity` and outside `auth.customer`.
`GuardedRoutesTest` needed no changes: none of the new routes carry
`auth.customer`.

### Found, not fixed

- Nothing outside scope.

## Review

The route table, the authorization shape, and the anonymous path match
`docs/messaging.md`: all six routes sit inside `customer.identity` and outside
`auth.customer`, `shop.order.messages` scopes its fulfillment to the order,
and every read and write settles ownership through
`ConversationPolicy::view`'s `denyAsNotFound`. Probed and held: an anonymous
POST mints the identity cookie, opens the thread, and notifies the seller with
the seller-side URL; a draft or archived listing 404s before a thread exists;
another customer's conversation, order, and fulfillment all 404 on GET and
POST with no message row written; support finds the first admin by id, reuses
the thread on a second visit, and flashes with no admin seeded; the merge test
asserts the row survives on the verified account with its `subject_key`
rewritten and the visitor's own question reading as read.

Changed in review. The nav total was restated in both layout composers —
`unreadBy` plus a `whereHas('conversation', withParticipant)` nobody's test
pinned, so the clause could be deleted with the suite still green. It is now
`Message::unreadInInboxOf`, one scope over the two existing definitions, read
by both composers and covered by a sidecar case that counts a stranger's
thread out. Six tests added: the seller is notified by an anonymous ask, a
blocked visitor's ask tells nobody and opens exactly one empty thread however
many times they try, that empty thread renders as a row with a name, a topic,
and no preview on both inboxes, the POST mints the identity cookie, a blocked
visitor can still favorite, and one walk carries a question from the
storefront to the seller's reply and back to the visitor's badge and thread.

The empty thread a blocked customer's ask leaves behind is accepted for the
prototype: it renders on both inboxes as a named row with no preview, it stays
one row, and nothing is sent about it.

`make check`: Pint clean on 423 files, 0 PHPStan errors, **1049 tests, 2322
assertions**. `make coverage`: 100.0%.

### Found, not fixed

- `SellerLayoutComposerTest` and `ShopLayoutComposerTest` still pin only their
  own actor's count; the stranger's-thread case lives on `MessageTest` beside
  the scope. Enough to hold the rule, one level below the composers.

