<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Money\Money;
use App\Models\Listing;
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'add_on_price' => ['required', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['add_on_price.regex' => 'The add-on price is an amount in dollars, like 3.00.'];
    }

    public function label(): string
    {
        return $this->string('label')->toString();
    }

    public function addOnPriceCents(): int
    {
        return Money::fromDollars($this->string('add_on_price')->toString())->cents;
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
