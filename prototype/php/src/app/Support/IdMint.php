<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Identifiers\PrefixedId;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Mints a prefixed id for something that has no table: a request, a browser
 * session, a unit of work. The shape is the one every row's key already
 * holds, so a `ses_…` and an `ord_…` read the same way side by side in a log
 * line.
 *
 * The ULID is drawn from the application clock, so a test that freezes or
 * travels time gets ids that sort in minting order.
 */
final class IdMint
{
    private function __construct() {} // @codeCoverageIgnore

    public static function of(string $prefix): string
    {
        return (string) PrefixedId::of($prefix, (string) Str::ulid(Date::now()));
    }
}
