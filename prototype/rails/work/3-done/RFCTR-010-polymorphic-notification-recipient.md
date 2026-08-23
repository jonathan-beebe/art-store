---
id: RFCTR-010
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-010: Notifications address a polymorphic recipient

## Problem
`notifications` carries nullable `seller_id` and `customer_id`; `Notification::RECIPIENT_COLUMNS` maps `Domain::Notifications::RecipientType` strings to the column, and `Notifications::Notify` picks the column at write time. `Domain::Notifications::NotificationMessage` builds the two subjects/bodies.

## Goal
A notification belongs to its recipient the way Rails models ownership across two tables.

## Outcome
`Notification` declares `belongs_to :recipient, polymorphic: true`; sellers and customers `has_many :notifications, as: :recipient`; the `recipient_column` map, `RecipientType`, `Notify` and `NotificationMessage` are gone; a migration converts existing rows; the seller and customer notification pages and the `"Item sold"`/`"Order shipped"` assertions pass unchanged.

## Why it matters
Two nullable foreign keys plus a column picker is the pattern `polymorphic: true` exists to replace.

## Discovery notes
A reversible migration that adds `recipient_type`/`recipient_id`, backfills from the two columns, then drops them. Subject/body builders fit as class methods on `Notification` or as the model methods that create them (`seller.notify_item_sold(order, net)`).

## Related work
- RFCTR-007
- RFCTR-008

## Working

`Notification` declares `belongs_to :recipient, polymorphic: true` and writes
its own messages: `Notification.item_sold(fulfillment)` files "Item sold" under
the seller, `Notification.order_shipped(fulfillment)` files "Order shipped"
under the customer. Both take the fulfillment because it carries the recipient,
the order id and the money. The email hook moved with them and is now
`Notification#deliver_by_email`, called from the private `Notification.deliver`
that both class methods write through. `Notification#read!(at:)` replaces the
`update!(read_at:)` both notification-read controllers wrote.

`Order#pay!` and `Fulfillment#ship!` call those two class methods.
`Fulfillment#tell_the_customer` was a one-line wrapper, so `ship!` now calls
`Notification.order_shipped(self)` directly. `Seller` and `Customer` declare
`has_many :notifications, as: :recipient, dependent: :destroy`.

`Customer#absorb` re-points each merged association through the foreign key its
own reflection names, so notifications move by `recipient_id` while the rest
still move by `customer_id`.

The migration `20260822000213_make_notifications_polymorphic` adds
`recipient_type`/`recipient_id`, backfills both, adds the
`[recipient_type, recipient_id, read_at]` index and drops the two foreign keys
with their indexes. SQLite rebuilds a table around a dropped column and carries
a composite index over as its remaining half, so the two `[x_id, read_at]`
indexes are removed before the columns are. `db:rollback` and re-migrate round
trip cleanly.

`app/actions/` (only `notifications/notify.rb` was left) and
`app/domain/notifications/` are deleted, with their tests. Notification
behaviour is covered by the new `test/models/notification_test.rb`; the order,
fulfillment, customer, shipments and account tests assert on `recipient`.

Left alone: the `url` column stays, unset by both writers, and the seller inbox
still renders the "Open" link when a row carries one.
