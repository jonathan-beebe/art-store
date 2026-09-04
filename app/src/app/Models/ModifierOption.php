<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ModifierOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One choice on a `select`-kind {@see Modifier} (a font, a paper stock), with
 * its own price delta — a select modifier prices per chosen option rather
 * than the modifier's own flat add-on.
 */
#[Fillable(['modifier_id', 'seller_id', 'label', 'add_on_price_cents', 'position'])]
class ModifierOption extends Model
{
    /** @use HasFactory<ModifierOptionFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'mdo';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['add_on_price_cents' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<Modifier, $this> */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function addOn(): Money
    {
        return Money::fromCents($this->add_on_price_cents);
    }
}
