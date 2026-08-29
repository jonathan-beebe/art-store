<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\VariantOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One axis's chosen value within a {@see Variant} — at most one row per axis
 * per variant, enforced by the table's own unique index.
 */
#[Fillable(['variant_id', 'axis_id', 'option_value_id'])]
class VariantOption extends Model
{
    /** @use HasFactory<VariantOptionFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'vop';
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** @return BelongsTo<OptionAxis, $this> */
    public function axis(): BelongsTo
    {
        return $this->belongsTo(OptionAxis::class, 'axis_id');
    }

    /** @return BelongsTo<OptionValue, $this> */
    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class);
    }
}
