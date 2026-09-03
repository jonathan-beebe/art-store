---
id: MAINT-007
type: maintenance
status: resolved
created: 2026-09-03
---

# MAINT-007: make fresh also resets the log store

## Problem
`make fresh` (prototype/php/Makefile) runs `php artisan migrate:fresh --seed`. That rebuilds the commerce database, and the analytics-connection migrations (`database/migrations/*_create_page_view_counts_table.php`, `*_create_analytics_events_table.php`, `*_create_analytics_visits_table.php`) each `dropIfExists` their table first, so the analytics store is rebuilt too. The log store is a separate SQLite file (`storage/logs.sqlite3`, `LOG_DATABASE_FILE`) opened on its own PDO handle by `App\Logging\LogStore::open()`, and nothing in `make fresh` or `seed:activity` clears it. Every re-seed stacks its log lines on the previous run's. Today the local store holds a pre-fix `seed:activity` run (2026-09-03 04:40Z) whose leaked request-context lines still show at `/admin/logs` beside the post-merge run (16:27Z). `docs/log-store.md` § "The second database" documents "log history survives a rebuild" as the intended shape.

## Goal
One command puts the local store into a reproducible demo state with no history from earlier runs.

## Outcome
After `make fresh`, `/admin/logs` shows only lines written by that rebuild and whatever runs after it; no request, session, or actor from an earlier seed run is queryable. README's make-target table and `docs/log-store.md` describe `make fresh` as resetting the commerce database, the analytics store, and the log store.

## Why it matters
The seed's `/admin/logs` and analytics pages are the demo. Stale lines from an earlier run make a fixed bug look live and make every count on the analytics pages a sum across runs. The human decided: fresh should be completely fresh.

## Discovery notes
- `LogStore::open()` creates the schema on open (`PRAGMA user_version`), so a deleted file needs no migration step; the next open recreates it. The `-wal` and `-shm` siblings go with the main file.
- `php artisan serve` runs each request in a fresh PHP script, so a store deleted under a running `make up` server is reopened by path on the next request.
- `composer.json`'s scripts already use `${ANALYTICS_DATABASE_FILE:-storage/analytics.sqlite3}` to honor the env override in a shell line; the same shape fits `LOG_DATABASE_FILE`. The literal `off` disables the store (`config/log_store.php`) and names no file.
- The entrypoint runs `php artisan migrate --force` before the container's command, so a delete inside the command runs after the entrypoint has finished.

## Related work
- FEAT-048 (the activity seed; commit 89832ff6 fixed the request-context leak whose lines still sit in the store)
- FEAT-039 (the log store)
