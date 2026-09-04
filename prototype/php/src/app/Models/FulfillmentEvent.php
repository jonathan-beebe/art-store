<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\FulfillmentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One appended row of a fulfillment's life: a step the seller completed, or a
 * transition that moved `fulfillments.status`. Nothing here is ever updated
 * or deleted — a correction is another row.
 *
 * @property-read Fulfillment $fulfillment
 * @property-read Seller $seller
 * @property-read FulfillmentFlowStep|null $fulfillmentFlowStep
 * @property string|null $step_label
 * @property string|null $carrier
 * @property string|null $tracking_number
 * @property string|null $actor_id
 */
#[Fillable([
    'fulfillment_id', 'seller_id', 'kind', 'fulfillment_flow_step_id', 'step_label',
    'actor_type', 'actor_id', 'carrier', 'tracking_number', 'occurred_at',
])]
class FulfillmentEvent extends Model
{
    /** @use HasFactory<FulfillmentEventFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'fev';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => FulfillmentEventKind::class,
            'actor_type' => ActorType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<FulfillmentFlowStep, $this> */
    public function fulfillmentFlowStep(): BelongsTo
    {
        return $this->belongsTo(FulfillmentFlowStep::class);
    }

    /**
     * The log in the order it happened, oldest first. `occurred_at` ties
     * within the same second; the id — a ULID — breaks the tie in the order
     * the rows were minted.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function inOrder(Builder $query): void
    {
        $query->orderBy('occurred_at')->orderBy('id');
    }
}
