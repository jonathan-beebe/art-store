---
id: FEAT-007
type: feature
status: resolved
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

## Working

### Tables (migration `20260823000008-create-messaging.ts`, row types in `app/db/commerce-schema.ts`)

| Table | Columns |
| --- | --- |
| `conversations` | `id`, `kind` (`admin_seller` \| `admin_customer` \| `fulfillment` \| `listing_question`), `seller_id?`, `customer_id?`, `admin_id?`, `listing_id?`, `fulfillment_id?`, `created_at`, `last_message_at` |
| `messages` | `id`, `conversation_id`, `sender_type` (`seller` \| `customer` \| `admin`), `sender_id`, `body`, `sent_at`, `read_at?` |
| `listing_faqs` | `id`, `listing_id`, `question`, `answer`, `source_message_id?`, `published_at` |

Indexes: one per participant column paired with `last_message_at` (an inbox is
`where <participant> = ? order by last_message_at desc`), `(kind, listing_id,
fulfillment_id)` for the find-or-open lookup, `(conversation_id, id)` on
`messages`, `(listing_id, id)` on `listing_faqs`.

### Core (`app/core/messaging/`)

| Module | Exports |
| --- | --- |
| `conversation-kind.ts` | `CONVERSATION_KINDS`, `ConversationKind`, `isConversationKind`, `participantColumn`, `participantColumnsOf`, `subjectColumnOf`, `admitsActor` |
| `conversation-subject.ts` | `ConversationSubject`, `ConversationOpening`, `conversationSubject`, `missingConversationParts`, `isSameConversationSubject` |
| `conversation-access.ts` | `ConversationActor`, `ConversationParticipants`, `ConversationAccess`, `conversationAccess`, `isConversationParticipant`, `otherParticipants` |
| `conversation-plan.ts` | `ConversationPlan`, `planConversation` |
| `conversation-path.ts` | `conversationPath`, `inboxPath` |
| `conversation-topic.ts` | `conversationTopic` |
| `participant-name.ts` | `customerName` |
| `unread-messages.ts` | `ReadMarker`, `isUnreadBy`, `unreadCountsByConversation`, `totalUnreadMessages` |
| `message-body.ts` | `MESSAGE_BODY_MAX_LENGTH` (2000), `messageBodyError`, `parseMessageBody` |
| `faq-draft.ts` | `FAQ_QUESTION_MAX_LENGTH` (500), `FAQ_ANSWER_MAX_LENGTH` (2000), `FaqDraftFields`, `FaqDraftErrors`, `FaqDraft`, `faqDraftErrors`, `parseFaqDraft` |

### Action signatures (`app/actions/messaging/`)

```ts
openConversation(context, { kind, sellerId?, customerId?, adminId?, listingId?, fulfillmentId? }): Promise<Conversation>
postMessage(context, { conversationId, sender: { type, id }, body }): Promise<Message>
markConversationRead(context, { conversationId, reader: { type, id } }): Promise<number>
publishListingFaq(context, { listingId, draft, sourceMessageId? }): Promise<ListingFaq>
updateListingFaq(context, { faqId, draft }): Promise<ListingFaq>
unpublishListingFaq(context, faqId): Promise<void>

inboxConversations({ db }, actor): Promise<readonly InboxConversation[]>
unreadMessageCount({ db }, actor): Promise<number>
conversationThread({ db }, { conversationId, actor }): Promise<ConversationThread | null>
listingFaqs({ db }, listingId): Promise<readonly ListingFaq[]>
findListingFaq({ db }, { faqId, listingId }): Promise<ListingFaq | null>
conversationActor({ db }, actor): Promise<ConversationActor>
conversationTopics({ db }, conversations): Promise<ReadonlyMap<number, string>>
conversationTopicOf({ db }, conversation): Promise<string>
participantNames({ db }, conversations): Promise<ParticipantNames>
counterpartName(conversation, actor, names): string
senderName(message, names): string
```

### Routes

