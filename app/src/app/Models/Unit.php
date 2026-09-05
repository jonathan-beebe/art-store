<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\UnitSale;
use App\Domain\Configurator\UnitState;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
#[Fillable(['variant_id', 'seller_id', 'label', 'state', 'condition_note', 'specs_json', 'price_override_cents'])]
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

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Claims this piece for a buyer — a serialized line's stock movement,
     * mirroring {@see Listing::sell()} one unit at a time.
     */
    public function sell(): self
    {
        $this->update(['state' => UnitSale::afterSale($this->state)]);

        return $this;
    }

    /**
     * Puts this piece back on the storefront, mirroring
     * {@see Listing::restock()}.
     */
    public function restock(): self
    {
        $this->update(['state' => UnitSale::afterRestock($this->state)]);

        return $this;
    }

    /**
     * Takes the row placement reads for update, in id order — the same
     * discipline {@see Listing::lockedForPlacement()} and
     * {@see Variant::lockedForPlacement()} hold their own rows to.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function lockedForPlacement(Builder $query): void
    {
        $query->orderBy('id')->lockForUpdate();
    }
}
