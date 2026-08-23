---
id: FEAT-011
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-011: Inboxes, threads, and entry points on all three sites

## Problem
With FEAT-009 and FEAT-010 landed the database can hold a thread but no page shows one: `src/config/routes.rb` has no `messages` routes on any site, the three layouts (`src/app/views/layouts/{shop,seller,admin}.html.erb`) have no Messages link, and the pages where a conversation starts — the seller order page (`src/app/views/seller/orders/show.html.erb`), the storefront order page (`src/app/views/shop/orders/show.html.erb`), the account page, the seller dashboard, the admin seller and customer pages — offer no way to open one.

## Goal
Every actor has an inbox and can reach the right counterpart about the right subject from the page where the subject lives, and a reply lands in front of the other side with a notification.

## Outcome
A seller at `/seller/messages`, a customer at `/messages`, and an admin at `/admin/messages` each see their conversations newest first with a per-thread unread count, open a thread, read it (which clears that side's unread), and reply; a "Support" button in the seller portal and a "Contact support" button on the storefront account page open (or reuse) that actor's thread with the first admin, and with no admin row the button flashes and returns; an admin's seller page and customer page each carry a "Message" button that opens or reuses the support thread; the seller order page and the storefront order page (per fulfillment) each carry a button that opens or reuses the fulfillment thread between that seller and that customer; posting notifies the counterpart with a link that opens the thread on the counterpart's own site; every layout's nav shows "Messages" with an unread count marked `data-unread-messages` when it is above zero; a thread whose participant the current actor is not, and a non-numeric id, both answer 404 on every site; the HTML stays form-post-and-redirect with no JavaScript; integration tests cover each site's inbox, thread, reply, refusal, and every entry point; the suite stays at 100% line coverage.

## Why it matters
This is the user-facing half of the messaging center and the reason an operator, a seller, and a buyer can talk at all. It is also the surface the stack comparison will click through.

## Discovery notes
Resourceful routes in the RFCTR-013 style: `resources :conversations, path: "messages", only: %i[index show]` nesting `resources :messages, only: :create` on each site; `resource :support, only: :create` on shop and seller; `resource :conversation, only: :create` nested under the admin's sellers/customers and under the order/fulfillment pages. Opening a thread is a POST (`button_to`) that redirects to the thread page — a GET that writes is what Node did and is the thing to improve on. `Conversation.involving(current_actor).find(params[:id])` is the whole access check and the reason a stranger sees 404 (the architecture doc's "someone else's order is not theirs to read" pattern). The three sites' conversation controllers differ only in which actor is current and which layout renders; a concern (`MessagingSite` or similar) with the shared actions, parameterised by `current_participant`, beats three copies — but keep the controllers thin and keep the concern free of domain `if`s. Each base controller's `unread_message_count` helper feeds the badge the way `unread_notification_count` already does (`Seller::BaseController`, `ShopHelper`). `ShopHelper#unread_notification_count` reads `current_customer`, which `/login` reaches without `Shop::BaseController` — the messages count has the same constraint. Seller-side the "customer" label for an anonymous asker needs a fallback name ("A visitor"); put it on the record (`Customer#display_name`) beside `Seller#display_name`. The thread page for a seller on a `listing_question` can leave the FAQ publish form to FEAT-012. Mark inbox rows `data-unread-count="N"` so tests and the smoke walk can read them.

## Related work
- FEAT-004 (seller portal)
- FEAT-005 (customer storefront)
- FEAT-009
- FEAT-010
- RFCTR-013 (resourceful storefront routes)
- prototype/node/docs/messaging.md

## Working

### Verified before starting

- `src/config/routes.rb` had no messages routes; the three layouts had no
  Messages link; the seller order page, the storefront order page, the account
  page, the seller dashboard and the two admin account pages had no entry point.
- FEAT-010's `Conversation#thread_path_for` reads a path table on the record
  (`SIDES`), and `Notification.new_message` files under the counterpart with
  that path. The routes landed here sit at `/messages/:id`,
  `/seller/messages/:id` and `/admin/messages/:id`, which is what the table
  says, so the record keeps the table and the integration tests assert the
  notification url against the route helpers.

### What landed

Routes: `resources :conversations, path: "messages", only: %i[index show]`
nesting `resources :messages, only: :create` on each of the three sites;
`resource :support, only: :create` on shop and seller; `resource :conversation,
only: :create` under `admin/sellers`, `admin/customers` and `seller/orders`; and
`post "orders/:order_id/fulfillments/:id/conversation"` on the storefront,
beside the delivery-confirmation route it mirrors.