Seller portal (`app/sites/seller/routes/messages.ts`, `faqs.ts`, `orders.ts`), all behind `requireSeller`:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/seller/messages` | Inbox: counterpart, topic, preview, unread count, newest first |
| GET | `/seller/messages/:id` | Thread; marks it read; offers "Publish as FAQ" when the thread has a listing |
| POST | `/seller/messages/:id` | Reply |
| GET | `/seller/support` | Finds or opens the `admin_seller` thread and redirects to it |
| POST | `/seller/orders/:id/messages` | `:id` is a `fulfillments.id`; finds or opens the `fulfillment` thread |
| GET | `/seller/listings/:id/faqs` | Published entries with an edit form and an unpublish button |
| POST | `/seller/listings/:id/faqs` | Publish |
| POST | `/seller/listings/:id/faqs/:faqId` | Update |
| POST | `/seller/listings/:id/faqs/:faqId/unpublish` | Unpublish |

Storefront (`app/sites/shop/routes/messages.ts`), inside `storefrontRoutes`, no verified-customer guard:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/messages` | Inbox |
| GET | `/messages/:id` | Thread; marks it read; the reply form is absent while `mayPost` is false |
| POST | `/messages/:id` | Reply |
| POST | `/art/:slug/questions` | Ask the seller — anonymous customers included; lands on the new thread |
| GET | `/support` | Finds or opens the `admin_customer` thread |
| POST | `/orders/:id/fulfillments/:fulfillmentId/messages` | Finds or opens the `fulfillment` thread |

