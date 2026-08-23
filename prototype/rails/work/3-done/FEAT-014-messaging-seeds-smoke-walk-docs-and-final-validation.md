---
id: FEAT-014
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-014: Messaging seeds, smoke walk, docs, and final validation

## Problem
Once FEAT-009 to FEAT-013 land the feature exists without a record of it: `src/db/seeds/` creates no conversations or FAQs so a fresh `make up` shows empty inboxes; `docs/` has no `messaging.md` and `docs/architecture.md`'s deployables, layers, ER diagram, and notifications sections describe a two-actor app; `test/smoke_test.rb` walks the product without sending a message; `README.md`'s seeded accounts and feature list predate messaging.

## Goal
Someone evaluating this prototype can run it, see messaging working with seeded data, and read how it is designed, the same way they can for the Node prototype.

## Outcome
`make fresh` seeds at least one thread of each of the four kinds with messages and one published FAQ, and the seed is idempotent; the smoke test walks a shopper asking a question, the seller answering and publishing it, and the FAQ appearing on the listing page; `docs/messaging.md` exists with the same three questions the Node doc answers (how a question becomes an FAQ, who may read and post, where unread counts come from) drawn as Mermaid diagrams and referring to the Rails files; `docs/architecture.md`, `docs/data-model.md`, `docs/ontology.md`, and `README.md` describe three sites, the admin actor, messaging, the Hotwire stack, and the commands; `docs/review.md` is refreshed; the full suite and `make coverage` pass; a manual curl or browser walk of the seeded data is recorded in the ticket.

## Why it matters
The stack comparison is read as much as it is run. A feature with no seed, no smoke, and no doc is invisible to the reviewers who will weigh Rails against PHP and Node.

## Discovery notes
`src/db/seeds/order_history.rb` already builds a shipped fulfillment the `fulfillment` thread can hang off; `Seeds::Messaging` is the natural fifth seed file and `seeds_test.rb` asserts counts. The Node doc's structure (kinds table, three "Question:" sections each with a Mermaid diagram and a "Caveats" paragraph) is the one to mirror so the two docs compare side by side; use the `diagramming` skill. The architecture doc's "Sites" table gains the admin row, the layers section notes `app/channels` and `app/javascript`, the ER diagram gains `admins`, `conversations`, `messages`, `listing_faqs`. The README's "no JavaScript" sentence becomes a sentence about what the importmap loads and what works without it. The review doc's comparison with the other prototypes is where the Hotwire point is made — state facts (lines of JavaScript, gems added, what broadcasts), no adjectives.

## Related work
- FEAT-006 (seed data)
- FEAT-007 (docs and diagrams)
- FEAT-008 (final validation)
- FEAT-009
- FEAT-010
- FEAT-011
- FEAT-012
- FEAT-013
- prototype/node/docs/messaging.md
- prototype/node/work/3-done/FEAT-017-final-validation-and-documentation-refresh.md

## Working

### What I verified before changing anything

Baseline 734 runs, 2270 assertions, 100% line coverage. `src/db/seeds/` held
five files and no conversation; `docs/` had no `messaging.md`;
`docs/review.md:65` claimed "no `<script>` in any view, no `app/javascript`, no
importmap", which FEAT-013 made false; `test/smoke_test.rb` walked listing →
payout with no message in it. `README.md` already carried the admin site, the
`ops@example.com` row and the JavaScript section FEAT-013 rewrote.

### Seeds

`src/db/seeds/messaging.rb` (`Seeds::Messaging`), required and called from
`db/seeds.rb` inside the existing `Seller.exists?` guard, so idempotency is the
same guard the other five seeds sit behind. The counts line now reads:

    Seeded 1 admin, 4 sellers, 29 listings, 1 customers, 3 orders,
    4 conversations, 9 messages, 1 published FAQ.

One thread of each kind:

