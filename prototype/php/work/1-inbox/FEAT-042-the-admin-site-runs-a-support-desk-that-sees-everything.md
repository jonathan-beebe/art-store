---
id: FEAT-042
type: feature
status: open
created: 2026-09-01
---

# FEAT-042: The admin site runs a support desk that sees everything

## Problem
The admin Messages tool (`resources/views/admin/messages/*`, `resources/views/components/messaging/*`, `app/Http/Controllers/Admin/MessageController.php`, `SellerMessageController.php`, `CustomerMessageController.php`) shows only threads bound to the signed-in admin's own row, so the second admin sees nothing and neither sees seller ↔ customer conversations. There is no queue of threads waiting on the desk, no resolve, no reply-to, and "Message seller" / "Message customer" open the one untitled thread rather than a new titled one.

## Goal
Anna and Jonathan open Messages and see the whole marketplace's conversations, work the ones waiting on them, and step into a seller ↔ customer thread by opening their own with either side.

## Outcome
- The inbox lists every thread with `?filter=` (`needs-reply` default, `all`, `sellers`, `customers`, `orders`, `questions`) and `?status=` (`open` default, `resolved`, `all`); unknown values answer 400. `needs-reply` is open desk threads whose latest message is not an admin's. Rows carry a kind tag (Seller / Customer / Order / Question), the title or order, a preview, a relative time, the unread dot, and "handled by <admin>" where set. The nav badge counts unread on desk threads only.
- A desk thread shows the title, status pill, the seller or customer with a link to their admin page, the order context with a link, "handled by", a "Mark resolved" / "Reopen" button, the transcript with day separators and per-message "Reply", the reply quote, and the composer (grow, counter from the constant, Cmd/Ctrl+Enter, reply block with Cancel).
- An oversight thread (seller ↔ customer) names both sides in the header, renders the transcript read-only with a short notice, offers "Message <seller>" and "Message <customer>" that open a new titled desk thread carrying the order or listing as context, and does not mark the thread read.
- The seller and customer admin pages' message forms take a subject, an optional order, and a message, and open a fresh titled thread through `OpenThread`.
- The admin layout loads `public/composer.js`; everything works without it. Stone/taupe tint rules from PR #59 hold.
- `make precommit` green, coverage 100%, feature tests cover the filters and 400s, oversight read-only, resolve/reopen, reply-to, and both new-thread forms.

## Why it matters
The desk is how the two owners support and form relationships with sellers and customers. Seeing everything, with a queue of what is waiting, is the admin feature the whole subsystem exists to serve.

## Discovery notes
Design of record: `docs/messaging.md` and the canvas artboards "Admin · Desk, needs reply" and "Admin · Oversight thread (read-only)". Depends on FEAT-040 (desk scopes, admin policy, oversight scope). The admin's existing list/detail scaffold (`mode="list"` / `mode="detail"` on `x-layouts.admin`, `cells` slot, `x-admin.cell-footer`) is the shell to stay in.

## Related work
- FEAT-040 (foundation)
- PR #59 (the admin chrome this extends)
