<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ModifierScopeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One option value a {@see Modifier} is gated to show for. Zero rows for a
 * modifier means it shows product-wide.
 */
#[Fillable(['modifier_id', 'seller_id', 'option_value_id'])]
class ModifierScope extends Model
{
    /** @use HasFactory<ModifierScopeFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'mds';
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

    /** @return BelongsTo<OptionValue, $this> */
    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class);
    }
}
