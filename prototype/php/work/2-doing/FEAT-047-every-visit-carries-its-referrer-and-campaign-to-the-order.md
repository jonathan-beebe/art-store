---
id: FEAT-047
type: feature
status: open
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
