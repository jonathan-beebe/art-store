<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Listing;
use App\Models\PropertyValue;
use App\Support\Configurator\PriceDifferenceInput;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

final class OptionValueRequest extends FormRequest
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
        return [
            'label' => ['required', 'string', 'max:255'],
            'surcharge' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! PriceDifferenceInput::isValid(is_string($value) ? $value : null)) {
                    $fail('The price difference is an amount in dollars, like 8.50 or -2.00.');
                }
            }],
            'is_default' => ['nullable', 'boolean'],
            'position' => ['required', 'integer', 'min:0'],
            'property_value_id' => ['nullable', 'string', Rule::exists('property_values', 'id')],
        ];
    }

    public function label(): string
    {
        return $this->string('label')->toString();
    }

    public function surchargeCents(): int
    {
        return PriceDifferenceInput::parseCents($this->string('surcharge')->toString());
    }

    public function isDefault(): bool
    {
        return $this->boolean('is_default');
    }

    public function position(): int
    {
        return $this->integer('position');
    }

    public function propertyValue(): ?PropertyValue
    {
        return $this->filled('property_value_id') ? PropertyValue::find($this->string('property_value_id')->toString()) : null;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The option value route binds a listing.');
    }
}
