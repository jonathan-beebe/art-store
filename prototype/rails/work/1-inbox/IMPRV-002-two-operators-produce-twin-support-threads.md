---
id: IMPRV-002
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-002: Two operators produce twin support threads and the support button reaches only one

## Problem
A seller's or customer's support thread opens against `Admin.on_duty` — the first admin by id (`src/app/controllers/seller/supports_controller.rb`, `src/app/controllers/shop/supports_controller.rb`) — while an admin's "Message" button opens against `current_admin` (`src/app/controllers/admin/seller_conversations_controller.rb`, `admin/customer_conversations_controller.rb`). With a second admin row, a second operator messaging a seller creates a second `admin_seller` thread; the seller's inbox then shows two threads both titled "Art Store support", distinguished only by the counterpart's name, and the seller's Support button only ever reaches the on-duty one. `docs/messaging.md` states the behaviour. Seeds ship one admin, so nothing surfaces it today.

## Goal
Support threads behave sensibly when the platform has more than one operator.

## Outcome
With two admins, a seller or customer who opens support and is answered by either operator keeps one legible conversation trail: the inbox distinguishes the threads (or one shared desk thread exists per seller/customer), and the Support button reaches the thread with the operator who is actually talking to them; the behaviour is stated in `docs/messaging.md` and covered by tests.

## Why it matters
The current shape strands a second operator's messages in a thread the seller's own Support button never reaches; the two identical inbox rows give the seller no way to know which is which.

## Discovery notes
The small end of the range: keep one thread per seller/customer for the whole desk (the admin side joins the existing thread rather than opening one per operator) — the kind's admin column then names whoever answered last, or the desk thread hangs off no particular admin. The large end is an assignment model, which the Node prototype also declined. Inbox rows could also carry the counterpart's name in the topic for the admin kinds, which fixes the two-identical-rows half on its own.

## Related work
- FEAT-011
- IMPRV-001 (documented the current behaviour)
- prototype/node/docs/messaging.md (Node has the same first-admin shape and no assignment model)
