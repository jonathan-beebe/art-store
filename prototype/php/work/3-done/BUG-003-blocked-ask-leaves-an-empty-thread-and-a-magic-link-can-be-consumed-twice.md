---
id: BUG-003
type: bug
status: resolved
created: 2026-08-23
---

# BUG-003: A blocked customer's ask leaves an empty thread, and a magic link can be consumed twice

## Problem
Opening a listing question and posting its first message are two transactions, so a blocked customer's refused first post leaves an empty `conversations` row (recorded as gap 10 in FEAT-017). Magic-link consumption reads the row then writes `consumed_at` in two statements, so two concurrent verifications of the same token both succeed (Node checks the `UPDATE … WHERE consumed_at IS NULL` row count).

## Goal
Thread opening and link consumption are each one atomic step.

## Outcome
A refused first post leaves no conversation row; the open + first message happen in one transaction; a second concurrent consume of the same magic link is refused, asserted by a test that consumes the same token twice with the row-count check; docs state both.

## Why it matters
Empty threads show up in the seller's inbox; a double-consumable link is a session-fixation primitive.

## Discovery notes
`firstOrCreate` inside the same `DB::transaction` as the post; `MagicLink::whereNull('consumed_at')->update([...])` returning the affected count is the Node shape in Eloquent.

## Related work
- FEAT-013, FEAT-017 (gap 10)
- prototype/node BUG-004

## Working

Both halves landed in one commit.

### The empty thread

`OpenConversationWithMessage` is the new action: `OpenConversation`, the
`post` gate, and `PostMessage` inside one `DB::transaction`. A refused first
post rolls the `conversations` row back with it.

The gate moved from the controller into the action. It had to: `App\Actions`
may open a transaction and `App\Http\Controllers` may not (`tests/Arch.php`
— "controllers do not reach around Eloquent with the DB facade"), so the
refusal could only be inside the transaction if the thing that authorizes is
inside it too. `Gate::forUser($sender)->authorize('post', $conversation)` is
the same check `ShopController::authorizeVisitor` was making, named for a
sender rather than read off a guard, which is what the storefront needs — it
resolves its visitor from a cookie and signs nobody in.

Callers that open a thread and immediately post, all three now routed through
the new action:

| Route | Controller | Had the bug |
| --- | --- | --- |
| `shop.listing.questions` | `Shop\ListingQuestionController` | yes — the blocked-customer refusal |
| `admin.sellers.messages` | `Admin\SellerMessageController` | the same two transactions; no refusal reaches it, since an admin passes `ConversationPolicy::post` and the body is validated by the form request first |
| `admin.customers.messages` | `Admin\CustomerMessageController` | as above |

Callers that only open a thread and redirect to it — `shop.support`,
`seller.support`, `shop.order.messages`, `seller.order.messages` — still call
`OpenConversation` alone and are unchanged. An empty thread is what they are
for: the actor types the first message on the page they land on.

Three storefront tests changed. "opens the thread but refuses the question
while blocked" now asserts no conversation; "opens no second thread when a
blocked visitor asks again" became "leaves both inboxes empty however often a
blocked visitor asks"; and "reads the empty thread as an inbox row with no
preview on both sites" is gone — the row it described cannot exist any more.
A new test walks block → refused ask → lift → the ask landing.

### The double consume

`MagicLink::consume(now)` returns `bool`. It is one
`update ... where consumed_at is null` and reports whether it affected a row;
the controller signs nobody in unless it did. The status read before it still
names the refusal for a link already used or expired, and it is no longer what
decides — two verifications arriving together both read a usable link, and the
write settles which of them gets it. The loser is refused with the same
sentence, logged `magic_link.consume` / `refused` at `info`.

Two tests. `MagicLinkTest` consumes the same row twice through two instances
and asserts `true` then `false` with the stamp unchanged — the row-count check
the Outcome asks for. `MagicLinkVerificationControllerTest` covers the race at
the HTTP boundary: a `MagicLink::retrieved` listener consumes the row through
the query builder right after the request reads it, which leaves the request
holding exactly what the losing side of a real race holds. The request is
refused, no seller row is created, and nobody is signed in.

### Logging

Unchanged in shape: `conversation.open` and `message.post` still tell their own
stories inside the new transaction, and `magic_link.consume` still ends
`refused` at `info` on every refusal.

### Left out

- Nothing about the empty threads the support and fulfillment routes open on
  purpose. They are not the bug.
- No transaction around the rate-limit check. A trip refuses before anything
  is opened, so there is nothing to roll back.

### Numbers

| Gate | Before | After |
| --- | --- | --- |
| Pest | 1793 tests, 4865 assertions | 1799 tests, 4886 assertions |
| Coverage | 100.0% lines | 100.0% lines |
| PHPStan (level max) | 0 errors | 0 errors |
| Pint | clean | clean |
