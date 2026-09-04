<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * Whether `/s/{slug}` answers a store page to a buyer. A hidden store still
 * sells: its listings stay on the storefront and its cards show the name as
 * plain text.
 */
enum StoreVisibility: string
{
    case Published = 'published';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Published => 'Published',
            self::Hidden => 'Hidden',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
