---
id: FEAT-016
type: feature
status: open
created: 2026-08-23
---

# FEAT-016: Live unread badge over Server-Sent Events

## Problem
All three layouts render `data-unread-messages="<%= unreadMessageCount %>"` (`sites/shop/views/layout.ejs:35`, `sites/seller/views/layout.ejs:21`, `sites/admin/views/layout.ejs:28`), computed per request by `plugins/unread-messages.ts:40`, and eight test files assert on that attribute — but nothing consumes it at runtime. The count only changes on the next full page load, even though the attribute is a ready-made target for a live update.

`docs/review.md:88` (per the manifest's citation) states "zero `<script>` tags across the 57 templates" as a claim about the codebase. That claim is true today and would become false the moment any client-side update ships, so it needs to be rewritten truthfully rather than silently invalidated.

## Goal
The unread-message badge updates live without a page reload, and every page still works with JavaScript off.

## Outcome
- Each site serves `GET <prefix>/events` as `text/event-stream` from a web `ReadableStream` subscribed to an `EventEmitter` decorated on the app, unsubscribing on client abort and on app close.
- Posting a message or a notification emits for the recipient.
- ~20 lines of dependency-free `public/app.js` (single `<script defer>` in each layout) update the existing `data-unread-messages` badge.
- Every page works with JavaScript off.
- The in-process limitation is stated in a comment.
- `docs/review.md`'s "zero script tags" claim is rewritten truthfully.
- Guarded by the sites' existing identity hooks.

## Why it matters
This is the one capability in the field that neither competing prototype can structurally answer. Neither Rails nor PHP has Turbo, Stimulus, Hotwire, htmx, Alpine, Livewire, SSE, WebSocket, or polling — every interaction in both is a form POST plus redirect, and both READMEs state the no-JavaScript constraint as a virtue. A Node entry that keeps every page working with JavaScript off and then layers a live badge on top demonstrates progressive enhancement rather than asserting it.

## Discovery notes
Shape: a `GET /events` per site returning `text/event-stream` from a `ReadableStream`, subscribed to a `node:events` `EventEmitter` decorated onto the app (`app.decorate('events', …)` — a decorator, not a module singleton, per the doctrine). `request.raw.signal` / an `AbortSignal` unsubscribes on client disconnect. `notify` and the message-create action emit; the stream re-runs `unreadMessageCount` for that actor and pushes one `unread` event. Client side is ~20 dependency-free lines of `new EventSource('/events')` in `public/app.js`, updating or creating the badge span. Guard behind the existing `requireSeller` / `requireAdmin` hooks so a stream cannot leak another actor's count.

Three risks to name explicitly, in a comment where relevant and in the docs update:
- This is the first `<script>` tag in the tree. `docs/review.md`'s "zero script tags across the 57 templates" claim has to be rewritten truthfully rather than quietly dropped.
- The emitter is in-process — correct for one deployable, wrong the moment there are two. Say so in a comment rather than pretending otherwise.
- An SSE connection holds a request open, which interacts with FEAT-011's draining shutdown: the abort has to fire on `onClose`.

Prefer the emitter over an interval poll — a per-connection `setInterval` querying SQLite is the version that looks like a demo hack, not a design.

Files expected to touch: new `app/plugins/events.ts` (the emitter decorator), new `app/sites/*/routes/events.ts` (or one shared route factory, since the three sites differ only in actor resolution), `app/actions/notifications/notify.ts` and the message-create action (emit), `src/public/app.js` (new), the three `layout.ejs` files (one `<script defer>` each), `docs/review.md`.

Depends on FEAT-011 (draining shutdown) landing first, or at minimum being coordinated with, since the SSE abort-on-close behavior is defined there.

## Related work
- 07-showcase.md — #7 "Live unread badge over SSE (web streams + `AbortSignal` + `node:events`)"
- FEAT-011 (draining shutdown — the SSE abort-on-close interaction)