| kind | Sides | Subject | Messages |
| --- | --- | --- | --- |
| `admin_seller` | ops ↔ maya | — | 2, about the payout schedule |
| `admin_customer` | ops ↔ casey | — | 2, about when a seller is paid |
| `fulfillment` | noah ↔ casey | `Fulfillment.shipped.sole` (order #2) | 3, about the delivery |
| `listing_question` | priya ↔ casey | "Woodfired Vase, Tall" | 2, about the vase |

The FAQ comes off the listing question through the same call the portal's
button makes: `ListingFaq.draft_from(conversation)` for the pair and the
`source_message`, then `ListingFaq.publish`.

Two decisions in the seed:

- `say` writes the message through `Conversation#post!` and then
  `update_columns(created_at:, updated_at:)`. `post!` takes `at:` for
  `last_message_at` alone, so without the second write a July thread would
  show today's date on every message.
- `answer` calls `read_by!` before posting. A reply reads the thread it is
  replying in, so the answering side opens a clear inbox and the asking side
  has one message waiting. The first pass had the customer's "Perfect, thank
  you." as a plain `say`, and the curl walk caught it: casey showed 3 unread
  for 3 threads, one of them a message she had answered without reading.

Every thread ends on a message its other side has not opened. Four badges
waiting: maya 1, noah 1, casey 2, ops 0 (ops answered last in both desk
threads), priya 0.

`test/seeds_test.rb` gains three tests (one thread per kind each ending unread,
the fulfillment thread against the shipped order, the published entry matching
its thread) and the notification count moves 5 → 14 (9 of them "New message").

### Smoke

`test/smoke_test.rb` keeps its walk and gains four steps after the payout:
`ask_the_seller_a_question` (the buyer asks from the listing page, which is
`sold` by then and still reachable through `Listing.on_storefront`),
`answer_from_the_inbox` (the seller reads the unread badge in
`/seller/messages`, opens the thread, replies, and the counts move on both
sides), `publish_the_answer` (the thread's pre-filled form, then the POST with
`source_message_id`), and `read_the_listing_as_a_stranger` (a third
`open_session` with no cookie reading the pair on `/art/:slug`). 105 → 142
assertions in the one test.

### Docs

- `docs/messaging.md` — new, mirroring `prototype/node/docs/messaging.md`:
  kinds table, "A question becomes a published FAQ" (sequence), "Who may read,
  who may post" (flowchart), "Unread counts" (flowchart), plus a "Live
  updates" section the Node doc has no counterpart for — the per-participant
  streams, the after-commit broadcasts, Solid Cable in the same SQLite file,
  and what works with JavaScript off.
- `docs/architecture.md` — three sites in the opening line and the Sites table
  (admin, `session[:admin_id]`, slate), `/cable` and `solid_cable_messages` in
  the deployables diagram, `app/javascript` in the layers diagram and table,
  the "no `app/channels`" paragraph and why, the admin arm of identity, a
  second ER diagram for messaging, `Notification.new_message`, the gem table
  (turbo-rails 2.0.23, importmap-rails 2.2.3, solid_cable 4.0.2,
  tailwindcss-rails 4.6.0), the broadcast test helper under Testing, and a
  Messaging section pointing at `messaging.md`.
- `docs/data-model.md` — schema version, the four new tables in the ER diagram,
  `actor_type` and `recipient_type` gaining `admin`, four new caveats,
  `solid_cable_messages` named as omitted.
- `docs/ontology.md` — Admin under Roles, Conversation / Message / Listing FAQ
  under "Identity and messaging", the Notification entry gaining "New message",
  four vocabulary notes (thread, the desk, unread, published).
- `docs/review.md` — the "No JavaScript required" row rewritten and a Hotwire
  row added; a "Messaging and the admin site" section with the route helper and
  test for each capability; a factual comparison of the two live
  implementations (Node: `app/plugins/events.ts` 174 lines, `src/public/app.js`
  21 lines, one number per frame, in-process bus; Rails: 4 lines of application
  JavaScript plus the gem's `turbo.min.js`, rendered message rows and badges
  both ways, after-commit, Solid Cable in the same SQLite file); stale counts
  fixed (20 of 85 files without a mirrored test, 30 tickets, FEAT-001 …
  FEAT-014, `messaging.md` in the diagramming row, 737 tests).
- `README.md` — three sites in the opening line, the seeded messaging in the
  seeded-accounts paragraph, the smoke paragraph gaining the question → FAQ
  leg, the coverage line 567 → 737, magic links covering all three sites and
  `Admin.claim`, the `docs/` line naming the feature docs.

### Final validation

- `make fresh`: drops, creates, migrates and seeds clean, printing the counts
  line above.
- `make test`: 737 runs, 2317 assertions, 0 failures, 0 errors, 0 skips.
- `docker compose run --rm -e COVERAGE_MIN=100 app bin/rails test`: same, with
  line coverage 1231/1231 (100.00%) and every group at 100%.
- `bin/rails zeitwerk:check`: all is good.
- Curl walk over the running dev server (modern UA for `allow_browser`, CSRF
  token read from each page):
  1. Anonymous `/art/woodfired-vase-tall` — 200, one `data-faq` block holding
     the seeded question and answer.
  2. Magic-link sign-in for casey (`/login`), maya, noah, priya
     (`/seller/login`) and ops (`/admin/login`) — each lands on its own site.
  3. Nav badges before anyone opens a thread: casey 2, maya 1, noah 1,
     priya 0, ops 0.
  4. Inboxes: `/messages` 3 rows (unread 1/0/1), `/seller/messages` 1 row for
     maya, noah and priya, `/admin/messages` 2 rows. Topics render as
     "Art Store support", "order #2", "“Woodfired Vase, Tall”".
  5. Thread pages on all three sites: 200, the right message count, two
     `<turbo-cable-stream-source>` elements each (the conversation stream and
     the badge stream).
  6. `/seller/messages/4` as maya (priya's thread) 404, `/seller/messages/999`
     404, `/messages/1` as casey 404, `/admin/messages/3` as ops 404.
  7. Casey's badge drops 2 → 1 after opening one thread.
  8. `POST /admin/sellers/4/conversation` twice — both redirect to
     `/admin/messages/5`, so find-or-open reuses the thread.
  9. A cookieless visitor asks on `/art/nine-herons` (302 to `/messages/6`),
     maya's badge moves to 1, she opens the thread, replies (302 back to the
     thread), the publish form arrives pre-filled with
     `source_message_id=11`, she publishes, and a browser with no cookie at
     all reads the pair on `/art/nine-herons`.
  10. `solid_cable_messages` holds the broadcasts those writes produced —
      `append` to `messages_conversation_6` on each participant's stream and
      `replace` on `unread_messages_seller_1` / `unread_messages_customer_8`.
- `make fresh` again afterwards, so the dev database is back to the seeded
  state.

Nothing in FEAT-009 … FEAT-013 was broken.

### Left alone

`MAINT-001`, `RFCTR-014` and `RFCTR-015` in `work/1-inbox/`. The known gaps in
`docs/review.md`, which this ticket had no fix for. The `Notifications` badge
in the seller nav.
