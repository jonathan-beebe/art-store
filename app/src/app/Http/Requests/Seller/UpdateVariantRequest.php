<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Money\Money;
use App\Models\Listing;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class UpdateVariantRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:255'],
            'price_override' => ['nullable', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'is_serialized' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['price_override.regex' => 'The price override is an amount in dollars, like 249.00 or -5.00.'];
    }

    public function sku(): ?string
    {
        return $this->filled('sku') ? $this->string('sku')->toString() : null;
    }

    public function priceOverrideCents(): ?int
    {
        return $this->filled('price_override') ? Money::fromDollars($this->string('price_override')->toString())->cents : null;
    }

    public function quantity(): ?int
    {
        return $this->filled('quantity') ? $this->integer('quantity') : null;
    }

    public function isSerialized(): bool
    {
        return $this->boolean('is_serialized');
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The variant route binds a listing.');
    }
}
