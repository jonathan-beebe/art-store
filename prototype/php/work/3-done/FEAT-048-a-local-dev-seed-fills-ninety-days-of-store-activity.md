---
id: FEAT-048
type: feature
status: open
created: 2026-09-02
---

# FEAT-048: a local-dev seed fills ninety days of store activity

## Problem

`make fresh` seeds two admins, six sellers, two customers, 37 listings,
three orders, one payout, and five conversations
(database/seeders/DatabaseSeeder.php), with a handful of analytics events
dated in the last month (CustomerSeeder). The analytics drill-in
(FEAT-045), the funnel (FEAT-046), the channel table (FEAT-047), the log
viewer, and the fraud lens all render against that: a 90-day range shows
a few dozen events, the leaderboard has two actors, no actor is flagged,
and the log store holds only what the developer clicked. There is no way
to see what the analytics system looks like with a store's worth of
traffic behind it, or to rehearse finding a bad actor and acting on them.

## Goal

A developer runs one command after `make fresh` and the admin's analytics,
funnel, channels, log viewer, and fraud lens show a store that has been
open for three months.

## Outcome

A separate make target, absent from `make fresh` and refused in
production, fills the app database, the analytics store, and the log
store with ninety-plus days of activity ending at the moment it runs;
running it twice on the same day produces the same data; it builds on
the sellers, listings, and customers `make fresh` already created and adds
to them; activity ramps: a few new customers in the first month, more in
the second, and a surge in the third, visible on `/admin/analytics` at 90
days as rising daily bars and on the funnel as a rising order count;
across the period a reader can find sign-ups, sign-ins, browsing,
favorites and unfavorites, cart adds, abandoned carts, placed and paid
orders, cancelled orders, support conversations and listing questions,
sellers creating listings, sales, shipments, deliveries, and a weekly
payout, each with the moment it happened, and the app database's own
counts agree with the analytics store's for orders placed and paid;
every named person is from the Harry Potter universe and anonymous
visitors are modelled too, with some verifying part way through and
their history merging; two anonymous actors behave badly — one scrapes
listing pages fast enough to trip the velocity flag, one probes the
server for `/.env`, `/.aws/credentials`, `/wp-login.php`, `/admin`, and
similar paths — and both are findable from the leaderboard or the actor
search, show their probe or scrape trail on "Open in logs" with matching
ip, session, and request ids, and can be blocked from the actor page;
the docs name the target and what it produces; the suite stays green and
the seed itself has a test that runs it against a small window.

## Why it matters

The analytics system was built to understand real customers and to
isolate a bad actor. Neither can be demonstrated, designed against, or
regression-tested on three orders and two customers. A realistic ninety
days is what every future analytics ticket measures itself against, and
what a demo opens on.

## Discovery notes

- Every action takes a `$now` (`AddToCart`, `ToggleFavorite`,
  `PlaceOrder`, `FinalizeOrder`, `CancelOrder`, `MarkShipped`,
  `ConfirmDelivered`, the messaging actions, `Analytics::recordEvent`), so
  the seed can drive the real actions with backdated moments and the two
  stores stay consistent by construction. `OrderHistorySeeder` and
  `CustomerSeeder` are the existing pattern at small scale.
- Sign-ups are `customers.created_at` and `email_verified_at`; sign-ins
  are magic-link story lines. Those reach the analytics store only through
  FEAT-046 and FEAT-047; build this seed after them, or make it grow with
  the vocabulary and say what it covers.
- The log store: `App\Logging\LogStore::append(LogLine)` takes a parsed
  line; `App\Logging\StoryFormatter` shapes them. A request line per
  seeded request (`http.request` will/did with status, path, duration,
  `session_id`, `actor_id`, `request_id`), the magic-link lines, and the
  order stories are what make "Open in logs" tell the same story as the
  actor page. The prober's requests are 404s and 302s, which
  `PageViewCountability` keeps out of the roll-up by design; they exist
  only as log lines, so the log store is where that actor is found.
- The `sid` cookie is the session id and lives a year; each seeded
  visitor gets one, and the prober may rotate ips while keeping one, or
  the reverse — decide which tells the better story.
- Ramp shape: a seeded generator (`mt_srand` with a fixed seed, or a small
  LCG as `App\Domain` value) over a day index, with weekday and evening
  weighting; the third month's surge can follow one listing "going viral"
  so the per-listing pages show it too.
- Analytics is buffered and flushes at 256 rows and at command end;
  thousands of events per run are fine. `Analytics::prune()` and
  `ANALYTICS_RETENTION_DAYS` (30 by default) would delete two thirds of
  this data on the next `orders:sweep`; the target should say so, or set
  a longer window for local dev in `.env.example`.
- Target name: `seed-activity` reads well beside `seed` and `fresh`. The
  make vocabulary in `docs/alignment.md` §6.1 is shared; a php-local target
  can be noted the way `precommit` was.
- Refuse in production the way `db:seed --force` gating works, and refuse
  when the analytics store already holds seeded activity unless a flag
  says to add another run.

## Related work

- FEAT-006 — the demo seed and `make fresh`
- FEAT-015 — messaging seed data and the smoke walk
- FEAT-039, FEAT-044, FEAT-045 — the analytics store, request facts, the drill-in
- FEAT-046, FEAT-047 — the funnel and attribution events this seed should cover
- FEAT-033 — the log store and viewer
