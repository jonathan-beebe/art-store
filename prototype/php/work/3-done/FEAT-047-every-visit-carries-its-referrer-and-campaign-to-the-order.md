---
id: FEAT-047
type: feature
status: resolved
created: 2026-09-02
---

# FEAT-047: every visit carries its referrer and campaign to the order

## Problem

Nothing in the store records where a visitor came from. The `Referer`
header and `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, and
`utm_term` query parameters arrive on the first request of a visit and are
discarded; page views roll up by route pattern only (`page_view_counts`),
and analytics events carry ip, session, and request id (FEAT-044) but no
origin. An admin cannot say which channel produced a view, a favorite, a
cart add, or an order.

## Goal

An admin reads views, cart adds, and orders by the channel that brought
the visitor, for a range and the range before it.

## Outcome

The first page view of a visit records the referrer host and any UTM
parameters, and every later event in that visit, up to and including the
order events (FEAT-046), can be attributed to that origin without the
visitor carrying anything in the URL after the first page; an admin page
lists channels (derived from referrer and UTM: direct, a named campaign, a
referring site, a search engine) with visitors, listing views, cart adds,
orders placed, and orders paid per channel for the range, the change
against the range before, and a drill-in to the visitors of one channel;
a visitor's analytics page shows the origin of each of their visits; a
UTM value is stored as given and never rendered unescaped; the docs and
the alignment contract name what is captured and where; the suite stays
green.

## Why it matters

Channel is the question every marketing decision waits on: which spend
paid for itself. Attribution captured at the first touch and joined to the
order is the whole of that answer; captured anywhere else it is a guess.

## Discovery notes

- Where it lands: an `analytics_visits` row per visit (session id from
  the `sid` cookie, first seen, referrer host, utm fields, landing path),
  or the origin fields on the first `page.view`-shaped event. The `sid`
  cookie lives a year, so a "visit" needs a definition — a gap of thirty
  minutes without a request starts a new one, or the first touch per
  cookie stands for the whole year. First-touch is simpler and answers
  the marketing question; say which in the docs.
- Capture belongs where the session cookie is minted
  (`App\Http\Middleware\NameRequestVisitor`) or beside `RollUpPageViews`,
  recording through `Analytics` so it buffers and flushes with everything
  else. `Referer` is absent on direct visits and on same-site navigation;
  only a foreign host is an origin.
- Channel derivation is a pure domain value (`App\Domain\Analytics`):
  utm_medium/utm_source first, then the referrer host mapped to search,
  social, or referral, then direct.
- The report joins channel to events by `session_id`, which every event
  already carries, and to orders through FEAT-046's order events; no
  commerce-database join is needed for the channel table.
- The customer merge re-points `actor_id`; a visit row keyed by session
  needs no re-pointing.
- Node and Rails owe parity once the captured fields land in
  `docs/alignment.md` §2.6.

## Related work

- FEAT-044 — session id on every event, the join key
- FEAT-045 — the admin drill-in the channel page joins
- FEAT-046 — order events, the end of the attributed path
- FEAT-002 — the visitor identity cookie

## Working

Commits (stage A, already landed on this branch, plus stage B below):

- `632f65f4` — the first request of a visit records its landing path,
  referrer, and campaign
- `a929cb7b` — a visit's channel derives from its campaign, then its
  referrer, then direct
- `5abe6b51` — `ChannelTable::forRange()` and `AnalyticsReport::visitsForActor()`
  read the store
- `ca49354d` — `/admin/analytics/channels` and `/admin/analytics/channels/{key}`,
  the entry page's Channels section
- `4b2dacd1` — the actor page's Visits panel and "First channel" fact
- `ec120567` — `Analytics::prune()` deletes stale `analytics_visits` rows too

A visit is first-touch per session cookie: `App\Analytics\Analytics::recordVisit()`
buffers whatever `App\Analytics\AnalyticsVisit::fromRequest()` builds off
the first request of a session, and `flush()` writes it `INSERT OR
IGNORE` on `session_id`, so only that first request's row ever lands —
every later request in the `sid` cookie's year-long life is a no-op
write. First-touch answers "which channel brought this visitor", the
question a marketing decision waits on; a thirty-minute session-gap
definition was considered and set aside for that reason.

The channel join: `App\Analytics\Admin\ChannelTable` and `ChannelVisits`
both derive a `Channel` in PHP per raw attribution tuple
(`Channel::derive()` is not expressible in SQL) and fold rows whose
derived key matches into one, after SQL has already grouped by the raw
tuple — `ChannelTable` groups `analytics_visits` and a join of
`analytics_events` to `analytics_visits` on `session_id`; `ChannelVisits`
reads every visit in the range and keeps the ones whose derived key
matches the requested one, since a channel key names no stored row and
"found" only means "at least one visit in the range derives to it" —
an unmatched key answers 404.

Decisions:

- The visits panel and "First channel" fact live on `EntityActivityView`
  itself (`$visits`, populated only by `forActor()`), unlike `Funnel`'s
  own separate top-level view variable — the fact needs the same query
  the panel does, and both differ by entity kind the way every other
  fact and tile already does.
- Every admin analytics page's own analytics-connection query count
  carries one query neither the ticket nor earlier stages named:
  `RollUpPageViews` upserts `page_view_counts` for every countable admin
  hit, not just the storefront's — `docs/analytics.md`'s query-count
  table now says so.
- `orders:sweep` had no printed line for the analytics prune's own count
  before this ticket (the log-store prune's count was never printed
  either) — stage B adds one, since "the count and log line include
  them" needed a line to include them into.

`make precommit`: 3860 tests passed (11036 assertions), lint clean. Two of
the four commits above needed a bare retry — the hook's own `make
precommit` run failed once with no captured detail and once with a
nonsensical Arch complaint against a test file, neither reproducing on
the immediate retry with no code change in between; every manual
`make precommit` run this stage was green.
