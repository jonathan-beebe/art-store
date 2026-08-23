---
id: RFCTR-007
type: refactor
status: resolved
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

## Working

Verified against the tree before starting: every Problem line still held.
`Notify` wrote rows through `Notification::to()` and ended in the empty
`deliverByEmail()`; both actions took it as a constructor dependency and
called it inside `DB::transaction`; there was no `app/Events`,
`app/Listeners`, `app/Notifications`, or `app/Mail`; `AppServiceProvider`
bound `MagicLinkDelivery` with a throwing `match`; the `notifications` table
carried `subject`/`body`/`url` and nullable `seller_id`/`customer_id`; and
`Seller::notifications()` was a `HasMany` on the name `Notifiable` reserves.

### Numbers

| Gate | Before | After |
| --- | --- | --- |
| Pest | 647 tests, 1480 assertions | 665 tests, 1496 assertions |
| PHPStan (level max) | 0 errors | 0 errors |
| Pint | clean | clean |

`docker compose run --rm --no-deps --entrypoint php app artisan migrate:fresh
--seed --force` runs clean and writes five rows: three
`App\Notifications\ItemSold` to `seller#1`/`seller#2` and two
`App\Notifications\OrderShipped` to `customer#1`.

### Table shape

`notifications` is now Laravel's own table, in place under the same migration
filename (`2026_08_22_000211`) so ordering is unchanged: uuid `id`, `type`,
`morphs('notifiable')`, `json data`, nullable `read_at`, timestamps, plus a
`(notifiable_type, notifiable_id, read_at)` index for the unread count. There
was no data to preserve. `App\Models\Notification` and its sidecar are gone;
rows are read back as `Illuminate\Notifications\DatabaseNotification`.

`notifiable_type` holds a morph alias (`seller`, `customer`) rather than a
class string, enforced by `Relation::enforceMorphMap()` in
`AppServiceProvider`. That is what `RecipientType` adapted into: its two
values *are* the aliases, so the enum still names the two sides of the
marketplace and its test now pins persisted words. `RecipientType::column()`
is gone with the columns it named.

### The merge

`CustomerOwnedTables::all()` lists tables that hold a `customer_id` foreign
key, and `notifications` no longer qualifies — re-pointing it needs the
`notifiable_type` to match as well, which a pure domain list cannot express
without naming a model. `MergeAnonymousCustomer` re-points it through the
relation instead: `$anonymous->notifications()->update(['notifiable_id' =>
$verified->id])`, which constrains on both morph columns. Two tests cover it:
the anonymous customer's rows move and a bystander customer's stay, and a
seller's row with the same numeric id stays put.

### Listener discovery is off

Laravel discovers listeners by reflecting over every file under
`app/Listeners`, and this repo's sidecar convention puts `*Test.php` there.
Reflection autoloads those files, Pest registers their tests a second time,
and the whole suite fails with `TestAlreadyExist`. `bootstrap/app.php` now
passes `withEvents(discover: false)` and `AppServiceProvider` names the two
event/listener pairs with `Event::listen()`. The alternative — a
`DiscoverEvents::guessClassNamesUsing` hook that skips test files — trades
two explicit lines for a framework-internals workaround with boot-order
risk.

### Other decisions

- `via()` reads `config('notifications.channels')` (new
  `config/notifications.php`, `NOTIFICATION_CHANNELS` comma separated,
  `database` by default). The magic link keeps `config/magic_links.php` →
  `delivery`; `MagicLinkIssued::channel()` maps `session` to
  `SessionFlashChannel::class` and `mail` to `'mail'`, and still throws
  `InvalidArgumentException` on an unknown value — the behaviour the provider
  `match` used to hold.
- The magic link is sent with
  `Notification::route(MagicLinkIssued::channel(), $address)`, so the same
  address is routed on whichever channel is configured. The session channel
  flashes under the unchanged key `debug_magic_link`, so the debug alert and
  the smoke test are untouched.
- `SessionFlashChannel` takes any notification implementing
  `App\Notifications\Channels\FlashesUrlToSession` and throws otherwise;
  the interface is what keeps the channel free of a `method_exists` guard at
  PHPStan level max. `tests/Arch.php` ignores `App\Notifications\Channels`
  in the laravel preset, because a channel is not a notification and Laravel's
  docs put custom channels there.
- No `app/Mail`: `toMail()` returning a `MailMessage` covers the three
  notifications, so no `Mailable` earns its place yet.
- `markAsRead()` reads `now()` itself rather than taking the controller's
  instant, which is a small step away from the clock rule in
  `docs/architecture.md`. `travelTo()` still controls it; the doc now says so.
- Views read `$notification->data['subject']` etc. directly — no presenter.
- The dashboard's "five most recent" test now travels a minute between
  notifications: uuid ids do not order, and the trait sorts on `created_at`,
  which ties at one-second resolution.

### Touched outside the ticket's list

`README.md` (magic-link section) and `docs/review.md` (four rows and two gap
entries) named deleted classes; both were corrected rather than left dangling.
`src/.env`/`src/.env.example` gained `NOTIFICATION_CHANNELS=database`.
`bootstrap/app.php` gained the discovery switch.
