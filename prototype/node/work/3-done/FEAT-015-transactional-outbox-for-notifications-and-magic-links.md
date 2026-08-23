---
id: FEAT-015
type: feature
status: resolved
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

## Working

### Verified against the code first

- `notify` did call `notificationDelivery?.deliver(...)` between the insert and
  the commit, and nothing in the running app set `notificationDelivery` — the
  port was latent, as the ticket says.
- `mailMagicLinkDelivery.deliver()` threw `NotImplementedError` unconditionally,
  so `MAGIC_LINK_DELIVERY=mail` broke sign-in.
- `sendMagicLink` wrote the `magic_links` row and called the delivery outside any
  transaction, so a queued message and its link could not have committed
  together.

### What changed

**Schema.** `app/db/migrations/20260823100000-create-outbox.ts` creates
`outbox_messages` (`recipient`, `subject`, `body`, `url`, `created_at`,
`delivered_at`) with a `(delivered_at, id)` index for the drain query, plus a
`down()`. Row type `OutboxMessagesTable` / `OutboxMessage` in
`commerce-schema.ts`, and an entry in `schema-fidelity.test.ts`.

**Core (pure).** `app/core/notifications/mail-message.ts` — `renderMailMessage`
takes `{ to, subject, body, url, messageId, date }` and returns the RFC-5322
text: `From`/`To`/`Subject`/`Date`/`Message-ID`/`MIME-Version`/`Content-Type:
text/plain; charset="utf-8"`/`Content-Transfer-Encoding`, blank line, body, blank
line, url. CRLF everywhere; a CR or LF inside a header value folds to a space so
a smuggled newline cannot open a header of its own. `rfc5322Date` is
`toUTCString()` with the obsolete `GMT` swapped for `+0000`. Nine tests against
literal strings. `signInLinkMessage(url)` joins the other message builders in
`notification-message.ts`.

**Ports.** `NotificationDelivery` moved out of `app/core/notifications/` into
`app/delivery/` — it now takes a `DeliveryContext` (`{ db, clock }`), and a
database handle has no business in the functional core. Both ports' `deliver`
now takes that context first, so an implementation writes with **the caller's
transaction**:

- `outboxNotificationDelivery` looks the recipient's address up on their own row
  and enqueues; an anonymous customer (no address) is queued nowhere and keeps
  only the in-app inbox.
- `outboxMagicLinkDelivery` enqueues `signInLinkMessage` and returns an empty
  flash — nothing prints the link.
- `flashMagicLinkDelivery` is unchanged in behaviour, now `Promise<Flash>`.
- `enqueueOutboxMessage` / `renderOutboxMessage` (`app/delivery/outbox-message.ts`)
  are the one insert and the one row-to-message mapping both the drain and the
  admin page use.

