<?php

declare(strict_types=1);

namespace App\Domain\Favorites;

use App\Domain\Analytics\AnalyticsEventName;

enum FavoriteChange
{
    case Added;
    case Removed;

    public static function fromCurrentState(bool $isFavorited): self
    {
        return $isFavorited ? self::Removed : self::Added;
    }

    public function listingEvent(): AnalyticsEventName
    {
        return match ($this) {
            self::Added => AnalyticsEventName::ListingFavorite,
            self::Removed => AnalyticsEventName::ListingUnfavorite,
        };
    }
}
