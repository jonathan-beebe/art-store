<?php

declare(strict_types=1);

namespace App\Domain\Shop;

final class StatusLabel
{
    /**
     * Order and fulfillment states are stored as snake_case; a page reads them
     * back as a sentence.
     */
    public static function humanize(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
}
