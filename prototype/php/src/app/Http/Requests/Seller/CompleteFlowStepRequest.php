<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Fulfillment;
use App\Models\FulfillmentFlowStep;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * A step of another seller's flow is a 404, the same page as a step that does
 * not exist. A step that prints a label needs the carrier and the tracking
 * number it prints with; every other step carries neither.
 */
final class CompleteFlowStepRequest extends FormRequest
{
    public function authorize(): Response
    {
        $fulfillment = $this->fulfillment();

        return $this->step()->seller_id === $fulfillment->seller_id
            ? Gate::inspect('update', $fulfillment)
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $shipment = $this->step()->action->printsLabel()
            ? ['required', 'string', 'max:255']
            : ['prohibited'];

        return [
            'carrier' => $shipment,
            'tracking_number' => $shipment,
        ];
    }

    public function carrier(): ?string
    {
        return $this->filled('carrier') ? $this->string('carrier')->trim()->toString() : null;
    }

    public function trackingNumber(): ?string
    {
        return $this->filled('tracking_number') ? $this->string('tracking_number')->trim()->toString() : null;
    }

    public function step(): FulfillmentFlowStep
    {
        $step = $this->route('step');

        return $step instanceof FulfillmentFlowStep
            ? $step
            : throw new RuntimeException('The step route binds a fulfillment flow step.');
    }

    private function fulfillment(): Fulfillment
    {
        $fulfillment = $this->route('fulfillment');

        return $fulfillment instanceof Fulfillment
            ? $fulfillment
            : throw new RuntimeException('The step route binds a fulfillment.');
    }
}
