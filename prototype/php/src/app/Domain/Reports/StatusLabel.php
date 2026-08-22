<?php

namespace App\Domain\Reports;

use BackedEnum;

final class StatusLabel
{
    public static function of(BackedEnum $status): string
    {
        return ucfirst(str_replace('_', ' ', (string) $status->value));
    }
}
