<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FlowStepAction;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\FulfillmentFlowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One step of a flow. `key` is the seller-facing handle, unique inside the
 * flow; `position` is where the step sits, unique inside the flow.
 *
 * @property-read FulfillmentFlow $fulfillmentFlow
 * @property-read Seller $seller
 * @property string $key
 * @property string $label
 */
#[Fillable(['fulfillment_flow_id', 'seller_id', 'key', 'label', 'action', 'position'])]
class FulfillmentFlowStep extends Model
{
    /** @use HasFactory<FulfillmentFlowStepFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'ffs';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'action' => FlowStepAction::class,
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<FulfillmentFlow, $this> */
    public function fulfillmentFlow(): BelongsTo
    {
        return $this->belongsTo(FulfillmentFlow::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function toFlowStep(): FlowStep
    {
        return new FlowStep(
            id: $this->id,
            key: $this->key,
            label: $this->label,
            action: $this->action,
            position: $this->position,
        );
    }
}
