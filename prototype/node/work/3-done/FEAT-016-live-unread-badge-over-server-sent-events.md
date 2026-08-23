---
id: FEAT-016
type: feature
status: resolved
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

## Working

### Verified before changing anything

- `unreadMessageCount` (`actions/messaging/conversation-inbox.ts`) counts rows in `messages`, not
  `notifications`. A `notify` emit could never move the badge on its own, so the emit the ticket
  asks for in `actions/notifications/notify.ts` would have been a no-op refresh.
- Three routes call `postMessage`: `sites/shop/routes/messages.ts`, `sites/seller/routes/messages.ts`,
  `sites/admin/routes/messages.ts`. Two of the three are another worker's territory this cycle.
- Each of those routes builds its own `ActionContext` literal (`{ db, clock }`), so an optional
  `events` on `ActionContext` only reaches an action if the route passes it — which is exactly the
  two files that could not be touched.
- Fastify 5 sends a web `ReadableStream` natively (`sendWebStream`, `lib/reply.js`), so no
  `Readable.fromWeb` wrapper is needed.
- Fastify runs `preClose` hooks *before* the http server closes and `onClose` hooks *after*. An open
  stream is an in-flight request, so `app.close()` waits for it: ending streams from `onClose` would
  deadlock against the close it is waiting on. The streams end on `preClose`.

### The one design decision, and why

The ticket and the brief ask for a precise emit — `notify`/`postMessage` publish
`{ actorType, actorId }` through `context.events`, and each stream listens for its own actor. Built
instead: the bus carries one payload-free `changed`, emitted by a root `onResponse` hook after any
request that wrote something (non-GET/HEAD, status < 400); each stream re-reads *its own* actor's
count and sends a frame only when the number moved.

- **Correctness.** `notify` and `postMessage` run inside `runInTransaction`, which joins the
  caller's transaction. An emit from in there fires before commit, and a stream that re-queries on
  it can read a total that is not yet committed — or that is about to roll back. `onResponse` fires
  after the response, so the write is settled.
- **Reach.** A precise emit only fires where a route passes `events` into the context. Two of the
  three message routes are off-limits this cycle, so the feature would have been live on one of the
  three sites and dead on the other two.
- **Coverage.** The count also drops when a thread is read — `markConversationRead` runs from
  `GET /messages/:id`. A `postMessage`-only emit would never see that; any *other* tab of the same
  actor now clears its badge on the actor's next write.
- **Cost.** One extra `unreadMessageCount` query per open stream per write, and no frame unless the
  number changed. No timers anywhere: nothing polls, nothing wakes on an interval.
- **Leakage.** A stream can only ever read the actor the request authenticated as, because the
  emitted event carries no actor at all. Nothing can push another actor's number into a stream.

`ActionContext`, `actions/notifications/notify.ts` and `actions/messaging/post-message.ts` are
therefore untouched — no Fastify type reached the action layer, and no route outside this ticket's
territory had to change.

### Changed

- `app/plugins/events.ts` (new) — `addEvents` decorates `app.events` (a typed `node:events`
  `EventEmitter<{ changed: []; closing: [] }>`, max listeners lifted so the ceiling is connected
  browsers), the `onResponse` hook that fires `changed`, and the `preClose` hook that fires
  `closing`. `unreadEventStream(source)` is the producer: a web `ReadableStream` that sends
  `retry: 3000`, then the count now, then the count each time it moves; it drops both listeners and
  closes on client disconnect (`AbortSignal`), on `closing`, and when the count cannot be read.
  `unreadEventsRoute(actorType)` is the shared route factory serving `GET <prefix>/events`.
  The in-process limitation is stated in the type's doc comment.
- `app/plugins/events.test.ts` (new) — 8 tests. Five drive the producer directly with a fake
  emitter and a fake count (first frames, no frame when the count did not move, unsubscribe on
  disconnect, unsubscribe on app close, a failing count ends the stream). Three run over a real
  socket, since `app.inject` buffers and a stream never ends: the content type and first frame of
  `/seller/events`; a customer's `POST /messages/:id` reaching the operator's stream as
  `data: 1` while the seller's stream still holds only its own `data: 0`; and the script tag plus
  `script-src 'self'` on all three sites.
- `app/app.ts` — `addEvents(app)` beside the other root plugins.
- `app/sites/shop/storefront.ts`, `app/sites/seller/index.ts`, `app/sites/admin/console.ts` —
  each registers `unreadEventsRoute` inside its existing identity guard.
- `app/sites/{shop,seller,admin}/views/layout.ejs` — the badge span is now always in the markup
  (marked `data-messages-badge`, `empty:hidden` so an empty one is invisible) and carries
  `data-unread-messages` and the number only when there is one, which is what every existing
  assertion about the attribute reads. Plus one `<script defer src="/app.js">` each.
  The marker is `data-messages-badge` rather than `data-unread-…` because
  `sites/seller/routes/{messages,notifications}.test.ts` assert `doesNotMatch(/data-unread/)` over a
  whole page.
- `public/app.js` (new, 21 lines, no dependencies) — finds the badge, derives the stream url from
  the messages link beside it, and writes the count into the span. Returns before anything else when
  `EventSource` is absent.
- `app/plugins/security-headers.ts` — the policy comment said no page has a script tag. CSP itself
  is unchanged: `script-src 'self'` already allowed this, and `EventSource` falls under
  `default-src 'self'`.
- `docs/review.md:88` — the "zero `<script>` tags across the 57 templates" claim now says what is
  actually there and what still holds without JavaScript.

### Left alone deliberately

- `app/actions/**` and `ActionContext` — see the decision above.
- `app/plugins/unread-messages.ts` — the per-request count for the first render is unchanged; the
  stream calls the same `unreadMessageCount` action rather than reaching through the plugin.
- No heartbeat frame. A proxy that closes an idle connection is a deployment concern this prototype
  does not have, and the `retry:` the stream opens with is what a browser needs to come back.
- `docs/architecture.md`'s "no client-side JavaScript required" still reads true and is untouched;
  FEAT-017 owns the documentation refresh.

### Tests

The shared tree carries three workers' in-flight edits and does not currently typecheck or import
(`core/listings/listing-draft.ts` is mid-refactor), so `npm run check` was run in an isolated
worktree at `HEAD` with only this ticket's files applied: **1448 → 1456 pass, 0 fail**, exit 0,
coverage 99.59 lines / 96.70 branches / 99.54 functions against the 95/90 gate. `events.ts` itself
is 99.42/83.33 — the uncovered branch is the `204` a stream answers when no actor resolved, which
no request can reach through the site guards. No lint rule disabled.
