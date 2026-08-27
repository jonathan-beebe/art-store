<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Listing;
use App\Models\Property;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

final class OptionAxisRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0'],
            'property_id' => ['nullable', 'string', Rule::exists('properties', 'id')],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function position(): int
    {
        return $this->integer('position');
    }

    public function property(): ?Property
    {
        return $this->filled('property_id') ? Property::find($this->string('property_id')->toString()) : null;
    }

    public function listing(): Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            ? $listing
            : throw new RuntimeException('The option axis route binds a listing.');
    }
}
