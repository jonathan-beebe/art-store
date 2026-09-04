<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ListingAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One search-facet fact about a listing: a category-gated property paired
 * with one of its values (Metal: Gold). A property the category
 * marks `multivalued` may hold more than one row on the same listing.
 *
 * @property-read Property $property
 * @property-read PropertyValue $propertyValue
 */
#[Fillable(['listing_id', 'seller_id', 'property_id', 'property_value_id'])]
class ListingAttribute extends Model
{
    /** @use HasFactory<ListingAttributeFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'lat';
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<PropertyValue, $this> */
    public function propertyValue(): BelongsTo
    {
        return $this->belongsTo(PropertyValue::class);
    }
}
