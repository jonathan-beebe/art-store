<?php

declare(strict_types=1);

use App\Logging\LogRetentionDays;

/*
|--------------------------------------------------------------------------
| Log store
|--------------------------------------------------------------------------
|
| docs/logging.md, docs/alignment.md §2.5: every stdout line is also
| mirrored into a queryable SQLite file of its own, separate from the
| commerce database. LOG_DATABASE_FILE names it, beside the commerce file
| (the literal "off" disables the store). LOG_RETENTION_DAYS bounds how
| long a mirrored line survives before the maintenance sweep prunes it
| ("off" disables pruning). This file loads on every boot, so a malformed
| LOG_RETENTION_DAYS value LogRetentionDays::parse() cannot read refuses
| the process before it answers a request rather than on the sweep that
| would have needed it.
|
*/

return [
    'database_file' => env('LOG_DATABASE_FILE', storage_path('logs.sqlite3')),
    'retention_days' => LogRetentionDays::parse(env('LOG_RETENTION_DAYS', '14'), 'LOG_RETENTION_DAYS')->days,
];