`MessagingSite` (`app/controllers/concerns/messaging_site.rb`) holds `index`,
`show` and `create`. It reads `current_participant` and `thread_template` from
the site that includes it and finds every thread through
`Conversation.involving(current_participant).find(id)`, which is what answers
404 for a stranger and for an id no thread of theirs carries. `create` posts
through `Conversation#post!` and re-renders the site's thread page with the
message carrying the error when the body is refused.

Each site's `ConversationsController` is the concern and nothing else;
`MessagesController` adds the one template path. `current_participant` sits on
each site's base controller beside `current_seller` / `current_customer` /
`current_admin`, so the two controllers of a site declare it once.

Entry points: `Seller::SupportsController`, `Shop::SupportsController`,
`Admin::SellerConversationsController`, `Admin::CustomerConversationsController`,
`Seller::OrderConversationsController`, `Shop::FulfillmentConversationsController`.
Each finds its row through the current actor's own association, calls
`Conversation.open`, and redirects to the thread.

`Admin.on_duty` (first admin by id) and `Admin#display_name` are new on the
model, mirroring `Seller#display_name`, so a sender label and a counterpart
label read the same whichever of the three writes.

Views: `{seller,shop,admin}/conversations/{index,show}.html.erb`. The three
carry the same markers — `data-conversation`, `data-unread-count`,
`data-cell="topic"`, `data-cell="counterpart"`, `data-message`,
`data-field-error="message_body"` — and differ in Tailwind classes. The nav
badge is `data-unread-messages` on all three layouts, fed by
`unread_message_count` on `Seller::BaseController`, `Admin::BaseController` and
`ShopHelper` (the helper, because `/login` renders the shop layout without
`Shop::BaseController`).

### Decisions

- No shared inbox-row or message partial. The three sites' rows carry the same
  markers and different classes (airy storefront, dense portal, slate admin),
  so a shared partial would need the classes passed in.
- `Shop::SupportsController` takes the storefront identity rather than a
  verified customer. `require_customer!` redirects to `/login?redirect_to=/support`,
  and `/support` answers POST only, so following that link lands on a route
  that does not exist. The button lives on the account page, which is behind
  sign-in; an anonymous visitor's thread travels with them through
  `Customer#absorb`, the way a listing question will.
- No admin on the desk redirects back with a flash
  (`redirect_back_or_to seller_root_path` / `root_path`).
- The seller portal's "Support" button sits on the dashboard.

### Left alone

- The `listing_question` thread page has no FAQ publish form; that is FEAT-012.
- `docs/` and the smoke walk are FEAT-014's.
- The seller layout's notification badge keeps `data-unread-count`, which the
  inbox rows also use. The two never appear in the same element, and the tests
  read the rows by value.

### Verification

- `make test`: 687 runs, 2082 assertions, 0 failures, 0 errors. Line coverage
  1168 / 1168 (100.00%). Baseline was 617 runs at 100%.
- No migration; the schema is FEAT-010's.
- Curl walk against http://localhost:3000 (dev container, source bind-mounted):
  - `GET /` carries `href="/messages"`; `GET /messages` as a visitor with no
    cookie answers 200 with "Nothing here yet."
  - Seller signs in through a magic link; `GET /seller` carries the
    `/seller/support` form and the Messages link. `POST /seller/support` → 302
    `/seller/messages/3`. `GET /seller/messages/3` renders "Art Store support"
    with the reply form. `POST /seller/messages/3/messages` with a body → 302
    back to the thread; with a blank body → 422 carrying
    `data-field-error="message_body">Write a message.`
  - `GET /seller/messages` shows `data-conversation="3"`,
    `data-unread-count="0"`, topic "Art Store support".
  - Admin signs in; `GET /admin` shows `data-unread-messages` = 1;
    `GET /admin/messages` shows `data-unread-count="1"`; `GET /admin/messages/3`
    renders the message and the badge count drops to 0 on the next page.
  - `GET /admin/sellers/1` carries the `/admin/sellers/1/conversation` form.
  - Customer signs in; `POST /support` → 302 `/messages/4`, and again → the same
    thread. `POST /orders/3/fulfillments/3/conversation` → 302 `/messages/5`.
    `GET /messages` lists both with topics "order #3" and "Art Store support".
  - The notification the admin received for the seller's post carries
    `/admin/messages/3`.
