<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingRemovalKind;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ListingRemovalFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property-read Listing $listing
 */
#[Fillable(['listing_id', 'seller_id', 'kind', 'reason', 'lifted_at'])]
class ListingRemoval extends Model
{
    /** @use HasFactory<ListingRemovalFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'rmv';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => ListingRemovalKind::class,
            'lifted_at' => 'datetime',
        ];
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

    public function isActive(): bool
    {
        return $this->lifted_at === null;
    }

    public function lift(DateTimeImmutable $now): void
    {
        $this->forceFill(['lifted_at' => $now])->save();
    }
}
