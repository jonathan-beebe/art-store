---
id: FEAT-014
type: feature
status: open
created: 2026-08-23
---

# FEAT-014: Admin messaging inbox and threads

## Problem
The admin site from FEAT-010 reads sellers and customers but cannot talk to them, and the `admin_seller` and `admin_customer` threads that FEAT-012 and FEAT-013 open from the other side have no admin-facing page. Support requests from either site currently reach a thread nobody can read.

## Goal
An admin reads and answers every support thread and starts one with any seller or customer from their detail page.

## Outcome
- `/admin/messages` lists the admin's threads newest first with who each is with, what it is about, and the unread count.
- Opening a thread shows the messages and a reply box, and clears the unread count.
- "Message seller" on a seller's detail page and "Message customer" on a customer's detail page each open the thread with that party; a second use lands on the same thread.
- A support thread a seller or a customer opened appears in the admin inbox and can be answered there, and the answer appears on the other party's thread page.
- A Messages link with the unread count is in the admin nav on every page.
- A conversation id for a thread the admin is not in and an id that matches nothing both answer 404, on the thread page and on the reply.
- Every new class has a sidecar test; `make check` is green.

## Why it matters
Support is a round trip. Two kinds of conversation exist only to reach an admin, and until the admin can answer, both are one-way.

## Discovery notes
- Routes go in `routes/admin.php`, behind `auth.admin`:

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/admin/messages` | `admin.messages.index` | Inbox |
| GET | `/admin/messages/{conversation}` | `admin.messages.show` | Thread; marks it read |
| POST | `/admin/messages/{conversation}` | `admin.messages.store` | Reply |
| POST | `/admin/sellers/{seller}/messages` | `admin.sellers.messages` | "Message seller" from the seller page |
| POST | `/admin/customers/{customer}/messages` | `admin.customers.messages` | "Message customer" from the customer page |

- The inbox, the thread page, and the reply are the same three pages the other two sites render — the pairing differs, the page does not. Look hard at what FEAT-012 and FEAT-013 built before writing a third copy: a shared Blade component for a thread and an inbox row, or a shared view model, is the cheaper answer. Which of the three sites owns it is yours **(decided at build time)**; a third divergent copy is the outcome to avoid.
- The admin holds a real guard, so `$this->authorize(...)` and `@can` work here the way they do in the seller portal — the storefront's `authorizeVisitor` shape is not needed.
- The two "message X" routes open the thread and redirect to it. They post a body or they do not; if they do, they take a `MessageBody` through a form request like every other write.
- `AdminLayoutComposer` bound to `components.layouts.admin` carries the nav count, matching the other two.
- The admin is `Notifiable` from FEAT-010, so a seller's or a customer's reply already leaves the admin a notification row. An admin notifications page is out of scope — the badge and the inbox are the admin's signal.

## Related work
- FEAT-010, FEAT-011, FEAT-012, FEAT-013.
