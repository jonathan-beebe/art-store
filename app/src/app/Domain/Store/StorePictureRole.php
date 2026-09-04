<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * What an uploaded store picture is for. Portrait and cover point a column
 * on the profile at the new row; a gallery picture joins the store's
 * pictures and waits for a section to place it.
 */
enum StorePictureRole: string
{
    case Portrait = 'portrait';
    case Cover = 'cover';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Portrait => 'Portrait',
            self::Cover => 'Cover',
            self::Gallery => 'Picture',
        };
    }

    /** The profile column this role fills, or null for a picture no column names. */
    public function profileColumn(): ?string
    {
        return match ($this) {
            self::Portrait => 'portrait_image_id',
            self::Cover => 'cover_image_id',
            self::Gallery => null,
        };
    }
}
