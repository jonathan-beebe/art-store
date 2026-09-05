<?php

declare(strict_types=1);

use App\Logging\LogSlowQueryMs;
use App\Support\RetentionDays;

/*
|--------------------------------------------------------------------------
| Log store
|--------------------------------------------------------------------------
|
| docs/logging.md, docs/spec.md §2.5: every stdout line is also
| mirrored into a queryable SQLite file of its own, separate from the
| commerce database. LOG_DATABASE_FILE names it, beside the commerce file
| (the literal "off" disables the store). LOG_RETENTION_DAYS bounds how
| long a mirrored line survives before the maintenance sweep prunes it
| ("off" disables pruning). LOG_SLOW_QUERY_MS is the threshold a single
| database query's elapsed time must pass to write a query.exceed line
| (docs/spec.md §2.3; "off" disables the line). This file loads on
| every boot, so a malformed value RetentionDays::parse() or
| LogSlowQueryMs::parse() cannot read refuses the process at boot, before
| any sweep or query needs it.
|
*/

return [
    'database_file' => env('LOG_DATABASE_FILE', storage_path('logs.sqlite3')),
    'retention_days' => RetentionDays::parse(env('LOG_RETENTION_DAYS', '14'), 'LOG_RETENTION_DAYS')->days,
    'slow_query_ms' => LogSlowQueryMs::parse(env('LOG_SLOW_QUERY_MS', '50'), 'LOG_SLOW_QUERY_MS')->milliseconds,
];
