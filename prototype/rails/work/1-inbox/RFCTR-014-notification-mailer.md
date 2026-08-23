---
id: RFCTR-014
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-014: Notifications reach email through a mailer

## Problem
`Notification#deliver_by_email` (`src/app/models/notification.rb`) is an empty method kept as "the email hook"; `Notification.item_sold` and `.order_shipped` call it and nothing happens. The `notifications.url` column is never written by either builder, yet the seller inbox view renders an "Open" link when a row carries one.

## Goal
Every notification the app writes has one obvious way to reach a mailbox, built the way the sign-in link now is.

## Outcome
A `NotificationMailer` (or per-message mailer actions) sends "Item sold" and "Order shipped" with `deliver_later`, covered by mailer tests and a preview; the empty hook is gone; the `url` column is either populated with the order page each message is about or removed by a migration, with the inbox view matching; README and `docs/review.md` no longer describe email as unimplemented.

## Why it matters
RFCTR-012 made magic links a mailer; the other email path still ends in a no-op, so the two halves of "email" in this app follow different shapes.

## Discovery notes
`MagicLinkMailer` (RFCTR-012) is the template: `with(params).action.deliver_later`, `delivery_method :test` outside production, `test/mailers/previews`. The seller page for a fulfillment is `seller_order_path(fulfillment)`; the customer page is `shop_order_path(order)`.

## Related work
- RFCTR-010
- RFCTR-012
