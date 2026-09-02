---
id: FEAT-045
type: feature
status: resolved
created: 2026-09-02
---

# FEAT-045: an admin analytics drill-in from events to listings and actors

## Problem

The admin reads analytics from two flat pages: `/admin/stats` (page views
by day and pattern, a platform tally of listing events) and each listing's
detail page (three counts). There is no way to compare a range with the
one before it, to see which listings or which actors carried an event, to
follow one actor's or one listing's events as a series, or to spot a
scripted visitor by its rate. The analytics store already holds every
event with its name, moment, subject, and actor (FEAT-039), and FEAT-044
adds the request's ip, session, and id.

## Goal

An admin can go from "what happened in this range" to "which listing or
who did it" to "everything that one did", and can isolate a bad actor
from the same screens.

## Outcome

An `/admin/analytics` page shows every event name with its count for the
chosen range (7, 30, or 90 days), the count for the range before, the
change between them, a daily bar strip, and distinct subject and actor
counts, plus the actors with the highest events-per-hour peak in the
range; search narrows both tables by event name, id, email, or ip, and a
pasted listing or customer id jumps to that entity; an event's own page
shows the range tiles, the daily bars, and a breakdown by listing or by
actor with each one's share; an all-actors page pages through every actor
in the range sorted by most active or most recent, filtered by anonymous
or verified; a listing's or an actor's page shows an identity card with
its facts and links (open the listing or customer, open the log viewer on
the actor, block the customer), range tiles, a daily strip (hourly for a
flagged actor), and the event feed newest first with each event's ip,
session, and request id and a filter by event name; an actor whose peak
hour passes a threshold is flagged on the leaderboard and on its page with
the facts that flagged it; every page carries the admin chrome and its
dark variant; the docs and the alignment contract name the routes; the
suite stays green.

## Why it matters

The analytics store was built to understand real customers and to isolate
a bad actor. Both need the same drill-in: a range compared with the last,
one tap to the entity, one tap to its series. Without it the data sits in
a file nobody can question.

## Discovery notes

- The design is a clickable prototype, matched to the admin chrome:
  https://claude.ai/code/artifact/4418bf2e-1563-4c8f-ba89-84c7eed0e126
  Its working source is `__local__/design/admin-analytics/Main.dc.html`.
