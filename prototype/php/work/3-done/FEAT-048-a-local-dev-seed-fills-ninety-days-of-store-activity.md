---
id: FEAT-048
type: feature
status: resolved
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

## Working

Stage B, branch `php/activity-seed`, on top of stage A
(`d38b9577`/`8aef08a8`/`9f3420ee`). Commits:

- `acb1a965` fix[php]: the activity ramp starts small and surges in the
  third month
- `4bc6bc04` feat[php]: the activity seed models a scraper and a prober
  among the visitors
- `8c452adb` fix[php]: the activity seed tolerates a payout period the
  demo data already settled
- `fc7e6cce` feat[php]: the activity seed writes the log lines its
  requests would have produced

### The ramp

Retuned `ActivityPlan`'s day-based counts (signups, anonymous visits,
per-session view count, listing-creation cadence). A real 92-day run
(seed 2026, `make fresh` then `make seed-activity`) lands:

- New customers (signups + anonymous visits) by month: 65 / 100 / 435 —
  a clear third-month surge, though not the ticket's literal "12/60/300".
  ~118 of those are verified signups (named from `HogwartsRoster`, 136
  entries) — nowhere near the roster's cap.
- Daily listing views on the real run: ~4–8/day in early June, rising
  through the teens and twenties in July, breaking 100 by late August and
  peaking at 426 on the scraper's own day (Aug 30) — a visibly rising bar
  strip at `/admin/analytics?range=90`.
- The catalog grows from `make fresh`'s 37 listings to 174 by the end of
  the window.

**Decision**: "12/60/300 new customers" and "30+ listings" (the
scraper's target) were both irreconcilable with the ticket's other
constraints once traced through the real mechanics — see below — so both
were retuned to what the mechanics actually support, prioritizing the
qualitative outcome (visible ramp and surge, the scraper actually
tripping the velocity flag) over the literal numbers. Recorded here
rather than silently guessed.

### The two bad actors

- **Scraper**: one session, ~340–370 `listing.view` requests every 8–10
  seconds across most of an hour, five days from the window's end,
  rotating two ips in `185.220.101.0/24`. `SeedActivity` resolves its
  listings against the *live* catalog (a fresh query at the moment it
  runs in the chronological sequence), not `ActivityPlan`'s fixed pool —
  necessary because `ActivityPlan::generate()`'s `$listingPoolSize` is
  fixed at the plan's original catalog size (37) for the whole run, and
  `ListingViewCollapse` dedupes to one event per listing per actor per
  UTC hour. Real per-listing-per-hour dedupe means the scraper needs
  **100+ distinct listings inside its hour**, not the ticket's "30+" —
  confirmed live: peak hour 2026-08-30T21, 131 events, one per listing,
  comfortably past `ActorVelocity::THRESHOLD_PER_HOUR` (100).
- **Prober**: one session, ~50–70 `ProbeRequest`s a night across five
  nights roughly a week apart, one to two seconds apart, from a fixed ip
  (`45.155.205.233`), cycling `ProbePaths` (`.env`, `.aws/credentials`,
  `.git/config`, `wp-login.php`, `/admin` → 302, ~24 paths total → 404).
  Two ordinary listing views open the session.

**Confirmed finding** (the ticket asked to confirm this): an actor with
page views but no analytics event is not findable at all through
`/admin/analytics/actors` — `ActorAggregates`/`AnalyticsJump`'s ip search
both read `analytics_events.ip`; `page_view_counts` carries no actor or
ip. The prober's two intro listing views (which dedupe to one stored
event) are what let it exist as a searchable actor; without them it
would be visible only by direct-linking a session id already known some
other way.

### Log lines

