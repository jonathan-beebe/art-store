---
id: FEAT-015
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-015: Seed data for messaging and smoke walk

## Problem
`make fresh` seeds four sellers, one customer, 29 listings, three orders, and a payout (`src/database/seeders/`), and after FEAT-010–FEAT-014 every messaging page in the demo is empty. There is no seeded admin, so `/admin/login` admits nobody and both support routes have no counterpart to open a thread against. `tests/SmokeTest.php` walks the product from sign-in to payout and stops short of messaging.

## Goal
`make fresh` produces a demo where every messaging page has something on it, and the end-to-end walk covers the ask-to-published-FAQ loop.

## Outcome
- After `make fresh` an admin account exists and can sign in at `/admin/login`.
- The seeded data holds one conversation of each of the four kinds, roughly eleven messages across them with some read and some unread, and one published FAQ entry on a seeded listing.
- Signing in as the seeded seller shows a non-zero unread badge and an inbox with threads; the same holds for the seeded customer and the seeded admin.
- The seeded listing's storefront page shows its published question and answer.
- Running `make fresh` twice produces the same demo, and a second seeder run over a seeded database changes nothing.
- The end-to-end walk covers a shopper asking on a listing, the seller replying, the seller publishing the answer, and the answer appearing on the listing page for everyone.
- `README.md`'s seeded-accounts section names the admin.
- Every seeder has a sidecar test; `make check` is green.

## Why it matters
A reviewer's first move is `make fresh` and a click through the three sites. Empty inboxes make the feature look unfinished no matter how well it is built.