- Readers today: `App\Analytics\AnalyticsReport` (three queries) and
  `App\Models\PageViewCount` (week/day/pattern totals). The drill-in wants
  a small query layer of its own under `App\Analytics\Admin\`, the way the
  log viewer keeps `App\Logging\Admin\`; range math, change formatting,
  bar scaling, and the velocity threshold are pure and belong in
  `App\Domain\Analytics\`.
- `page.view` is a roll-up (`page_view_counts`), so its row has no
  subjects or actors and its breakdown is by route pattern.
- Velocity: events per UTC hour per actor, from `analytics_events` grouped
  by `actor_id` and `strftime('%Y-%m-%dT%H', occurred_at)`. One threshold
  constant is enough to start.
- The admin log viewer already links by actor (`/admin/logs?actor=`), and
  `Admin\CustomerBlockController` already blocks; the actor page links to
  both rather than duplicating them.
- `/admin/stats` stays as it is; whether it later redirects to
  `/admin/analytics` is a separate decision.
- docs/alignment.md §5 lists the admin routes; new routes go there with a
  §8 entry. Node and rails owe parity.

## Related work

- FEAT-039 — the analytics store and the `Analytics` entry point
- FEAT-044 — ip, session, and request id on every event (builds first)
- FEAT-033 — the log viewer this links to
- FEAT-023 — `/admin/stats`, the page this grows past

## Working

2026-09-02, branch `php/analytics-admin`. Design:
https://claude.ai/code/artifact/4418bf2e-1563-4c8f-ba89-84c7eed0e126
(working source `__local__/design/admin-analytics/Main.dc.html`).

Commits, by stage:

- Pure domain values
  - `e3cc7792` analytics ranges, changes, bar strips, and the velocity
    threshold are pure domain values
- Entry page
  - `47792c87` event totals, the actor leaderboard, and id jumps read the
    analytics store for the admin
  - `ec880d46` `/admin/analytics` compares every event with the range
    before and ranks actors by velocity
  - `951d5273` the admin nav links to analytics
- Event page
  - `433579fb` one event's range, daily bars, and breakdown by listing,
    actor, or pattern read the analytics store
  - `c4ec1590` `/admin/analytics/events/{name}` stacks one event's tiles,
    daily bars, and breakdown
- All-actors page
  - `b835e7f4` every actor in a range lists paged, sorted by most active
    or most recent
  - `63687b09` `/admin/analytics/actors` pages through every actor in the
    range
- Listing and actor pages
  - `7930e6a6` one listing's or actor's identity, tiles, strip, and event
    feed read the analytics store
  - `33e11d15` `/admin/analytics/listings/{id}` and `/actors/{id}` stack an
    identity card, tiles, a strip, and the event feed
- Polish, fixes, and coverage
  - `65cdd5b6` the smoke walk crosses the analytics drill-in
  - `01acbb34` docs: drop a redundant clause from `EntityStripBar`'s
    docblock
  - `97434969` the first request's analytics events carry the session it
    was given
  - `cf71c688` the analytics entry page's search narrows the events table
    too
  - `8b8d8225` the analytics query classes converge on one label, icon,
    and instant source
  - `0dfb73db` state analytics comments as facts, not contrasts
  - `c3ddb68a` the seeded customers carry a month of recent activity for
    the analytics drill-in
  - `4a8598de` every analytics admin page runs on a fixed query count
  - `5d40ce73` an entity page's event feed reads newest first

Decisions made along the way:

- Event labels, verbs, and icon paths live on `AnalyticsEventName` itself
  (`pluralLabel()`, `verb()`, `iconPath()`) and on `EventBreakdown` for the
  `page.view` roll-up, which carries no case of its own — `EventTotals` and
  `EventDetail` each started with an identical private copy; `8b8d8225`
  converges them on the enum.
- `ActorAggregates::forRange()` is the one aggregation both
  `ActorLeaderboard` (top six by peak) and `ActorList` (sorted, paged) read,
  so the leaderboard and the all-actors page can never disagree about one
  actor's numbers.
- Blocking a customer stays on the customer page. The actor page's identity
  card links to the customer page's own block form; the block flow itself
  lives only there.
- A listing's identity card offers no "Open in logs" action — the log
  viewer filters by actor only, so a listing has nothing to open there.
- `created_at` is the listing identity card's "Published" fact — the
  listing model carries no separate publication timestamp.
- A first request's analytics event carries the session id the response is
  about to set: `RequestFacts` falls back to `Cookie::queued(RequestMarks::SESSION_COOKIE)`
  when the incoming request has no `sid` cookie yet (`97434969`).
- `/admin/stats` is untouched — the ticket's own decision, carried through
  unchanged.

Live walk: this closing pass touches only docs and work tickets, so Docker
stays out of scope for it — the walk on record is the automated suite's.
`SmokeTest` walks the drill-in end to end (visitor favorites and carts a
listing; admin opens the entry page, the favorite event's page, the
all-actors page, and the visitor's own actor page — `65cdd5b6`), each stop
asserting 200 and one recognisable string. Every page's controller and
request tests separately assert 400 on an unrecognised filter value (range,
actors, sort, event, breakdown) and 404 on an unknown event name, listing
id, or customer id.

Open follow-ups:

- `EventTotals`, `ActorAggregates`, `EventDetail`, and `EntityActivity` each
  hand-write the same `whereBetween('occurred_at', [SqlInstant::format(...)])`
  query fragment — flagged in review, not extracted this round.
- Node and rails owe the same drill-in (`docs/alignment.md` §8).
- Whether "Published" should read a dedicated `published_at` column is
  open — nothing today distinguishes a listing's creation from its first
  time on the storefront, so `created_at` stands in for both.