Admin site (`app/sites/admin/routes/messages.ts`), inside `adminConsoleRoutes`:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/admin/messages` | Inbox |
| GET | `/admin/messages/:id` | Thread; marks it read |
| POST | `/admin/messages/:id` | Reply |
| POST | `/admin/sellers/:id/messages` | "Message seller" from the seller page |
| POST | `/admin/customers/:id/messages` | "Message customer" from the customer page |

A conversation id naming a thread the actor is not in, and a non-numeric id, answer 404 on
every read and write above — the storefront through its shared not-found page, the other two
sites as plain text.

Entry points added to existing pages: "Questions & answers" on the seller's listing page,
"Message the customer" on the seller's fulfillment page, "Message the seller" per fulfillment
on the storefront order page, "Ask the seller a question" and the published FAQ list on
`/art/:slug`, "Contact support" on `/account`, "Message seller" / "Message customer" on the
admin's seller and customer pages, and a Messages link with an unread badge in all three
layouts.

### Decisions

- **One `read_at` per message, not a per-participant marker.** A conversation of every kind
  has exactly two participants, so the reader of a message is always the participant who did
  not send it. `isUnreadBy` is the single place that says so, and both the fold and
  `markConversationRead` read it rather than repeating the rule in SQL.
- **A `listing_faqs` row exists only while it is published.** Unpublishing deletes it and
  `published_at` is `not null`, so the storefront reads the table with no predicate and there
  is no fourth route for a draft state. Re-publishing is one click from the thread the answer
  came from, which is still there.
- **`conversationAccess` answers `mayRead` and `mayPost` separately, and the actor carries
  `isBlocked`.** Reading is being named in the participant column; posting is that plus
  standing. Both refusals then live in one pure predicate, and `postMessage` enforces rather
  than re-deciding.
- **Find-or-open is a pure plan over the rows that exist** (`planConversation`), the same
  shape FEAT-002 used for the identity plan. The action narrows in SQL by kind and the five id
  columns, and the plan makes the decision, so "one thread per subject" has one definition.
- **`conversationPath(actorType, id)` is core.** The same thread is `/seller/messages/:id`,
  `/messages/:id`, and `/admin/messages/:id`; `postMessage` needs the recipient's own path for
  the notification url, and the sites need it for their links. One table answers both.
- **The seller's and the customer's support counterpart is the first admin by id.** Admin rows
  are seeded and there is no assignment model in this prototype; with no admin row at all the
  route flashes and goes back rather than opening a half-formed thread.
- **The unread count rides on the request, not in each route.** `addUnreadMessages` decorates
  `request.unreadMessageCount`, `countUnreadMessages(actorType)` is one `preHandler` per site,
  and `addSiteRender` hands the number to every layout beside the flash and the identity — the
  same reason those two travel that way. The storefront's resolver reads the cookie itself, so
  `/account` and `/login`, which sit outside the hook that resolves a customer, still count.
- **Presentation naming is core, not view code.** `conversationTopic` (what a thread is about)
  and `customerName` (how an unnamed visitor reads) are pure functions all three sites share,
  beside `shopName`.
- **A blocked customer is refused by the action, not by a `preHandler`.** `refuseBlockedCustomer`
  exists for the paths that buy something and sends the visitor to a page that fits; a message
  refusal belongs with the message rules, so all three sites get the same answer and the same
  words.
- **The reply POST reads the thread before posting.** `postMessage` refuses a non-participant
  with a `TransitionError`, which is a flash; the ticket wants 404. Reading the thread first is
  what turns "not yours" into "not found" without the route holding the rule.

### Deviations

- **`app/core/messaging/` holds more than the one predicate the discovery notes named** —
  `conversation-kind`, `conversation-subject`, `conversation-plan`, `conversation-path`,
  `conversation-topic`, `participant-name`, `unread-messages`, `message-body`, `faq-draft`
  beside `conversation-access`. Each is a rule three sites read.
- **`app/plugins/site-render.ts` gained one key** (`unreadMessageCount`) and `app/app.ts` one
  line (`addUnreadMessages`). Both are FEAT-001's files; a nav count on every page has nowhere
  else to come from.
- **`app/actions/customers/merge-anonymous-customer.test.ts` and `merged-table-columns.test.ts`
  were rewritten around the real table.** Both used `conversations` as the stand-in for a table
  the schema does not have yet. The absence case now drops the table; the re-point case now
  inserts a real conversation row and is the ticket's merge test.
- **`app/db/seed-order-history.ts` returns `shippedFulfillmentId`** so the seeded `fulfillment`
  thread hangs off a real fulfillment.
- **No admin assignment, no per-conversation archive, no attachments.** Out of the ticket.

### Verification

- `docker compose run --rm app npm run check` (typecheck, eslint, `node --test`):
  **1156 tests, 1156 pass, 0 fail** — 189 more than the 967 this ticket started from.
  Roughly: 86 in `app/core/messaging`, 56 in `app/actions/messaging`, 18 in the seller portal,
  12 on the storefront, 11 on the admin site, 8 in `app/db/seed.test.ts`, 5 in
  `app/plugins/unread-messages.test.ts`, 2 in `app/test/smoke.test.ts`.
- `docker compose run --rm app npm run coverage`: **99.37% lines, 95.04% branches, 98.87%
  functions**, exit 0 against the unchanged 90 / 80 gate. `app/actions/messaging/**` is 100%
  lines; `app/core/messaging/**` is 100% lines and branches.
- Curl walk on <http://localhost:4000> against the seeded database: an anonymous shopper asks
  on `/art/portrait-of-a-welder` (302 to `/messages/5`) and reads the thread → the seller's nav
  shows `data-unread-messages="2"` and the inbox row `data-unread-count="1"` → the seller
  replies (302) → the thread offers the publish form pre-filled with
  `source_message_id="13"` → `POST /seller/listings/24/faqs` (302) → `/art/portrait-of-a-welder`
  now shows the question and the answer to everyone. `/seller/support` opens thread 1 and a
  second visit reuses it. `/seller/messages/3` (another seller's) and `/seller/messages/nope`
  both 404. The admin inbox lists both seeded threads; `POST /admin/customers/1/messages`
  reuses thread 2. Blocking the asker makes their next `POST /messages/5` flash "This account
  is blocked and cannot send messages.", append nothing, and drop the reply form from the page;
  lifting the block restores it.
- `npm run seed` on a scratch database prints `... 4 conversations, 11 messages, 1 listing FAQ.`
  and a second run still prints `demo data already seeded, skipping.`

### Found, not fixed

`POST /admin/customers/:id/blocks/lift` and `POST /admin/listings/:id/removals/lift` answer 500
for a request with no body at all: `moderationRoute` calls `command.form.parse(request.body)`
and Fastify leaves `request.body` undefined when nothing is submitted. Every form on the admin
pages posts a `redirect_to`, so no page reaches it; a bare `curl -X POST` does.
`command.form.parse(request.body ?? {})` is the fix. Belongs to `app/sites/admin/routes/moderation.ts`,
outside this ticket.
