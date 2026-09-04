<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fulfillment\FlowStepAction;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<FulfillmentFlowStep>
 */
class FulfillmentFlowStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'fulfillment_flow_id' => FulfillmentFlow::factory(),
            'seller_id' => Seller::factory(),
            'key' => 'packed',
            'label' => 'Packed',
            'action' => FlowStepAction::None,
            'position' => 0,
        ];
    }

    public function printsLabel(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'label-printed',
            'label' => 'Label printed',
            'action' => FlowStepAction::PrintLabel,
        ]);
    }

    /**
     * A step of $flow, carried by the flow's own seller.
     */
    public function of(FulfillmentFlow $flow, int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'fulfillment_flow_id' => $flow->id,
            'seller_id' => $flow->seller_id,
            'position' => $position,
        ]);
    }
}
