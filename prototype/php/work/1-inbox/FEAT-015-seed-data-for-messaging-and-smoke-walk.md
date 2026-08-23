---
id: FEAT-015
type: feature
status: open
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
