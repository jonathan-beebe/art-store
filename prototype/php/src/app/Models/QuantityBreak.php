<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\QuantityDiscount;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\QuantityBreakFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One quantity-break tier a listing offers: at `min_qty` or more, the
 * resolved unit price carries a `discount_bps` discount.
 */
#[Fillable(['listing_id', 'min_qty', 'discount_bps'])]
class QuantityBreak extends Model
{
    /** @use HasFactory<QuantityBreakFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'qbk';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['min_qty' => 'integer', 'discount_bps' => 'integer'];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function toDomain(): QuantityDiscount
    {
        return QuantityDiscount::of($this->min_qty, $this->discount_bps);
    }
}