`SeedActivity` writes the `http.request` will/did pair (captured for
real via `Tests\CapturedStory` before writing any: `will` carries only
`method`/`path`, `did` carries `status` and a `db` tally, neither carries
an `ip`) for every simulated request — ordinary steps, the scraper's and
prober's own, and the magic-link request/consume a sign-up or verifying
visitor would have produced — straight through
`LogStore::append(LogLine::parse(...))`, bypassing the `Log` facade
entirely (it's `null` in tests anyway). The real domain actions this
command already drives (`AddToCart`, `PlaceOrder`, `CreateListing`, …)
write their own story lines for free through the ordinary `Log` facade,
since they're the same action objects a real request calls — no seed
code needed for those.

### A bug the run revealed

`OrderHistorySeeder` (`make fresh`'s "one payout") runs `RunWeeklyPayout`
for a hardcoded historical date. Once `seed:activity`'s own weekly payout
sweep reaches that week, new activity the plan added on top of the demo
data can leave that seller newly payable for a period that already has a
payout row — the unique `(seller_id, period_start)` key then throws, and
the exception aborted the whole command (no log flush, no `seed_runs`
marker). `runPayouts()` now catches and skips a failing week, the same
tolerance every other step in the command already has for a collision
with real state.

### Live proof

`make fresh` → `make seed-activity` (seed 2026, 92 days) on the dev stack
(`art-store-php`), then signed in as `jonathan-beebe@outlook.com` via
curl (POST `/admin/login` with a GET-first CSRF token, cookie jar, grep
the debug magic link, GET it, follow through to `/admin`):

- Runtime: ~3.8s wall clock for the command itself.
- Report: 539 customers created, 35 orders placed, 3,790 analytics
  events, 539 analytics visits. App DB after the run: 541 customers, 38
  orders, 174 listings.
- `/admin/analytics?range=90`: funnel populated (534 visitors, 3,193
  views, 177 favorites, 231 cart adds, 46 checkouts, 38 placed, 35 paid,
  3 cancelled); channels populated (Direct 141, Google search 110, Email
  campaign: sept-launch 81); daily bar strip rises from single digits in
  June to 100+ by late August, spiking to 426 on the scraper's day.
- Scraper (`cus_01M1JQTAAPWQAV5MH7S5QDA2JB`) tops the "Actors by
  velocity" leaderboard (131/hr); its own page shows the flagged banner
  and `FlaggedActorSummary`'s exact sentence ("131 listing views between
  21:00 and 22:00 UTC on Aug 30 from 185.220.101.138, one every 27.5
  seconds across 131 listings, no favorite or cart event in the range.");
  findable by `?q=185.220.101` on `/admin/analytics/actors`.
- Prober (`cus_01M1JQT8CB2HW3DY2GJQQ01GF5`) findable by
  `?q=45.155.205.233` on `/admin/analytics/actors`; its actor page reads
  "Anonymous visitor"; `/admin/logs?actor=<id>` shows its full 404 trail
  (280 not-found lines), each `wp-login.php`/`.env`/etc. request's
  will/did pair rendering with the exact captured shape.
- Log store: 129,208 total lines in `storage/logs.sqlite3` after the run
  (a persistent dev file, so this also carries prior container activity
  from earlier in the session); 57,422 `http.request` lines, 936
  `magic_link.*` lines.
- `make precommit`: green throughout (3,920–3,933 tests across the four
  commits' checkpoints, 100% lint clean). One transient flake surfaced
  once in a pre-commit-hook run before the payout fix landed — traced to
  the payout collision above, not test infrastructure; five follow-up
  runs after the fix were all green.

### Decisions

1. "12/60/300 new customers" and "30+ listings" (ticket text) are both
   unreachable as literal targets given the real mechanics (roster size,
   per-listing-per-hour dedupe, the fixed listing pool `ActivityPlan`
   sizes sessions against) — retuned to what actually produces the
   qualitative outcome the ticket cares about (a visible ramp and surge,
   a scraper that really trips the flag), numbers recorded above.
2. The scraper resolves its listing pool against a live DB query at
   `SeedActivity` run time rather than `ActivityPlan`'s fixed pool — the
   only way to reach a hundred-plus addressable listings without
   restructuring how every other session addresses the catalog.
3. Not reproduced: every other individual domain story line a real
   controller would also emit beyond `http.request` (there is no
   `page.view`-shaped event in this system beyond the roll-up and
   `listing.view`'s own `did` line, which is reproduced) — the real
   action objects this command drives already write their own lines for
   free.
