---
id: RFCTR-010
type: refactor
status: open
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
