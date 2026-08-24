<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Fulfillment;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class DeclineFulfillmentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }

    private function fulfillment(): Fulfillment
    {
        $fulfillment = $this->route('fulfillment');

        return $fulfillment instanceof Fulfillment
            ? $fulfillment
            : throw new RuntimeException('The decline route binds a fulfillment.');
    }
}
