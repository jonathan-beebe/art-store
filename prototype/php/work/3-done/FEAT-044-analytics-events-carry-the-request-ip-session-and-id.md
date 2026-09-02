---
id: FEAT-044
type: feature
status: resolved
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

## Working

2026-09-02, branch `php/analytics-admin`.

Commits:

- `54f62e78` feat[php]: analytics events capture the request's ip, session, and id
- `3c5041ea` feat[php]: analytics reader isolates every event from one ip or session
- `b718e83a` feat[php]: analytics events past ANALYTICS_RETENTION_DAYS are pruned
- `e385e35e` docs[php]: analytics events document request facts and retention

Public API:

- `analytics_events` gains nullable indexed `ip` (string 45) and
  `session_id` columns.
- `AnalyticsEvent` gains `ip` and `sessionId` fields and a
  `withRequestFacts(RequestFacts $facts): self` method; `forListing()`'s
  signature is unchanged. `columns()` writes the two new columns.
- New `App\Analytics\RequestFacts` (`ip`, `sessionId`, `requestId`, all
  nullable), with `RequestFacts::current()` (reads the container's current
  request) and `RequestFacts::of(...)` (direct construction, for tests).
- `Analytics::recordEvent()` now fills every event's request facts in
  before buffering; `Analytics::prune(DateTimeImmutable $cutoff, int
  $batchSize = 5000): int` deletes `analytics_events` rows older than the
  cutoff.
- New `App\Analytics\AnalyticsEventRow` (name, occurredAt, subjectType,
  subjectId, actorId, ip, sessionId, requestId) and
  `AnalyticsReport::eventsForIp()` / `eventsForSession()`, both returning
  `list<AnalyticsEventRow>` newest first.
- `App\Logging\LogRetentionDays` becomes `App\Support\RetentionDays`
  (same `parse($raw, $variable)` contract); `config/log_store.php` updated
  to match.
- New `config/analytics.php` (`retention_days`, from
  `ANALYTICS_RETENTION_DAYS`, default `30`, `off` disables).
- New `App\Support\RequestMarks` (`REQUEST_ID_ATTRIBUTE`,
  `SESSION_COOKIE`) — `LogRequestStory::REQUEST_ID_ATTRIBUTE` and
  `NameRequestVisitor::SESSION_COOKIE` now alias it.

Decisions:

- **Where the request id comes from.** `LogRequestStory` already stamps
  every real HTTP request with a `story.request_id` attribute
  (`REQUEST_ID_ATTRIBUTE`) before any route runs, and that is the id
  `RequestFacts::current()` reads back — never a second mint. Its presence
  is also the signal `RequestFacts` gates on to tell a real request from
  the synthetic one the console kernel binds for an artisan run
  (`Illuminate\Foundation\Bootstrap\SetRequestForConsole`), so a CLI run's
  `ip`, `sessionId`, and `requestId` come back null together rather than
  leaking whatever the synthetic request happens to carry.
- **No constructor seam on `Analytics`.** The ticket asked for a value
  "read lazily per `recordEvent`, injectable for tests" without making
  `Analytics` depend on the HTTP kernel at construction. `RequestFacts`
  satisfies both: `current()` resolves the container's request fresh on
  every call (no caching at construction), and `RequestFacts::of(...)`
  lets a test build one directly and hand it to
  `AnalyticsEvent::withRequestFacts()` without touching HTTP at all.
  `Analytics`'s constructor is unchanged.
- **`App\Http` cannot be depended on from outside it.** The Laravel arch
  preset confines `App\Http` to `App\Http` and `App\Providers`, so
  `RequestFacts` (in `App\Analytics`) could not import
  `LogRequestStory`/`NameRequestVisitor` directly. `App\Support\RequestMarks`
  holds the two constants as the single source of truth; the two
  middleware classes now alias them rather than defining their own, so
  every existing caller of either constant keeps working unchanged.
- **What prune deletes.** Whole `analytics_events` rows, batched and
  looped the way `LogStore::prune()` deletes `log_lines` — never nulling
  just the `ip`/`session_id` columns while the row stays. `page_view_counts`
  carries no personal data (a route pattern and a day) and is never
  pruned. No `incremental_vacuum` step: the analytics SQLite connection
  never sets `auto_vacuum = INCREMENTAL` (unlike the bespoke `LogStore`
  PDO handle, Laravel's `SQLiteConnector` has no config key for it), so
  the pragma would be a silent no-op on the file as configured today.
  Reclaiming file size is left for a follow-up if it turns out to matter.
- `orders:sweep`'s three steps (stale-order sweep, log prune, analytics
  prune) are computed independently and combined with `&&` only at the
  return — never short-circuited — so one step's failure never hides
  another's completed work, matching the existing stale-sweep/log-prune
  relationship.

Last `make precommit`: lint (Pint + PHPStan) clean, 3523 tests passed
(10181 assertions).

Left undone: node and rails parity (tracked as an open item in
`docs/alignment.md` §8 and `prototype/php/docs/analytics.md`'s "Open
items" — no tickets filed yet).
