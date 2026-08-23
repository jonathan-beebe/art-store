---
id: FEAT-011
type: feature
status: open
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
