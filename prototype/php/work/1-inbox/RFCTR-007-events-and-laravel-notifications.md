---
id: RFCTR-007
type: refactor
status: open
created: 2026-08-23
---

# RFCTR-007: Domain events and the Laravel notification subsystem

## Problem
`app/Actions/Notifications/Notify.php:13-29` writes `notifications` rows by hand and ends in an empty `protected function deliverByEmail()` hook; `FinalizeOrder.php:57-70` and `MarkShipped.php:37-43` call it inline inside their `DB::transaction`, so each action carries a `Notify` dependency and a future email would fire inside an open transaction. No `app/Events`, `app/Listeners`, `app/Notifications`, or `app/Mail` exists; `docs/architecture.md` names past-tense events as a convention that nothing uses. Magic-link delivery is a hand-rolled port (`app/Support/MagicLinkDelivery/`, bound by a `match` in `AppServiceProvider.php:18-26` that throws on an unknown channel) where Laravel's notification channels and `Notification::fake()` cover the same need. The app's `notifications` table (`database/migrations/2026_08_22_000211_create_notifications_table.php`) has `subject`/`body`/`url` and split `seller_id`/`customer_id` columns, a shape chosen so `MergeAnonymousCustomer` can re-point rows by `customer_id` (`app/Domain/Customers/CustomerOwnedTables.php`), and `Seller::notifications()` is a `HasMany` on the method name `Notifiable` reserves for its `morphMany`.

## Goal
Business moments are Laravel events, and what a seller or customer is told about them is a Laravel notification the framework can deliver to any channel.

## Outcome
- `OrderPaid` and `FulfillmentShipped` (past-tense) events are dispatched by the actions; listeners send the notifications after commit; the actions no longer depend on `Notify`.
- `ItemSold` and `OrderShipped` are `Illuminate\Notifications\Notification` classes with a database channel now and a `toMail()` ready for the mail channel; `Seller` and `Customer` are `Notifiable`; the in-app inboxes, unread counts, and mark-read routes read the framework's notifications.
- The magic link is a notification delivered through a channel chosen by config: a session-flash channel for the prototype debug alert and the mail channel for real delivery; the `MagicLinkDelivery` port and provider `match` are gone.
- Anonymous-to-verified customer merge still re-points the merged customer's notifications, with a test.
- Tests assert with `Event::fake()` / `Notification::fake()` where the point is that something was sent, and through HTTP where the point is what the inbox shows; all existing notification and magic-link tests pass or are replaced by equivalents.
- `docs/architecture.md` Notifications and Identity sections describe the event → listener → notification shape and the channel config.

## Why it matters
This is the feature area where the brief says "jobs and events over service-layer sprawl"; it is also the one the Rails and Node prototypes model with their own frameworks' messaging.

## Discovery notes
- Laravel's `DatabaseNotification` uses a `notifications` table with uuid `id`, `type`, `notifiable_type`/`notifiable_id`, `data` json, `read_at`. Options: migrate the app table to that shape (the merge then updates `notifiable_id` where `notifiable_type` is `Customer`), or subclass `DatabaseNotification` with a custom table. The first is the idiomatic one; the prototype has no production data to preserve.
- `NotificationMessage::itemSold()`/`orderShipped()` already produce subject/body/url; the notification classes can keep using them for `toArray()` and `toMail()`.
- A custom channel is a class with `send($notifiable, Notification $notification)`; `Notification::route()` / `AnonymousNotifiable` suits the magic link since the recipient may not be a model yet. The session-flash behavior that prints the link in the debug alert must survive.
- Listeners registered with `ShouldHandleEventsAfterCommit` (or `afterCommit` on the notification) keep delivery out of the transaction; `QUEUE_CONNECTION=sync` keeps the prototype simple while the classes can be `ShouldQueue`.
- Seeders (`OrderHistorySeeder`) go through the actions, so they need no change beyond what the events do.

## Related work
- RFCTR-004
- FEAT-002 (magic-link identity)
