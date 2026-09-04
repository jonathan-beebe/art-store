<?php

declare(strict_types=1);

use App\Support\RetentionDays;

/*
|--------------------------------------------------------------------------
| Analytics store
|--------------------------------------------------------------------------
|
| docs/analytics.md, docs/spec.md §2.6: analytics_events carries an
| ip and a session id, personal data the platform keeps only as long as it
| is useful for isolating a bad actor. ANALYTICS_RETENTION_DAYS bounds how
| long a row survives before the maintenance sweep prunes it ("off"
| disables pruning). This file loads on every boot, so a malformed value
| RetentionDays::parse() cannot read refuses the process before it answers
| a request rather than on the sweep that would have needed it.
|
*/

return [
    'retention_days' => RetentionDays::parse(env('ANALYTICS_RETENTION_DAYS', '30'), 'ANALYTICS_RETENTION_DAYS')->days,
];
