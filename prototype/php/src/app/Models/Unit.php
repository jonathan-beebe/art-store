<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\UnitState;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One serialized (one-of-a-kind) piece of stock behind a {@see Variant} — the
 * primitive that replaces a 52-option axis of numbered lots.
 *
 * @property-read array<string, int|float|string|bool>|null $specs_json
 */
#[Fillable(['variant_id', 'label', 'state', 'condition_note', 'specs_json', 'price_override_cents'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'unt';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'state' => UnitState::class,
            'specs_json' => 'array',
            'price_override_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
