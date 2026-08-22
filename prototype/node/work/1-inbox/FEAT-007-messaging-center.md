---
id: FEAT-007
type: feature
status: open
created: 2026-08-22
---

# FEAT-007: Messaging center — admin↔seller, admin↔customer, seller↔customer about fulfillments, listing questions with FAQ publishing

## Problem
No site can send a message. The brief needs admins to message sellers and customers (support, product chat), sellers and customers to chat about an order, and a customer to ask a question on a listing that the seller answers and can publish as part of the listing's FAQ.

## Goal
Every actor has an inbox and can reach the right counterpart about the right subject, and a good answer to one customer becomes an FAQ for the next.

## Outcome
- Inboxes at `/seller/messages`, `/messages`, `/admin/messages` list the actor's conversations newest first with unread counts; a thread page shows messages and a reply form.
- Admin starts a conversation from a seller's or a customer's admin page; a seller opens "Support" from the portal; a customer opens "Contact support" from their account page. Each pair has one open thread reused on the next message.
- A fulfillment page (seller portal and storefront order page) links to the thread for that fulfillment between that seller and that customer.
- The storefront listing page has "Ask the seller a question"; an anonymous customer can ask. The seller sees it in the inbox, replies, and can "Publish as FAQ" which adds the question + answer to the listing page for everyone; the seller can edit or unpublish it.
- Posting a message notifies the recipient (in-app notification with a link).
- Access is a pure predicate over participants; a non-participant answers 404; a blocked customer cannot post.
- Conversations re-point on anonymous-customer merge.
- Integration tests cover each pairing, FAQ publishing appearing on the storefront, and the access refusal.

## Why it matters
Messaging and the FAQ loop are the two features added to this prototype's scope after the earlier spikes; the reviewers will look for them specifically.

## Discovery notes
`docs/architecture.md` → Messaging fixes the tables and kinds. Keep one `conversations` table; a `kind` plus nullable participant and subject columns beats four tables.
- Core: `conversationAccess(conversation, actor)`, `findOrOpenConversation` shape as a pure decision over existing rows, unread counts as a fold.
- Actions under `app/actions/messaging/`: open conversation, post message (notifies), mark thread read, publish / update / unpublish FAQ.
- Each site gets `messages` routes under its own `app/sites/<site>/routes/`; add the nav link in each layout and the entry points on the listing page, order pages, fulfillment page, account pages. This ticket runs after FEAT-004/005/006 land, so those files exist.
- Extend FEAT-002's merge table list with `conversations.customer_id`.

## Related work
- `__local__/retro.md` item 7 (one messaging port) — notifications and messages share the delivery port.
