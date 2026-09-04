<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlowStep;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<FulfillmentEvent>
 */
class FulfillmentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'fulfillment_id' => Fulfillment::factory(),
            'seller_id' => Seller::factory(),
            'kind' => FulfillmentEventKind::Shipped,
            'fulfillment_flow_step_id' => null,
            'step_label' => null,
            'actor_type' => ActorType::Seller,
            'actor_id' => null,
            'carrier' => null,
            'tracking_number' => null,
            'occurred_at' => now(),
        ];
    }

    public function completing(FulfillmentFlowStep $step): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => FulfillmentEventKind::StepCompleted,
            'fulfillment_flow_step_id' => $step->id,
            'step_label' => $step->label,
        ]);
    }

    public function on(Fulfillment $fulfillment): static
    {
        return $this->state(fn (array $attributes): array => [
            'fulfillment_id' => $fulfillment->id,
            'seller_id' => $fulfillment->seller_id,
            'actor_id' => $fulfillment->seller_id,
        ]);
    }
}