**Transactions.** `notify` wraps both writes in `runInTransaction` (joining the
caller's) and defaults its delivery to `outboxNotificationDelivery`.
`sendMagicLink` wraps the link insert and the delivery in one transaction. A
test asserts a rolled-back business transaction leaves neither the inbox row nor
the outbox row.

**Drain.** `drainOutbox(context, { outboxDir })`
(`app/actions/outbox/drain-outbox.ts`) reads `delivered_at is null`, `mkdir -p`s
the directory, and per row writes `<outboxDir>/<id>.eml` then stamps
`delivered_at`. Deliberately **not** in a transaction — the connection is one
synchronous handle, and a message already on disk must not be un-stamped by a
later rollback. Exposed three ways over the same function: `npm run outbox` /
`make outbox` (`app/cli/drain-outbox.ts`, `main(argv, env)` with `parseArgs`
`--dir` and the `import.meta` guard, like `run-payouts.ts`) and
`POST /admin/outbox/drain`.

**Admin.** `GET /admin/outbox` (newest first, Pending/Sent column, drain button,
the directory named), `GET /admin/outbox/:id` (the rendered message escaped in a
`<pre>`, the url as a clickable anchor), `POST /admin/outbox/drain`. Queries in
`app/sites/admin/queries/outbox-rows.ts`. Registered in `console.ts` behind
`requireAdmin` and linked from the admin nav.

**Config.** `OUTBOX_DIR` (default `storage/outbox`) → `config.outboxDir`.
`MAGIC_LINK_DELIVERIES` is now `flash | outbox`; production's refusal of `flash`
stands and now has a working alternative.

**Docs.** README's Configuration table (`MAGIC_LINK_DELIVERY`, new `OUTBOX_DIR`),
Commands table (`make outbox`), the admin page table, and the old "Magic links
and the email hooks" section rewritten as "Magic links and the outbox" +
"Outbox". `docs/review.md` known gap 1 rewritten (the gap is now "no SMTP", not
"the hook throws"), its requirement row moved partial → done, and the matching
"next step" reworded.

### Decisions taken where the ticket left a choice

- **No `NOTIFICATION_DELIVERY` env var.** The ticket asked for one selecting
  `none | outbox`. It could not be made to mean anything: every route builds its
  own `ActionContext` literal (`{ db: shop.db, clock: shop.clock }`), and the
  shop and seller route files are another ticket's territory this cycle, so a
  config-selected delivery could not reach the call sites that raise
  notifications. A knob the running app ignores is worse than no knob. Instead
  `notify` falls back to `outboxNotificationDelivery` when the context carries
  none, which is the "default outbox everywhere, demo visible" option the brief
  preferred; `ActionContext.notificationDelivery` stays as the override seam
  (tests use it, and an SMTP implementation drops in there). Adding the env var
  is a small follow-up once the route files are free — it is one line in
  `notify` plus a decoration.
- **`mailMagicLinkDelivery` deleted**, not kept, along with its test and
  `app/not-implemented-error.ts` (its only user — leaving it would have left an
  unreferenced, uncovered file). `MAGIC_LINK_DELIVERY=mail` is now refused by
  `loadConfig` instead of breaking sign-in at the moment someone tries to sign
  in.
- **`outbox_messages` carries the resolved email address**, not
  `(recipient_type, recipient_id)`. The stored row is then a complete message and
  the drain needs no joins; the lookup happens inside the business transaction,
  where it is a read on the handle already held.

### Deliberately left alone

- **`docs/architecture.md` and `docs/identity.md` still describe
  `mailMagicLinkDelivery`** (architecture.md lines ~60, ~146-148, the repository
  layout listing of `not-implemented-error.ts`, "Twenty-three tables in nine
  migrations", and the Notifications paragraph saying the port is "unset in the
  running app"; identity.md line ~69). Both files are outside this ticket's
  territory and FEAT-017 is the documentation refresh — flagged to the
  orchestrator rather than edited here.
- **Shop and seller route files** — BUG-005 and IMPRV-001 are in them.
- **No SMTP**, per the ticket's non-goal.

### Tests

Before: 1385 pass, 0 fail. After: **1428 pass, 0 fail** in an isolated worktree
holding HEAD plus only this ticket's files; `npm run check` exits 0 there with
**99.54% lines / 96.69% branches / 99.71% functions** against the 95/90 gate.
Every new file is at 100% lines and branches except `app/cli/drain-outbox.ts`
(95.12 / 90.00 — the `import.meta` guard, uncovered the same way
`run-payouts.ts`'s is).

The isolated run was needed because the shared tree carries IMPRV-001's in-flight
edits to `app/plugins/error-pages.ts`, `app/app.ts`, and the three site
`index.ts` files; at the time of writing those fail `tsc` and a handful of
not-found tests. Nothing in that failure set touches this ticket's files. In the
shared tree the suite reads 1446 tests with only those in-flight failures.

New tests: `mail-message.test.ts` (9), `notification-message.test.ts` (+1),
`outbox-message.test.ts` (3), `outbox-notification-delivery.test.ts` (5),
`outbox-magic-link-delivery.test.ts` (4), `flash-magic-link-delivery.test.ts`
(+1), `drain-outbox.test.ts` (6), `cli/drain-outbox.test.ts` (3),
`admin/queries/outbox-rows.test.ts` (4), `admin/routes/outbox.test.ts` (8),
`sign-in-routes.test.ts` (+2, including the queued link followed to a signed-in
seller), `notify.test.ts` (+2, including the rollback), `config.test.ts` and
`schema-fidelity.test.ts` extended.
