<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Money\Money;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * One sparse variant row: one option value per axis, picked from a select per
 * axis rather than a free-text combination — the form behind "the seller
 * creates only the combinations that actually sell".
 */
final class CreateVariantRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->listing());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'sku' => ['nullable', 'string', 'max:255'],
            'price_override' => ['nullable', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'is_serialized' => ['nullable', 'boolean'],
        ];

        foreach ($this->listing()->optionAxes as $axis) {
            $rules["option_value_id.{$axis->id}"] = ['required', 'string', Rule::exists('option_values', 'id')->where('axis_id', $axis->id)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['price_override.regex' => 'The price override is an amount in dollars, like 249.00 or -5.00.'];
    }

    /**
     * @return list<OptionValue>
     */
    public function optionValues(): array
    {
        return array_values($this->listing()->optionAxes->map(
            fn (OptionAxis $axis): OptionValue => OptionValue::where('id', $this->input("option_value_id.{$axis->id}"))->firstOrFail()
        )->all());
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

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The variant route binds a listing.');
    }
}
