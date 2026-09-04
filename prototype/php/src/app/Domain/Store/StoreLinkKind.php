<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * A place a store points buyers to, shown under the story. One row per kind
 * per profile.
 */
enum StoreLinkKind: string
{
    case Website = 'website';
    case Instagram = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Instagram => 'Instagram',
        };
    }

    /** The example a blank field shows. */
    public function placeholder(): string
    {
        return match ($this) {
            self::Website => 'https://',
            self::Instagram => '@yourname',
        };
    }
}
