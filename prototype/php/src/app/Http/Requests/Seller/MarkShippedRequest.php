<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Fulfillment;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class MarkShippedRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->fulfillment());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'carrier' => ['required', 'string', 'max:255'],
            'tracking_number' => ['required', 'string', 'max:255'],
        ];
    }

    public function carrier(): string
    {
        return $this->string('carrier')->toString();
    }

    public function trackingNumber(): string
    {
        return $this->string('tracking_number')->toString();
    }

    private function fulfillment(): Fulfillment
    {
        $fulfillment = $this->route('fulfillment');

        return $fulfillment instanceof Fulfillment
            ? $fulfillment
            : throw new RuntimeException('The shipment route binds a fulfillment.');
    }
}
