---
id: FEAT-045
type: feature
status: open
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
