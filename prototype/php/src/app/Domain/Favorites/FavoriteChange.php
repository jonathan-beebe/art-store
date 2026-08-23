<?php

declare(strict_types=1);

namespace App\Domain\Favorites;

use App\Domain\Listings\ListingEventType;

enum FavoriteChange
{
    case Added;
    case Removed;

    public static function fromCurrentState(bool $isFavorited): self
    {
        return $isFavorited ? self::Removed : self::Added;
    }

    public function listingEvent(): ListingEventType
    {
        return match ($this) {
            self::Added => ListingEventType::Favorite,
            self::Removed => ListingEventType::Unfavorite,
        };
    }
}