## Discovery notes
- `DatabaseSeeder::run()` calls the seeders in order; a `MessagingSeeder` runs last, after `OrderHistorySeeder`, since a `fulfillment` thread needs a real fulfillment. An `AdminSeeder` runs first — the support threads need the admin id.
- `SellerSeeder` publishes its emails as class constants and the other seeders read them. Follow that: the admin email and the seeded listing slug belong as constants other seeders and the tests read, not as repeated strings.
- Idempotence is what `make fresh` needs and what `DatabaseSeederTest` pins today. Read how the existing seeders get it before choosing between `firstOrCreate` and an early return.
- Seeded messages need a spread of `read_at` — some read, some not — or the badge seeds at zero and the demo shows nothing. Freeze time or stamp explicit instants so `last_message_at` orders the inbox the way you intend.
- The seeder writes rows; it does not have to drive the actions. Where the lifecycle matters (a thread's `last_message_at`, a notification row behind a badge) the action walk is worth it — that is the judgement `OrderHistorySeeder` already made for orders.
- `tests/SmokeTest.php` is one long `it()` built from closures per step, bound to `Tests\StorefrontTestCase`. The messaging steps append after the existing walk or slot beside the listing step; keep each step a closure that asserts both the row it wrote and the page the person is looking at.
- `README.md` names the seeded accounts and the counts (`four sellers, one customer, 29 listings…`). Update the numbers there in this ticket; the doc sweep is FEAT-017.

## Related work
- FEAT-010 through FEAT-014. FEAT-006 (seed data and demo reset) is the seeder this extends.

## Working

### Decisions

- **`AdminSeeder`** (`database/seeders/AdminSeeder.php`) publishes `EMAIL =
  'admin@example.com'` and uses `Admin::firstOrCreate` — one admin, Reese
  Calloway, verified. Runs first in `DatabaseSeeder::run()`.
- **`MessagingSeeder`** (`database/seeders/MessagingSeeder.php`) runs last.
  It seeds exactly one conversation per kind, reusing rows the earlier
  seeders already created rather than inventing new sellers/customers:
  - `listing_question` — Casey asks Priya about "Woodfired Vase, Tall" (a
    `for_sale` listing already seeded and already one of Casey's favorites).
    The question and Priya's reply are published as the listing's one FAQ
    entry via `PublishListingFaq`, driven from the same `Message` rows the
    thread holds — the same "Publish as FAQ" flow a seller uses.
  - `fulfillment` — Casey and Noah, on the real shipped fulfillment
    `OrderHistorySeeder` already created for "Kitchen Table, Late Morning".
    Located by `order_id` off the matching `OrderItem` rather than by
    accessing `OrderItem->order` as a relation, since `Model::shouldBeStrict()`
    is on outside production and a lazy load there would raise.
  - `admin_seller` — Reese and Priya. `admin_customer` — Reese and Casey.
  - Each thread is built through `OpenConversation`, `PostMessage`, and
    `MarkConversationRead` (the real action walk, per the discovery note),
    with explicit instants so `last_message_at` orders the inboxes and the
    `read_at` spread is intentional: 11 messages total, 4 already read and 7
    left unread, landing a non-zero unread count for Priya, Casey, and Reese
    alike (verified against `Message::unreadInInboxOf`, the same scope the
    three layout composers read).
  - Idempotence is an early return per thread: `if
    ($conversation->messages()->exists()) { return; }` before posting,
    since `OpenConversation` already finds-or-opens the row by
    `subject_key` but `PostMessage` has no such guard of its own. A second
    run posts no further messages and publishes no second FAQ.
- **Notification count**: `DatabaseSeederTest`'s "notifies sellers and the
  customer" test moved from 5 to 16 (5 from order history + one
  `MessageReceived` per of the 11 seeded messages) — `NotifyOfMessage`
  fires on every `PostMessage` call, seeded or not.
- **Sidecars**: added `AdminSeederTest.php` and `MessagingSeederTest.php`
  beside their seeders (idempotence, kind/message counts, the FAQ text, the
  unread spread). `tests/SidecarsTest.php` does not scan `database/`, so
  this is new coverage rather than something the arch suite would have
  caught — matches the ticket's explicit "every seeder has a sidecar test"
  rather than the pre-existing seeders, which still share only
  `DatabaseSeederTest`.
- **`tests/SmokeTest.php`** gained three closures between `viewListing` and
  `favoriteListing`: `askSellerAQuestion` (anonymous POST to the question
  route), `sellerRepliesAndPublishesFaq` (seller reads the thread, replies,
  submits the "Publish as FAQ" form with the reply's message id as
  `source_message_id`), and `seeFaqOnListingPage` (a plain GET asserting the
  question and answer render — the page needs no auth of its own to prove
  "visible to everyone").
- **README**: seeded-accounts table gains the admin row and a paragraph
  naming which threads are seeded and who has an unread message; the counts
  line ("four sellers, one customer, 29 listings…") and the test-count line
  are updated. `docs/architecture.md`'s own test-count mention is left for
  FEAT-017's doc sweep, per the ticket.

### Verification

- `make check`: 1099 tests, 2460 assertions, PHPStan level max clean, Pint
  clean. (Baseline was 1090/2425.)
- `make fresh` against the running stack, then curled with no
  authentication: `/`, `/seller/login`, `/login`, `/admin/login`, and
  `/art/woodfired-vase-tall` all `200`; the listing page's rendered HTML
  contains the seeded question ("Does this vase come with a stand for
  display?") and answer ("Yes — it ships with a simple wood stand
  included.").
- Walked `/admin/login` for real over curl (POST the seeded email, follow
  the debug magic link, 302 to `/admin`): the dashboard renders "Reese
  Calloway" and `/admin/messages` lists both support threads (Priya Anand,
  Casey Whitfield) with "2 unread" — matches the seeded read/unread split.
  Did not attempt the seller/customer inboxes over curl (session-cookie
  sign-in for those sites is not worth scripting outside the test suite);
  the seeded unread counts for all three actors are pinned instead in
  `MessagingSeederTest`.

### Found, not fixed

- None. The existing seeders (`SellerSeeder`, `ListingSeeder`,
  `CustomerSeeder`, `OrderHistorySeeder`) are not idempotent against a
  second `db:seed` on an already-seeded database (unique `email`/`slug`
  constraints) — pre-existing, out of scope, and not what this ticket's
  idempotence outcome asked for (that outcome is read here as scoped to the
  two seeders this ticket adds).

## Review

Probed the seeded demo against the live stack and every Outcome bullet the
seeders own. `make fresh` twice, then `db:seed --class=AdminSeeder` and
`--class=MessagingSeeder` over the already-seeded database: 1 admin, 4
conversations, 11 messages (4 read / 7 unread), 1 FAQ, 16 notifications —
identical before and after the second run. `Conversation::openFor` is a
`firstOrCreate`, so a re-run does not restamp `last_message_at` either. The
FAQ's `source_message_id` resolves to Priya's answer message (body matches
the published answer). `NotifyOfMessage` notifies
`Conversation::otherParticipant`, so no seeded message notifies its own
sender; 5 + 11 = 16 holds. Live unread counts match the seeded read walk:
Priya 2, Casey 2, the admin 2, Noah 1.

Three changes:

- `tests/SmokeTest.php` — `askSellerAQuestion` asserted a bare
  `assertRedirect()` and never opened the thread, so the step wrote a row
  without checking the page its own comment claims the visitor lands on. It
  now pins the redirect to `shop.messages.show` and reads the question off
  that page.
- `tests/SmokeTest.php` — the FAQ visibility step ran on the client that had
  just asked the question, which does not show the answer reaching anyone
  else. It is now `seeFaqAsAnotherVisitor`: a second anonymous visitor
  arrives, reads the question and answer on the listing page, and the walk's
  own visitor is pinned again for checkout. `verifyEmailFromDebugAlert`
  moved off `Customer::sole()` onto the order's own customer, since a second
  visitor row makes `sole()` ambiguous.
- `MessagingSeederTest` — the read/unread spread and the per-actor unread
  counts were `toBeGreaterThan(0)`; the seeder is deterministic, so they are
  exact now (7 unread / 4 read; Priya 2, Noah 1, Casey 2, admin 2), and the
  FAQ test asserts `source_message_id` points at Priya's answer.

`DatabaseSeederTest` pins no idempotence test at all — the Discovery note's
"what `DatabaseSeederTest` pins today" was already false when the ticket was
written. The Outcome's second-run clause is pinned in `AdminSeederTest` and
`MessagingSeederTest` instead, and `MessagingSeederTest`'s second run is over
a fully seeded database. Left as the worker scoped it: a full `db:seed` over
a seeded database still fails on `SellerSeeder`'s unique email, which is
pre-existing and outside this ticket.

`make check`: 1099 tests, 2467 assertions, PHPStan level max clean, Pint
clean. `make coverage`: 100.0%. README's assertion count updated to 2467.
