<?php

declare(strict_types=1);

use App\Domain\RateLimiting\RateLimitName;
use App\Domain\RateLimiting\RateLimitValue;

/*
|--------------------------------------------------------------------------
| Rate limits
|--------------------------------------------------------------------------
|
| docs/spec.md §3: one budget per limit, `<count>/<window>` (window
| `<n>s`, `<n>m`, or `<n>h`), `"off"` to disable it, the default when its
| env variable is unset. This file loads on every boot, so a value
| `RateLimitValue::parse()` cannot read refuses the process at boot,
| before any request needs it.
|
*/

return array_combine(
    array_map(fn (RateLimitName $limit): string => $limit->value, RateLimitName::cases()),
    array_map(
        fn (RateLimitName $limit): RateLimitValue => RateLimitValue::parse(
            env($limit->envVariable(), $limit->default()),
            $limit->envVariable(),
        ),
        RateLimitName::cases(),
    ),
);
