<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\FlowStep;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\FulfillmentFlowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * A seller's own ordered way of getting a parcel out the door. One flow per
 * seller is their default; a listing may name another.
 *
 * @property-read Seller $seller
 * @property string $name
 */
#[Fillable(['seller_id', 'name', 'is_default'])]
class FulfillmentFlow extends Model
{
    /** @use HasFactory<FulfillmentFlowFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'ffl';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return HasMany<FulfillmentFlowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(FulfillmentFlowStep::class)->orderBy('position');
    }

    /**
     * The listings that name this flow.
     *
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * The flow as the pure core reads it, in position order.
     *
     * @return list<FlowStep>
     */
    public function flowSteps(): array
    {
        return array_values($this->steps
            ->sortBy('position')
            ->map(fn (FulfillmentFlowStep $step): FlowStep => $step->toFlowStep())
            ->all());
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function defaults(Builder $query): void
    {
        $query->where('is_default', true);
    }
}
