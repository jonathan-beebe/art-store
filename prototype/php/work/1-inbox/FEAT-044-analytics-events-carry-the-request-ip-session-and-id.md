---
id: FEAT-044
type: feature
status: open
created: 2026-09-02
---

# FEAT-044: analytics events carry the request ip, session, and id

## Problem

Every analytics event records who (`actor_id`) and what (`subject_type`,
`subject_id`) in `analytics_events` (app/Analytics/AnalyticsEvent.php,
`columns()`), and nothing else about the request that produced it: `data`
is empty JSON. A scripted or abusive visitor is therefore traceable only
by the anonymous customer id its cookie carries. The request's IP,
session id, and request id exist only in the log store (`log_lines`,
app/Logging/LogStore.php), which `orders:sweep` prunes after
`LOG_RETENTION_DAYS` (default 14), so the link between an event and its
request expires while the event stays.

## Goal

An admin can isolate everything one IP or one session did, and step from
any analytics event to the request that produced it.

## Outcome

Listing every analytics event from one IP, or from one session, is one
query on the analytics store; every stored event names the request it
came from, and the admin log viewer can be opened on that request; the
code that records an event passes none of these request facts (the
analytics system captures them itself); analytics rows older than a
retention window are pruned by the maintenance sweep, and the window is
configurable and can be turned off; `docs/alignment.md` §2.6 and
`prototype/php/docs/analytics.md` name the request fields and the
retention; the suite stays green.

## Why it matters

The analytics store exists to understand real customers and to isolate a
bad actor. An anonymous customer id is one cookie; an attacker rotates it
for free, while an IP and a session are what the operator can block and
correlate. Once the store carries an IP it holds personal data, and a
retention window is the price of keeping it.

## Discovery notes

- `ip` and `session_id` as their own indexed columns on `analytics_events`
  read well: "everything from this IP" and "everything in this session" are
  index hits, and the admin analytics design drills on both. `request_id`
  fits in `data` — it is a cross-link, never a filter on its own.
- `App\Analytics\Analytics::recordEvent()` is the one place every event
  passes through; capturing the request facts there keeps every caller
  (`ToggleFavorite`, `AddToCart`, the shop listing page, the seeder)
  untouched. A CLI run has no request; the fields are nullable.
- `Request::ip()` honours the `TRUSTED_PROXIES` rule the app already
  configures (docs/alignment.md §3), so the value behind Render's proxy is
  the visitor's address. The request id is minted by the logging pipeline
  (app/Logging/StoryFormatter.php and the request middleware) — read how
  the story reaches it before minting a second one.
- Retention: `LogStore::prune()` and `SweepOrders` are the pattern
  (batched deletes, `incremental_vacuum`); `ANALYTICS_RETENTION_DAYS` with
  `off` mirrors `LOG_RETENTION_DAYS`. Decide whether the window prunes
  whole rows or nulls the ip and session columns while the counts stay —
  the roll-up `page_view_counts` never carries personal data either way.
- The admin analytics design canvas shows ip, session, and request on
  every feed row and an "Open in logs" action on the actor page:
  https://claude.ai/code/artifact/4418bf2e-1563-4c8f-ba89-84c7eed0e126

## Related work

- FEAT-039 — the analytics store and the `Analytics` entry point
- FEAT-033 — the log store and its retention window
