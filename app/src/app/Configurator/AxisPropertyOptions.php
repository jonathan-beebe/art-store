<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Models\Listing;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The catalog properties a seller can turn into a choice on one listing:
 * only the current category's `usable_as_axis` grants — an uncategorized
 * listing offers none, the same as it offers no attribute section.
 */
final class AxisPropertyOptions
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return Collection<int, Property>
     */
    public static function for(Listing $listing): Collection
    {
        return $listing->category_id === null
            ? new Collection
            : Property::query()
                ->whereHas('categoryProperties', fn (Builder $query): Builder => $query
                    ->where('category_id', $listing->category_id)
                    ->where('usable_as_axis', true))
                ->orderBy('name')
                ->get();
    }
}
