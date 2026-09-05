<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Configurator\PriceDifferenceInput;
use App\Models\Listing;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class ModifierOptionRequest extends FormRequest
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
            'add_on_price' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! PriceDifferenceInput::isValid(is_string($value) ? $value : null)) {
                    $fail('The price difference is an amount in dollars, like 3.00 or -1.00.');
                }
            }],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    public function label(): string
    {
        return $this->string('label')->toString();
    }

    public function addOnPriceCents(): int
    {
        return PriceDifferenceInput::parseCents($this->string('add_on_price')->toString());
    }

    public function position(): int
    {
        return $this->integer('position');
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The modifier option route binds a listing.');
    }
}
