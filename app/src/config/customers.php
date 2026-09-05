<?php

declare(strict_types=1);

use App\Domain\Retention\RetentionDays;

/*
|--------------------------------------------------------------------------
| Customer identity
|--------------------------------------------------------------------------
|
| docs/spec.md §4.1: a storefront visitor gets a customers row lazily, on
| the first event tracked under their id. ANONYMOUS_CUSTOMER_RETENTION_DAYS
| bounds how long an anonymous row that owns nothing survives before
| sweep:customers deletes it ("off" disables pruning). This file loads on
| every boot, so a malformed value RetentionDays::parse() cannot read
| refuses the process at boot, before any sweep needs it.
|
*/

return [
    'anonymous_retention_days' => RetentionDays::parse(env('ANONYMOUS_CUSTOMER_RETENTION_DAYS', '30'), 'ANONYMOUS_CUSTOMER_RETENTION_DAYS')->days,
];
