---
id: FEAT-015
type: feature
status: open
created: 2026-08-23
---

# FEAT-015: Transactional outbox: notifications and magic links delivered after commit

## Problem
`app/actions/notifications/notify.ts:41` calls `await context.notificationDelivery?.deliver({ … })` between the insert and the commit. `NotificationDelivery` is a port whose point is out-of-process delivery (mail). Two problems once an implementation is wired up: the message goes out even when the transaction later rolls back, and because Kysely's SQLite adapter reports `supportsMultipleConnections === false`, the transaction holds the single connection and its mutex for the whole duration of that I/O — every other request in the process blocks behind it. The port is currently latent: `notificationDelivery` is declared on `ActionContext` (`action-context.ts:13`) and read at that call site, but `app.ts` wires only `magicLinkDelivery` — nothing sets `notificationDelivery` in the running app.

Separately, `app/delivery/mail-magic-link-delivery.ts:8`'s `deliver()` throws `NotImplementedError` unconditionally, so setting `MAGIC_LINK_DELIVERY=mail` breaks sign-in outright. The only delivery that works today is the flash-based one, which hands the link back to the same requester rather than delivering it anywhere.

The prototype's own review doc records this as an open gap (`docs/review.md` known gap 1, per the manifest's citation).

## Goal
Notifications and magic links can be delivered outside the flash mechanism, and no code path performs I/O inside an open database transaction.

## Outcome
- A forward-only migration adds `outbox_messages`.
- Outbox implementations of `NotificationDelivery` and `MagicLinkDelivery` write a row in the business transaction.
- `npm run outbox` / `make outbox` and a button on `/admin/outbox` drain rows into RFC-5322 `.eml` files under `storage/outbox` and stamp `delivered_at`.
- `/admin/outbox` lists and shows messages.
- Message rendering is a pure core function with tests.
- `NOTIFICATION_DELIVERY` and `MAGIC_LINK_DELIVERY` select `flash|outbox`.
- Nothing performs I/O inside a transaction.
- No SMTP.

## Why it matters
The doctrine states: "Mock only what crosses the process boundary; a transaction holds no external I/O; better-sqlite3 is one synchronous connection." Delivering inside a transaction violates that directly, and the violation is currently latent only because nothing implements the port yet — the first implementation would trigger it.

This closes the prototype's own recorded known gap. It also answers the one place a competing prototype currently leads: Rails ships a real mailer (`app/mailers/magic_link_mailer.rb`) with previews, and PHP at least delivers somewhere (`MAIL_MAILER=log`), while Node's two delivery ports throw or are wired to nothing. An outbox with an admin page is Rails' mailer preview plus the queue Rails does not have.

## Discovery notes
There is a real bug waiting in the naive fix: implementing `NotificationDelivery` or `MagicLinkDelivery` to perform I/O directly, in place, would run that I/O inside `runInTransaction` — a sent email for an order that never existed, the moment the transaction rolls back after the message went out. The transactional-outbox pattern avoids this: write an `outbox_messages` row in the same transaction as the business change, then drain it outside, after commit.

Non-goal: no SMTP. `.eml` files under `storage/outbox` plus the admin page is the whole deliverable. SMTP becomes a third `NotificationDelivery` implementation later without touching a call site — that is the reason the port exists as a port.

Pieces: a migration for `outbox_messages` (recipient, subject, body, url, `created_at`, `delivered_at`); `outboxNotificationDelivery` / `outboxMagicLinkDelivery`, each writing a row inside the caller's transaction; a drain step that renders each row as an RFC-5322 `.eml` and stamps `delivered_at`, exposed as `npm run outbox` / `make outbox` and a button on the admin page; `GET /admin/outbox` listing and showing rows; message rendering (subject, headers, body) as a pure function in `app/core/notifications/` with literal-input unit tests; `NOTIFICATION_DELIVERY` selected in `app/config.ts` / `app/delivery/magic-link-delivery.ts` the same way `MAGIC_LINK_DELIVERY` already is.

Files expected to touch: `app/db/migrations/<new>-create-outbox.ts`, `app/core/notifications/` (rendering), `app/delivery/` (both implementations), `app/actions/notifications/notify.ts` (delivery moves out of the transaction), `app/actions/outbox/drain-outbox.ts` (new), `app/cli/drain-outbox.ts` (new), `app/sites/admin/routes/outbox.ts` + view (new), `app/config.ts`, `Makefile`, `docs/review.md` (gap 1).

No dependency on other tickets in this batch to start; IMPRV-003's structured-logging event list (order placed/paid/declined, payout run, magic link requested/consumed) covers similar business moments — coordinate naming with that ticket if both are in flight at once, but neither blocks the other.

## Related work
- 04-data-layer.md — "Notification delivery runs inside the transaction"
- 07-showcase.md — #8 "Transactional outbox: `NotificationDelivery` and `MailMagicLinkDelivery` implemented, `/admin/outbox`"
- docs/review.md known gap 1
- IMPRV-003 (structured logging — related business-event vocabulary)
